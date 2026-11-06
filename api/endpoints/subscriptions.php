<?php
// subscriptions.php — Subscription lifecycle, dynamic plans, and Squad payment integration
require_once __DIR__ . '/../services/SquadService.php';

$method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = $path ?? '';

// Helper: Calculate end date accounting for existing active subscription
function vpr_calculate_end_date(string $intervalUnit, int $intervalCount, ?string $currentEndDate = null): string {
    $baseTime = time();
    if ($currentEndDate) {
        $currentTs = strtotime($currentEndDate);
        if ($currentTs > $baseTime) {
            $baseTime = $currentTs; // Extend from current expiration date!
        }
    }
    
    $modifier = "+{$intervalCount} " . strtolower($intervalUnit);
    return date('Y-m-d H:i:s', strtotime($modifier, $baseTime));
}

// Helper: Log payment audit entry
function vpr_log_payment_audit(mysqli $conn, ?int $txnId, ?string $txnRef, ?int $userId, string $action, string $actorType, ?int $actorId, ?string $reason, ?array $payload = null): void {
    $stmt = $conn->prepare("INSERT INTO payment_audit_logs (transaction_id, transaction_ref, user_id, action, actor_type, actor_id, reason, payload, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    if ($stmt) {
        $payloadJson = $payload ? json_encode($payload) : null;
        $stmt->bind_param("isisssis", $txnId, $txnRef, $userId, $action, $actorType, $actorId, $reason, $payloadJson);
        $stmt->execute();
        $stmt->close();
    }
}

// ══════════════════════════════════════════════════════════════════════════
// 1. GET /subscriptions/plans — Authoritative dynamic subscription plans
// ══════════════════════════════════════════════════════════════════════════
if ($method === 'GET' && $path === '/subscriptions/plans') {
    $plans = [];
    $stmt = $conn->prepare("SELECT id, plan_code, name, description, category, price, currency, interval_unit, interval_count, features_json, limits_json, is_active, is_featured, badge_text, sort_order FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['features'] = !empty($row['features_json']) ? json_decode($row['features_json'], true) : [];
            $row['limits'] = !empty($row['limits_json']) ? json_decode($row['limits_json'], true) : new stdClass();
            $plans[] = $row;
        }
        $stmt->close();
    }

    // Fallback if table hasn't been seeded yet
    if (empty($plans)) {
        $plans = [
            [
                'id' => 1,
                'plan_code' => 'member_monthly',
                'name' => 'Premium Member',
                'description' => 'Complete individual access with HD streams and crusade hosting.',
                'category' => 'membership',
                'price' => 3000.00,
                'currency' => 'NGN',
                'interval_unit' => 'month',
                'interval_count' => 1,
                'features' => [
                    'Full 1080p Full HD praise broadcasts',
                    'Host digital crusades with up to 5,000 attendees',
                    'Create praise meeting rooms & watch parties',
                    'Complete on-demand video & ministration library',
                    'Exclusive children outreach series & teachings',
                ],
                'is_active' => 1,
                'is_featured' => 1,
                'badge_text' => 'Most Popular'
            ],
            [
                'id' => 2,
                'plan_code' => 'member_annual',
                'name' => 'Premium Annual',
                'description' => 'Full premium access for a full year with 2 months free savings.',
                'category' => 'membership',
                'price' => 30000.00,
                'currency' => 'NGN',
                'interval_unit' => 'year',
                'interval_count' => 1,
                'features' => [
                    'Everything in Premium Member plan',
                    'Save ₦6,000 annually (2 months free)',
                    'Priority access to special praise festivals',
                    'Early access to upcoming ministration releases',
                ],
                'is_active' => 1,
                'is_featured' => 0,
                'badge_text' => 'Best Value (Save 17%)'
            ]
        ];
    }

    json_response(['success' => true, 'data' => $plans]);
}

// ══════════════════════════════════════════════════════════════════════════
// 2. GET /user/subscription — User's active subscription status & history
// ══════════════════════════════════════════════════════════════════════════
if ($method === 'GET' && ($path === '/user/subscription' || $path === '/subscriptions/status')) {
    require_auth();
    $userId = $_SESSION['user_id'];

    // 1. Fetch latest active or recent subscription
    $subStmt = $conn->prepare("
        SELECT s.id, s.subscription_type, s.plan_id, s.plan_code, s.streaming_plan_id, s.streaming_status,
               s.status, s.auto_renew, s.start_date, s.end_date, s.created_at,
               sp.name AS plan_name, sp.price AS plan_price, sp.currency AS plan_currency,
               sp.interval_unit, sp.features_json
        FROM subscriptions s
        LEFT JOIN subscription_plans sp ON sp.plan_code = s.plan_code OR sp.id = s.plan_id
        WHERE s.user_id = ?
        ORDER BY s.end_date DESC
        LIMIT 1
    ");
    $subscription = null;
    $isActive = false;
    $daysRemaining = 0;

    if ($subStmt) {
        $subStmt->bind_param("i", $userId);
        $subStmt->execute();
        $res = $subStmt->get_result();
        $subscription = $res->fetch_assoc();
        $subStmt->close();

        if ($subscription) {
            $endTs = strtotime($subscription['end_date']);
            $nowTs = time();
            if ($endTs > $nowTs && $subscription['status'] !== 'cancelled') {
                $isActive = true;
                $daysRemaining = (int)ceil(($endTs - $nowTs) / 86400);
            }
            if (!empty($subscription['features_json'])) {
                $subscription['features'] = json_decode($subscription['features_json'], true);
            }
        }
    }

    // 2. Fetch user's recent payment transactions
    $transactions = [];
    $txnStmt = $conn->prepare("
        SELECT id, transaction_ref, amount, currency, gateway, channel, subscription_type, status, created_at
        FROM transactions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    if ($txnStmt) {
        $txnStmt->bind_param("i", $userId);
        $txnStmt->execute();
        $res = $txnStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $transactions[] = $row;
        }
        $txnStmt->close();
    }

    json_response([
        'success' => true,
        'data' => [
            'is_subscribed'   => $isActive,
            'status'          => $isActive ? 'active' : ($subscription ? 'expired' : 'none'),
            'days_remaining'  => $daysRemaining,
            'current'         => $subscription,
            'recent_payments' => $transactions
        ]
    ]);
}

// ══════════════════════════════════════════════════════════════════════════
// 3. POST /subscriptions/squad/initiate — Initiate Squad checkout
// ══════════════════════════════════════════════════════════════════════════
if ($method === 'POST' && $path === '/subscriptions/squad/initiate') {
    require_auth();
    $userId = $_SESSION['user_id'];
    $data = get_request_data();

    $planCode = trim($data['plan_code'] ?? $data['plan'] ?? '');
    $isRecurring = !empty($data['is_recurring']);
    $giftedUsername = trim($data['gifted_username'] ?? '');
    $giftMessage = trim($data['gift_message'] ?? '');

    if ($planCode === '') {
        json_response(['success' => false, 'message' => 'Please select a valid subscription plan.'], 400);
    }

    // 1. Authoritative plan validation from backend
    $plan = null;
    $planStmt = $conn->prepare("SELECT id, plan_code, name, price, currency, interval_unit, interval_count, category FROM subscription_plans WHERE plan_code = ? AND is_active = 1 LIMIT 1");
    if ($planStmt) {
        $planStmt->bind_param("s", $planCode);
        $planStmt->execute();
        $res = $planStmt->get_result();
        $plan = $res->fetch_assoc();
        $planStmt->close();
    }

    // Fallback: Check streaming_plans table if plan_code starts with 'streaming_plan_' or is numeric
    if (!$plan && (strpos($planCode, 'streaming_plan_') === 0 || is_numeric($planCode))) {
        $streamingPlanId = (int)str_replace('streaming_plan_', '', $planCode);
        $sStmt = $conn->prepare("SELECT id, name, price, billing_period FROM streaming_plans WHERE id = ? AND is_active = 1 LIMIT 1");
        if ($sStmt) {
            $sStmt->bind_param("i", $streamingPlanId);
            $sStmt->execute();
            $sRes = $sStmt->get_result();
            if ($sRow = $sRes->fetch_assoc()) {
                $rawPrice = (float)$sRow['price'];
                // Guard: If stored in legacy USD (<= 100), convert to NGN equivalent (₦15,000 for Basic, ₦45,000 for Pro)
                $ngnPrice = $rawPrice <= 100 ? ($rawPrice <= 15 ? 15000.00 : 45000.00) : $rawPrice;
                $plan = [
                    'id' => $sRow['id'],
                    'plan_code' => 'streaming_plan_' . $sRow['id'],
                    'name' => $sRow['name'],
                    'price' => $ngnPrice,
                    'currency' => 'NGN',
                    'interval_unit' => 'month',
                    'interval_count' => 1,
                    'category' => 'streaming'
                ];
            }
            $sStmt->close();
        }
    }

    // Fallback default for member_monthly if table not yet seeded
    if (!$plan && $planCode === 'member_monthly') {
        $plan = [
            'id' => 1,
            'plan_code' => 'member_monthly',
            'name' => 'Premium Member',
            'price' => 3000.00,
            'currency' => 'NGN',
            'interval_unit' => 'month',
            'interval_count' => 1,
            'category' => 'membership'
        ];
    } elseif (!$plan && $planCode === 'member_annual') {
        $plan = [
            'id' => 2,
            'plan_code' => 'member_annual',
            'name' => 'Premium Annual',
            'price' => 30000.00,
            'currency' => 'NGN',
            'interval_unit' => 'year',
            'interval_count' => 1,
            'category' => 'membership'
        ];
    }

    if (!$plan) {
        json_response(['success' => false, 'message' => 'The selected subscription plan is invalid or no longer active.'], 404);
    }

    // 2. Fetch paying customer details
    $uStmt = $conn->prepare("SELECT id, username, email FROM praise_users WHERE id = ? LIMIT 1");
    $uStmt->bind_param("i", $userId);
    $uStmt->execute();
    $userRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();

    if (!$userRow || empty($userRow['email'])) {
        json_response(['success' => false, 'message' => 'Valid user profile and email address required to proceed.'], 400);
    }

    // 3. Resolve gifted recipient if applicable
    $giftedUserId = null;
    if ($giftedUsername !== '') {
        $gStmt = $conn->prepare("SELECT id FROM praise_users WHERE username = ? OR email = ? LIMIT 1");
        $gStmt->bind_param("ss", $giftedUsername, $giftedUsername);
        $gStmt->execute();
        $gRes = $gStmt->get_result()->fetch_assoc();
        $gStmt->close();
        if ($gRes) {
            $giftedUserId = (int)$gRes['id'];
        } else {
            json_response(['success' => false, 'message' => "Gift recipient '$giftedUsername' was not found."], 404);
        }
    }

    // 4. Generate unique transaction reference
    $txnRef = SquadService::generateReference('vpr_sq_');
    $amount = (float)$plan['price'];
    $currency = strtoupper($plan['currency'] ?? 'NGN');

    $isStreamingPlan = ($plan['category'] === 'streaming' || strpos($plan['plan_code'], 'streaming_') === 0 || strpos($plan['plan_code'], 'studio_') === 0);
    $defaultRedirect = $isStreamingPlan ? '/dashboard/streaming/plans' : '/subscribe';
    $redirectPath = !empty($data['redirect_path']) ? '/' . ltrim(trim($data['redirect_path']), '/') : $defaultRedirect;

    $baseUrl = defined('REACT_APP_URL') ? REACT_APP_URL : 'http://localhost:5173';
    $callbackUrl = rtrim($baseUrl, '/') . $redirectPath . (strpos($redirectPath, '?') !== false ? '&' : '?') . 'squad_ref=' . urlencode($txnRef);

    $metadata = [
        'user_id'          => $userId,
        'username'         => $userRow['username'],
        'plan_id'          => $plan['id'],
        'plan_code'        => $plan['plan_code'],
        'category'         => $plan['category'],
        'interval_unit'    => $plan['interval_unit'],
        'interval_count'   => $plan['interval_count'],
        'gifted_user_id'   => $giftedUserId,
        'gift_message'     => $giftMessage,
        'is_recurring'     => $isRecurring,
        'environment'      => defined('SQUAD_ENV') ? SQUAD_ENV : 'sandbox'
    ];
    $metadataJson = json_encode($metadata);

    // 5. Insert pending transaction record
    $insStmt = $conn->prepare("
        INSERT INTO transactions 
          (user_id, gifted_user_id, gift_message, full_name, amount, currency, proof_of_transaction, subscription_type, status, transaction_ref, gateway, is_recurring, metadata, created_at)
        VALUES 
          (?, ?, ?, ?, ?, ?, 'squad_online', ?, 'pending', ?, 'squad', ?, ?, NOW())
    ");
    $fullName = $userRow['username'];
    $isRecInt = $isRecurring ? 1 : 0;
    $insStmt->bind_param(
        "iisssdsssis",
        $userId,
        $giftedUserId,
        $giftMessage,
        $fullName,
        $amount,
        $currency,
        $plan['plan_code'],
        $txnRef,
        $isRecInt,
        $metadataJson
    );

    if (!$insStmt->execute()) {
        $insStmt->close();
        json_response(['success' => false, 'message' => 'Database error recording pending transaction.'], 500);
    }
    $transactionId = $conn->insert_id;
    $insStmt->close();

    // Log initiation audit
    vpr_log_payment_audit($conn, $transactionId, $txnRef, $userId, 'INITIATED', 'user', $userId, 'User initiated Squad checkout', $metadata);

    // 6. Call Squad API to initiate payment
    $squad = new SquadService();
    $squadResult = $squad->initiatePayment([
        'amount'           => $amount,
        'email'            => $userRow['email'],
        'currency'         => $currency,
        'transaction_ref'  => $txnRef,
        'customer_name'    => $fullName,
        'callback_url'     => $callbackUrl,
        'metadata'         => $metadata,
        'is_recurring'     => $isRecurring
    ]);

    if (!$squadResult['success']) {
        // Mark transaction as failed
        $upd = $conn->prepare("UPDATE transactions SET status = 'failed' WHERE id = ?");
        $upd->bind_param("i", $transactionId);
        $upd->execute();
        $upd->close();

        vpr_log_payment_audit($conn, $transactionId, $txnRef, $userId, 'FAILED', 'system', null, 'Squad initiation failed: ' . $squadResult['message']);
        json_response(['success' => false, 'message' => $squadResult['message']], 502);
    }

    json_response([
        'success'         => true,
        'checkout_url'    => $squadResult['checkout_url'],
        'transaction_ref' => $txnRef,
        'plan' => [
            'name'     => $plan['name'],
            'price'    => $amount,
            'currency' => $currency
        ]
    ]);
}

// ══════════════════════════════════════════════════════════════════════════
// 4. GET /subscriptions/squad/verify/{reference} — Server-side verification
// ══════════════════════════════════════════════════════════════════════════
if ($method === 'GET' && preg_match('#^/subscriptions/squad/verify/([a-zA-Z0-9_\-]+)$#', $path, $matches)) {
    require_auth();
    $userId = $_SESSION['user_id'];
    $txnRef = trim($matches[1]);

    if ($txnRef === '') {
        json_response(['success' => false, 'message' => 'Transaction reference is missing.'], 400);
    }

    // 1. Fetch pending/existing transaction record
    $tStmt = $conn->prepare("
        SELECT id, user_id, gifted_user_id, amount, currency, subscription_type, status, metadata, card_token
        FROM transactions
        WHERE transaction_ref = ? AND gateway = 'squad'
        LIMIT 1
    ");
    $tStmt->bind_param("s", $txnRef);
    $tStmt->execute();
    $txn = $tStmt->get_result()->fetch_assoc();
    $tStmt->close();

    if (!$txn) {
        json_response(['success' => false, 'message' => 'Payment transaction reference not found.'], 404);
    }

    // Security check: ensure current user is either the payer, recipient, or an admin
    $isAdmin = (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
    if (!$isAdmin && (int)$txn['user_id'] !== (int)$userId && (int)$txn['gifted_user_id'] !== (int)$userId) {
        json_response(['success' => false, 'message' => 'Unauthorized access to this transaction.'], 403);
    }

    // Idempotency: If already approved, return success immediately
    if ($txn['status'] === 'approved') {
        // Query active subscription details for the response
        $activeSub = null;
        $targetUid = !empty($txn['gifted_user_id']) ? (int)$txn['gifted_user_id'] : (int)$txn['user_id'];
        $subStmt = $conn->prepare("SELECT plan_code, start_date, end_date FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
        if ($subStmt) {
            $subStmt->bind_param("i", $targetUid);
            $subStmt->execute();
            $activeSub = $subStmt->get_result()->fetch_assoc();
            $subStmt->close();
        }

        json_response([
            'success'   => true,
            'message'   => 'Payment has already been verified and subscription is active.',
            'reference' => $txnRef,
            'status'    => 'approved',
            'data'      => [
                'plan_code'  => $activeSub['plan_code'] ?? $txn['subscription_type'],
                'start_date' => $activeSub['start_date'] ?? $txn['created_at'],
                'end_date'   => $activeSub['end_date'] ?? null,
                'extended'   => false
            ]
        ]);
    }

    // 2. Query Squad API for server-side verification
    $squad = new SquadService();
    $verifyResult = $squad->verifyTransaction($txnRef);

    if (!$verifyResult['success'] || strcasecmp($verifyResult['status'], 'success') !== 0) {
        $gatewayStatus = strtolower($verifyResult['status'] ?? 'pending');
        $isDefiniteFailure = in_array($gatewayStatus, ['failed', 'abandoned', 'cancelled']);

        if ($isDefiniteFailure) {
            $updFail = $conn->prepare("UPDATE transactions SET status = 'failed', gateway_response = ?, updated_at = NOW() WHERE id = ?");
            if ($updFail) {
                $rawResp = json_encode($verifyResult['raw'] ?? []);
                $updFail->bind_param("si", $rawResp, $txn['id']);
                $updFail->execute();
                $updFail->close();
            }
            vpr_log_payment_audit($conn, $txn['id'], $txnRef, $userId, 'PAYMENT_FAILED', 'system', null, 'Squad verification returned terminal status: ' . ($verifyResult['status'] ?? 'Failed'));
            $userMsg = ($gatewayStatus === 'abandoned' || $gatewayStatus === 'cancelled')
                ? 'Payment checkout was cancelled or abandoned. You can try again whenever you are ready.'
                : 'Payment was declined or could not be completed by your bank. Please try again with another card or method.';
        } else {
            vpr_log_payment_audit($conn, $txn['id'], $txnRef, $userId, 'VERIFICATION_PENDING', 'system', null, 'Squad verification pending: ' . ($verifyResult['status'] ?? 'Pending'));
            $userMsg = 'Payment has not been confirmed by Squad yet. If you were debited, your subscription will be activated automatically.';
        }

        json_response([
            'success' => false,
            'message' => $userMsg,
            'status'  => $verifyResult['status'] ?? 'Pending'
        ], 400);
    }

    // 3. Amount & Currency integrity check
    $verifiedAmount = $verifyResult['amount'];
    if ($verifiedAmount !== null && abs($verifiedAmount - (float)$txn['amount']) > 0.01) {
        vpr_log_payment_audit($conn, $txn['id'], $txnRef, $userId, 'AMOUNT_MISMATCH', 'system', null, "Amount mismatch: Expected {$txn['amount']}, received {$verifiedAmount}");
        json_response(['success' => false, 'message' => 'Payment amount verification mismatch.'], 400);
    }

    $verifiedCurrency = strtoupper($verifyResult['currency'] ?? 'NGN');
    $expectedCurrency = strtoupper($txn['currency'] ?? 'NGN');
    if ($verifiedCurrency !== $expectedCurrency) {
        vpr_log_payment_audit($conn, $txn['id'], $txnRef, $userId, 'CURRENCY_MISMATCH', 'system', null, "Currency mismatch: Expected {$expectedCurrency}, received {$verifiedCurrency}");
        json_response(['success' => false, 'message' => 'Payment currency verification mismatch.'], 400);
    }

    // 4. Execute atomic database transaction
    $conn->begin_transaction();
    try {
        $channel = $verifyResult['channel'] ?? 'card';
        $cardToken = $verifyResult['card_token'] ?? $txn['card_token'];
        $gatewayResp = json_encode($verifyResult['raw'] ?? []);

        // Update transaction status to approved with atomic concurrency guard
        $updTxn = $conn->prepare("UPDATE transactions SET status = 'approved', channel = ?, card_token = ?, gateway_response = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'");
        $updTxn->bind_param("sssi", $channel, $cardToken, $gatewayResp, $txn['id']);
        $updTxn->execute();
        $affected = $updTxn->affected_rows;
        $updTxn->close();

        if ($affected === 0) {
            // Transaction was already approved concurrently by webhook
            $conn->rollback();
            $targetUid = !empty($txn['gifted_user_id']) ? (int)$txn['gifted_user_id'] : (int)$txn['user_id'];
            $subStmt = $conn->prepare("SELECT plan_code, start_date, end_date FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
            $activeSub = null;
            if ($subStmt) {
                $subStmt->bind_param("i", $targetUid);
                $subStmt->execute();
                $activeSub = $subStmt->get_result()->fetch_assoc();
                $subStmt->close();
            }
            json_response([
                'success'   => true,
                'message'   => 'Payment confirmed! Your subscription is active.',
                'reference' => $txnRef,
                'status'    => 'approved',
                'data'      => [
                    'plan_code'  => $activeSub['plan_code'] ?? $txn['subscription_type'],
                    'start_date' => $activeSub['start_date'] ?? $txn['created_at'],
                    'end_date'   => $activeSub['end_date'] ?? null,
                    'extended'   => false
                ]
            ]);
        }

        // Target user: recipient for gifted plans, else payer
        $targetUserId = !empty($txn['gifted_user_id']) ? (int)$txn['gifted_user_id'] : (int)$txn['user_id'];
        $meta = !empty($txn['metadata']) ? json_decode($txn['metadata'], true) : [];
        $planCode = $txn['subscription_type'];
        $planId = $meta['plan_id'] ?? null;
        $intervalUnit = $meta['interval_unit'] ?? 'month';
        $intervalCount = (int)($meta['interval_count'] ?? 1);
        $category = $meta['category'] ?? 'membership';

        // Check if target user has an existing active subscription to extend
        $curSubStmt = $conn->prepare("SELECT end_date FROM subscriptions WHERE user_id = ? AND end_date > NOW() ORDER BY end_date DESC LIMIT 1");
        $curSubStmt->bind_param("i", $targetUserId);
        $curSubStmt->execute();
        $curSubRes = $curSubStmt->get_result()->fetch_assoc();
        $curSubStmt->close();

        $currentEndDate = $curSubRes['end_date'] ?? null;
        $startDate = date('Y-m-d H:i:s');
        $endDate = vpr_calculate_end_date($intervalUnit, $intervalCount, $currentEndDate);

        // Insert new active subscription record
        $isStreaming = ($category === 'streaming' || strpos($planCode, 'streaming_plan_') === 0 || strpos($planCode, 'studio_') === 0);
        $streamingStatus = $isStreaming ? 'active' : 'inactive';
        $streamingPlanId = null;
        if ($isStreaming) {
            if (strpos($planCode, 'streaming_plan_') === 0) {
                $streamingPlanId = (int)str_replace('streaming_plan_', '', $planCode);
            } elseif ($planCode === 'studio_basic') {
                $streamingPlanId = 1;
            } elseif ($planCode === 'studio_pro') {
                $streamingPlanId = 2;
            } else {
                $streamingPlanId = $planId ?: 1;
            }
        }

        $insSub = $conn->prepare("
            INSERT INTO subscriptions 
              (user_id, subscription_type, plan_id, plan_code, streaming_plan_id, streaming_status, status, start_date, end_date, created_at)
            VALUES 
              (?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW())
        ");
        $subType = $isStreaming ? 'streaming' : $planCode;
        $insSub->bind_param("isisisss", $targetUserId, $subType, $planId, $planCode, $streamingPlanId, $streamingStatus, $startDate, $endDate);
        $insSub->execute();
        $newSubId = $conn->insert_id;
        $insSub->close();

        // Update target user subscription_status to active
        $updUser = $conn->prepare("UPDATE praise_users SET subscription_status = 'active' WHERE id = ?");
        $updUser->bind_param("i", $targetUserId);
        $updUser->execute();
        $updUser->close();

        // If streaming plan, provision channel if not already existing
        if ($isStreaming) {
            $chanCheck = $conn->prepare("SELECT id FROM streaming_channels WHERE user_id = ? AND is_active = 1");
            $chanCheck->bind_param("i", $targetUserId);
            $chanCheck->execute();
            if ($chanCheck->get_result()->num_rows === 0) {
                // Auto-provision channel placeholder or via AWS credentials if available
                $chanArn = 'arn:aws:ivs:us-east-1:placeholder:channel/vpr_user_' . $targetUserId;
                $streamKeyArn = 'arn:aws:ivs:us-east-1:placeholder:stream-key/' . bin2hex(random_bytes(16));
                $ingest = 'rtmps://placeholder.global-contribute.live-video.net:443/app/';
                $playback = 'https://placeholder.us-east-1.playback.live-video.net/api/video/v1/us-east-1.placeholder.channel.m3u8';
                
                $insChan = $conn->prepare("INSERT INTO streaming_channels (user_id, aws_channel_arn, aws_stream_key_arn, ingest_endpoint, playback_url) VALUES (?, ?, ?, ?, ?)");
                $insChan->bind_param("issss", $targetUserId, $chanArn, $streamKeyArn, $ingest, $playback);
                $insChan->execute();
                $insChan->close();
            }
            $chanCheck->close();
        }

        // Record successful audit log
        vpr_log_payment_audit($conn, $txn['id'], $txnRef, $userId, 'VERIFIED', 'user', $userId, 'Subscription successfully verified & activated', [
            'subscription_id' => $newSubId,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'extended'        => !empty($currentEndDate)
        ]);

        $conn->commit();

        json_response([
            'success'   => true,
            'message'   => 'Payment confirmed! Your subscription has been activated.',
            'reference' => $txnRef,
            'data'      => [
                'plan_code'  => $planCode,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'extended'   => !empty($currentEndDate)
            ]
        ]);

    } catch (\Throwable $e) {
        $conn->rollback();
        error_log("Squad Verify DB Error: " . $e->getMessage());
        json_response(['success' => false, 'message' => 'Database error finalizing subscription activation.'], 500);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// 5. POST /subscriptions/squad/webhook — Squad Webhook Notification
// ══════════════════════════════════════════════════════════════════════════
if ($method === 'POST' && $path === '/subscriptions/squad/webhook') {
    // 1. Optional IP Whitelist enforcement if enabled
    $enforceIp = defined('SQUAD_ENFORCE_IP_WHITELIST') ? SQUAD_ENFORCE_IP_WHITELIST : (getenv('SQUAD_ENFORCE_IP_WHITELIST') === 'true');
    if ($enforceIp) {
        $clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if (strpos($clientIp, ',') !== false) {
            $clientIp = trim(explode(',', $clientIp)[0]);
        }
        $squadIp = '18.133.63.109';
        if ($clientIp !== $squadIp) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized webhook IP.']);
            exit;
        }
    }

    // 2. Validate signature header (case-insensitive extraction across server environments)
    $allHeaders = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
    $headerSignature = $_SERVER['HTTP_X_SQUAD_ENCRYPTED_BODY'] 
        ?? $_SERVER['REDIRECT_HTTP_X_SQUAD_ENCRYPTED_BODY']
        ?? $_SERVER['HTTP_X_SQUAD_SIGNATURE'] 
        ?? $_SERVER['REDIRECT_HTTP_X_SQUAD_SIGNATURE']
        ?? ($allHeaders['x-squad-encrypted-body'] ?? $allHeaders['x-squad-signature'] ?? '');

    $rawBody = file_get_contents('php://input');
    $squad = new SquadService();

    if (!$squad->validateWebhookSignature($rawBody, $headerSignature)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid webhook signature.']);
        exit;
    }

    $event = json_decode($rawBody, true);
    if (!is_array($event)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Malformed JSON body.']);
        exit;
    }

    // Extract reference across all supported Squad payload formats
    $txnRef = $event['TransactionRef'] 
        ?? $event['transaction_ref'] 
        ?? $event['Body']['transaction_ref'] 
        ?? $event['Body']['TransactionRef']
        ?? $event['Body']['transaction_reference']
        ?? $event['transaction_reference']
        ?? null;

    if (!$txnRef) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing transaction reference in webhook.']);
        exit;
    }

    // 3. Fetch pending transaction
    $tStmt = $conn->prepare("SELECT id, user_id, gifted_user_id, amount, currency, subscription_type, status, metadata, card_token FROM transactions WHERE transaction_ref = ? LIMIT 1");
    $tStmt->bind_param("s", $txnRef);
    $tStmt->execute();
    $txn = $tStmt->get_result()->fetch_assoc();
    $tStmt->close();

    if (!$txn) {
        // Unknown transaction reference
        http_response_code(200); // Return 200 so Squad does not retry indefinitely
        echo json_encode(['success' => true, 'message' => 'Transaction not found in database.']);
        exit;
    }

    // Idempotency: If already approved, avoid duplicate extension
    if ($txn['status'] === 'approved') {
        vpr_log_payment_audit($conn, $txn['id'], $txnRef, $txn['user_id'], 'WEBHOOK_DUPLICATE', 'webhook', null, 'Duplicate webhook received; already processed.');
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Event already processed idempotently.']);
        exit;
    }

    $eventName = strtolower($event['Event'] ?? $event['event'] ?? 'charge_successful');
    $bodyData = $event['Body'] ?? $event['data'] ?? [];
    $statusString = strtolower($bodyData['transaction_status'] ?? 'success');

    if ($eventName === 'charge_successful' || $statusString === 'success') {
        // Amount integrity verification (lowest denomination integer comparison)
        $chargedKobo = (int)($bodyData['amount'] ?? $bodyData['transaction_amount'] ?? $event['amount'] ?? 0);
        $expectedKobo = SquadService::toKobo((float)$txn['amount']);
        if ($chargedKobo > 0 && $chargedKobo !== $expectedKobo) {
            vpr_log_payment_audit($conn, $txn['id'], $txnRef, $txn['user_id'], 'WEBHOOK_AMOUNT_MISMATCH', 'webhook', null, "Webhook amount mismatch: Expected {$expectedKobo} kobo, received {$chargedKobo} kobo");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Amount mismatch in webhook payload.']);
            exit;
        }

        // Currency verification
        $webhookCurrency = strtoupper($bodyData['currency'] ?? $event['currency'] ?? 'NGN');
        $expectedCurrency = strtoupper($txn['currency'] ?? 'NGN');
        if (!empty($bodyData['currency']) && $webhookCurrency !== $expectedCurrency) {
            vpr_log_payment_audit($conn, $txn['id'], $txnRef, $txn['user_id'], 'WEBHOOK_CURRENCY_MISMATCH', 'webhook', null, "Webhook currency mismatch: Expected {$expectedCurrency}, received {$webhookCurrency}");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Currency mismatch in webhook payload.']);
            exit;
        }
        $conn->begin_transaction();
        try {
            $channel = $bodyData['channel'] ?? 'card';
            $cardToken = $bodyData['card_token'] ?? $txn['card_token'];
            $gatewayResp = json_encode($event);

            // Update transaction atomically with concurrency guard (only update if still pending)
            $updTxn = $conn->prepare("UPDATE transactions SET status = 'approved', channel = ?, card_token = ?, gateway_response = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'");
            $updTxn->bind_param("sssi", $channel, $cardToken, $gatewayResp, $txn['id']);
            $updTxn->execute();
            $affected = $updTxn->affected_rows;
            $updTxn->close();

            if ($affected === 0) {
                // Already approved concurrently by verify redirect
                $conn->rollback();
                vpr_log_payment_audit($conn, $txn['id'], $txnRef, $txn['user_id'], 'WEBHOOK_DUPLICATE_RACE', 'webhook', null, 'Transaction already approved concurrently.');
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Event already processed concurrently.']);
                exit;
            }

            $targetUserId = !empty($txn['gifted_user_id']) ? (int)$txn['gifted_user_id'] : (int)$txn['user_id'];
            $meta = !empty($txn['metadata']) ? json_decode($txn['metadata'], true) : [];
            $planCode = $txn['subscription_type'];
            $planId = $meta['plan_id'] ?? null;
            $intervalUnit = $meta['interval_unit'] ?? 'month';
            $intervalCount = (int)($meta['interval_count'] ?? 1);
            $category = $meta['category'] ?? 'membership';

            // Check existing active subscription to extend
            $curSubStmt = $conn->prepare("SELECT end_date FROM subscriptions WHERE user_id = ? AND end_date > NOW() ORDER BY end_date DESC LIMIT 1");
            $curSubStmt->bind_param("i", $targetUserId);
            $curSubStmt->execute();
            $curSubRes = $curSubStmt->get_result()->fetch_assoc();
            $curSubStmt->close();

            $currentEndDate = $curSubRes['end_date'] ?? null;
            $startDate = date('Y-m-d H:i:s');
            $endDate = vpr_calculate_end_date($intervalUnit, $intervalCount, $currentEndDate);

            $isStreaming = ($category === 'streaming' || strpos($planCode, 'streaming_plan_') === 0 || strpos($planCode, 'studio_') === 0);
            $streamingStatus = $isStreaming ? 'active' : 'inactive';
            $streamingPlanId = null;
            if ($isStreaming) {
                if (strpos($planCode, 'streaming_plan_') === 0) {
                    $streamingPlanId = (int)str_replace('streaming_plan_', '', $planCode);
                } elseif ($planCode === 'studio_basic') {
                    $streamingPlanId = 1;
                } elseif ($planCode === 'studio_pro') {
                    $streamingPlanId = 2;
                } else {
                    $streamingPlanId = $planId ?: 1;
                }
            }

            $insSub = $conn->prepare("
                INSERT INTO subscriptions 
                  (user_id, subscription_type, plan_id, plan_code, streaming_plan_id, streaming_status, status, start_date, end_date, created_at)
                VALUES 
                  (?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW())
            ");
            $subType = $isStreaming ? 'streaming' : $planCode;
            $insSub->bind_param("isisisss", $targetUserId, $subType, $planId, $planCode, $streamingPlanId, $streamingStatus, $startDate, $endDate);
            $insSub->execute();
            $insSub->close();

            $updUser = $conn->prepare("UPDATE praise_users SET subscription_status = 'active' WHERE id = ?");
            $updUser->bind_param("i", $targetUserId);
            $updUser->execute();
            $updUser->close();

            // If streaming plan, provision channel if not already existing
            if ($isStreaming) {
                $chanCheck = $conn->prepare("SELECT id FROM streaming_channels WHERE user_id = ? AND is_active = 1");
                $chanCheck->bind_param("i", $targetUserId);
                $chanCheck->execute();
                if ($chanCheck->get_result()->num_rows === 0) {
                    $chanArn = 'arn:aws:ivs:us-east-1:placeholder:channel/vpr_user_' . $targetUserId;
                    $streamKeyArn = 'arn:aws:ivs:us-east-1:placeholder:stream-key/' . bin2hex(random_bytes(16));
                    $ingest = 'rtmps://placeholder.global-contribute.live-video.net:443/app/';
                    $playback = 'https://placeholder.us-east-1.playback.live-video.net/api/video/v1/us-east-1.placeholder.channel.m3u8';
                    
                    $insChan = $conn->prepare("INSERT INTO streaming_channels (user_id, aws_channel_arn, aws_stream_key_arn, ingest_endpoint, playback_url) VALUES (?, ?, ?, ?, ?)");
                    $insChan->bind_param("issss", $targetUserId, $chanArn, $streamKeyArn, $ingest, $playback);
                    $insChan->execute();
                    $insChan->close();
                }
                $chanCheck->close();
            }

            vpr_log_payment_audit($conn, $txn['id'], $txnRef, $txn['user_id'], 'WEBHOOK_ACTIVATED', 'webhook', null, 'Subscription activated via webhook', [
                'end_date' => $endDate,
                'extended' => !empty($currentEndDate)
            ]);

            $conn->commit();
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Subscription activated successfully.']);
            exit;

        } catch (\Throwable $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Webhook DB transaction error.']);
            exit;
        }
    } else {
        // Mark failed
        $updTxn = $conn->prepare("UPDATE transactions SET status = 'failed', gateway_response = ?, updated_at = NOW() WHERE id = ?");
        $rawJson = json_encode($event);
        $updTxn->bind_param("si", $rawJson, $txn['id']);
        $updTxn->execute();
        $updTxn->close();

        vpr_log_payment_audit($conn, $txn['id'], $txnRef, $txn['user_id'], 'WEBHOOK_FAILED', 'webhook', null, 'Payment failed notification received from Squad.');
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Failed status recorded.']);
        exit;
    }
}

// ══════════════════════════════════════════════════════════════════════════
// 6. POST /subscriptions/manual — Bank proof upload (Preserved Fallback)
// ══════════════════════════════════════════════════════════════════════════
if ($method === 'POST' && $path === '/subscriptions/manual') {
    require_auth();
    $userId = $_SESSION['user_id'];
    
    $fullName = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $planCode = filter_input(INPUT_POST, 'plan', FILTER_SANITIZE_SPECIAL_CHARS);
    
    if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'No file uploaded or upload error.'], 400);
    }
    
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    
    $fileInfo = pathinfo($_FILES['proof']['name']);
    $extension = strtolower($fileInfo['extension'] ?? '');
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['proof']['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($extension, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
        json_response(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF are allowed.'], 400);
    }
    
    $safeFilename = bin2hex(random_bytes(16)) . '.' . $extension;
    $uploadDir = __DIR__ . '/../../uploads/proofs/';
    
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    
    $targetPath = $uploadDir . $safeFilename;
    
    if (move_uploaded_file($_FILES['proof']['tmp_name'], $targetPath)) {
        // Authoritative price lookup
        $amount = 3000.00;
        $pStmt = $conn->prepare("SELECT price FROM subscription_plans WHERE plan_code = ? LIMIT 1");
        if ($pStmt) {
            $pStmt->bind_param("s", $planCode);
            $pStmt->execute();
            if ($pRow = $pStmt->get_result()->fetch_assoc()) {
                $amount = (float)$pRow['price'];
            }
            $pStmt->close();
        }

        $txnRef = SquadService::generateReference('vpr_man_');
        
        $insertStmt = $conn->prepare("
            INSERT INTO transactions 
              (user_id, amount, proof_of_transaction, subscription_type, status, transaction_ref, gateway, created_at) 
            VALUES 
              (?, ?, ?, ?, 'pending', ?, 'manual', NOW())
        ");
        $insertStmt->bind_param("idsss", $userId, $amount, $safeFilename, $planCode, $txnRef);
        if ($insertStmt->execute()) {
            $insertId = $conn->insert_id;
            vpr_log_payment_audit($conn, $insertId, $txnRef, $userId, 'MANUAL_SUBMITTED', 'user', $userId, 'Manual proof of payment uploaded');
            json_response(['success' => true, 'message' => 'Proof uploaded successfully. Pending admin approval.']);
        } else {
            json_response(['success' => false, 'message' => 'Database error.'], 500);
        }
        $insertStmt->close();
    } else {
        json_response(['success' => false, 'message' => 'Failed to save uploaded file.'], 500);
    }
}
