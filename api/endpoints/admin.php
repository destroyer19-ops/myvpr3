<?php
// admin.php - Admin endpoints

function require_admin() {
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        json_response(['success' => false, 'message' => 'Admin access required.'], 403);
    }
}

global $conn;

// --- Admin Stats ---
if ($method === 'GET' && $path === '/admin/stats') {
    require_admin();
    $stats = [
        'totalUsers' => 0,
        'activeSubscriptions' => 0,
        'totalVideos' => 0,
        'liveStreams' => 0,
        'totalCrusades' => 0,
        'totalRevenue' => 0.00,
        'pendingPayments' => 0
    ];
    $res = $conn->query("SELECT COUNT(*) FROM praise_users");
    if ($res) $stats['totalUsers'] = (int)$res->fetch_row()[0];
    $res = $conn->query("SELECT COUNT(*) FROM subscriptions WHERE end_date > NOW()");
    if ($res) $stats['activeSubscriptions'] = (int)$res->fetch_row()[0];
    $res = $conn->query("SELECT COUNT(*) FROM praise_videos");
    if ($res) $stats['totalVideos'] = (int)$res->fetch_row()[0];
    $res = $conn->query("SELECT COUNT(*) FROM praise_live_tv WHERE is_live = 1");
    if ($res) $stats['liveStreams'] = (int)$res->fetch_row()[0];
    $res = $conn->query("SELECT COUNT(*) FROM praise_crusades WHERE is_active = 1");
    if ($res) $stats['totalCrusades'] = (int)$res->fetch_row()[0];
    $res = $conn->query("SELECT SUM(amount) FROM transactions WHERE status = 'approved'");
    if ($res) {
        $val = $res->fetch_row()[0];
        $stats['totalRevenue'] = $val !== null ? (float)$val : 0.00;
    }
    $res = $conn->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'");
    if ($res) $stats['pendingPayments'] = (int)$res->fetch_row()[0];
    json_response(['success' => true, 'data' => $stats]);
}

// --- Admin Videos ---
if ($method === 'GET' && $path === '/admin/videos') {
    require_admin();
    $videos = [];
    $result = $conn->query("SELECT id, title, video_url, language, category_children_outreach, category_movie_outreach, category_ministrations, created_at FROM praise_videos ORDER BY created_at DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) $videos[] = $row;
    }
    json_response(['success' => true, 'data' => $videos]);
}


// ── POST /admin/videos (upload video) ────────────────────────────────────────────────
if ($method === 'POST' && $path === '/admin/videos') {
    require_admin();
    $data = get_request_data();
    
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $language = trim($data['language'] ?? '');
    $video_url = trim($data['video_url'] ?? '');
    $cat_children = !empty($data['category_children_outreach']) ? 1 : 0;
    $cat_movie = !empty($data['category_movie_outreach']) ? 1 : 0;
    $cat_min = !empty($data['category_ministrations']) ? 1 : 0;

    if (empty($title) || empty($video_url)) {
        json_response(['success' => false, 'message' => 'Title and Video URL are required.'], 400);
    }

    $thumbnail_url = null;
    if (isset($_FILES['ecard']) && $_FILES['ecard']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../uploads/videos/';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
        
        $ext = pathinfo($_FILES['ecard']['name'], PATHINFO_EXTENSION);
        $safe_filename = 'thumb_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['ecard']['tmp_name'], $upload_dir . $safe_filename)) {
            $thumbnail_url = '/uploads/videos/' . $safe_filename;
        }
    }

    $stmt = $conn->prepare("INSERT INTO praise_videos (title, description, video_url, thumbnail_url, language, category_children_outreach, category_movie_outreach, category_ministrations, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssiii", $title, $description, $video_url, $thumbnail_url, $language, $cat_children, $cat_movie, $cat_min);
    
    if ($stmt->execute()) {
        json_response(['success' => true, 'message' => 'Video published successfully.']);
    } else {
        json_response(['success' => false, 'message' => 'Failed to publish video.'], 500);
    }
}

if ($method === 'DELETE' && preg_match('#^/admin/videos/(\\d+)$#', $path, $m)) {
    require_admin();
    require_csrf();
    $id = (int)$m[1];
    $stmt = $conn->prepare("DELETE FROM praise_videos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    json_response(['success' => true, 'message' => 'Video deleted.']);
}

// --- Admin Users ---
if ($method === 'GET' && $path === '/admin/users') {
    require_admin();
    $users = [];
    $result = $conn->query("SELECT id, username, email, account_type, subscription_status, created_at FROM praise_users ORDER BY created_at DESC LIMIT 200");
    if ($result) {
        while ($row = $result->fetch_assoc()) $users[] = $row;
    }
    json_response(['success' => true, 'data' => $users]);
}

// --- Admin Subscriptions ---
if ($method === 'GET' && $path === '/admin/subscriptions') {
    require_admin();

    $pending = [];
    $stmt = $conn->prepare("
        SELECT t.id, t.transaction_ref, t.amount, t.currency, t.gateway, t.channel,
               t.proof_of_transaction, t.subscription_type, t.created_at,
               pu.username AS user_username, pu.email AS user_email,
               gu.username AS gifted_username, gu.email AS gifted_email
        FROM transactions t
        JOIN praise_users pu ON t.user_id = pu.id
        LEFT JOIN praise_users gu ON t.gifted_user_id = gu.id
        WHERE t.status = 'pending'
        ORDER BY t.created_at DESC
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $pending[] = $row;
        $stmt->close();
    }

    $active = [];
    $stmt = $conn->prepare("
        SELECT s.id, s.subscription_type, s.plan_code, s.status, s.start_date, s.end_date,
               pu.id AS user_id, pu.username AS user_username, pu.email AS user_email
        FROM subscriptions s
        JOIN praise_users pu ON s.user_id = pu.id
        WHERE s.end_date > NOW() AND (s.status IS NULL OR s.status != 'cancelled')
        ORDER BY s.end_date ASC
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $active[] = $row;
        $stmt->close();
    }

    $expired = [];
    $stmt = $conn->prepare("
        SELECT s.id, s.subscription_type, s.plan_code, s.status, s.start_date, s.end_date,
               pu.id AS user_id, pu.username AS user_username, pu.email AS user_email
        FROM subscriptions s
        JOIN praise_users pu ON s.user_id = pu.id
        WHERE s.end_date <= NOW() OR s.status = 'cancelled'
        ORDER BY s.end_date DESC
        LIMIT 100
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $expired[] = $row;
        $stmt->close();
    }

    json_response(['success' => true, 'data' => compact('pending', 'active', 'expired')]);
}

// --- Admin Payment Overview & Metrics ---
if ($method === 'GET' && $path === '/admin/payments/overview') {
    require_admin();

    $metrics = [
        'totalSubscribers'   => 0,
        'activeSubscribers'  => 0,
        'pendingTransactions'=> 0,
        'expiredSubscribers' => 0,
        'totalRevenue'       => 0.00,
        'successCount'       => 0,
        'failedCount'        => 0,
        'planDistribution'   => []
    ];

    // Total distinct users who have ever had a subscription
    $res = $conn->query("SELECT COUNT(DISTINCT user_id) FROM subscriptions");
    if ($res) $metrics['totalSubscribers'] = (int)$res->fetch_row()[0];

    // Active subscriptions
    $res = $conn->query("SELECT COUNT(*) FROM subscriptions WHERE end_date > NOW() AND (status IS NULL OR status != 'cancelled')");
    if ($res) $metrics['activeSubscribers'] = (int)$res->fetch_row()[0];

    // Expired or cancelled subscriptions
    $res = $conn->query("SELECT COUNT(*) FROM subscriptions WHERE end_date <= NOW() OR status = 'cancelled'");
    if ($res) $metrics['expiredSubscribers'] = (int)$res->fetch_row()[0];

    // Pending transactions
    $res = $conn->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'");
    if ($res) $metrics['pendingTransactions'] = (int)$res->fetch_row()[0];

    // Total verified revenue (only approved transactions)
    $res = $conn->query("SELECT SUM(amount) FROM transactions WHERE status = 'approved'");
    if ($res) {
        $val = $res->fetch_row()[0];
        $metrics['totalRevenue'] = $val !== null ? (float)$val : 0.00;
    }

    // Success vs Failed payment counts
    $res = $conn->query("SELECT COUNT(*) FROM transactions WHERE status = 'approved'");
    if ($res) $metrics['successCount'] = (int)$res->fetch_row()[0];

    $res = $conn->query("SELECT COUNT(*) FROM transactions WHERE status IN ('failed', 'rejected')");
    if ($res) $metrics['failedCount'] = (int)$res->fetch_row()[0];

    // Distribution by plan
    $res = $conn->query("SELECT subscription_type, COUNT(*) as count FROM subscriptions GROUP BY subscription_type ORDER BY count DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $metrics['planDistribution'][] = [
                'plan'  => $row['subscription_type'],
                'count' => (int)$row['count']
            ];
        }
    }

    json_response(['success' => true, 'data' => $metrics]);
}

// --- Admin Filterable Transactions Table ---
if ($method === 'GET' && $path === '/admin/payments/transactions') {
    require_admin();

    $q = trim($_GET['q'] ?? '');
    $status = trim($_GET['status'] ?? 'all');
    $gateway = trim($_GET['gateway'] ?? 'all');
    $fromDate = trim($_GET['from_date'] ?? '');
    $toDate = trim($_GET['to_date'] ?? '');

    $where = ["1=1"];
    $types = "";
    $params = [];

    if ($q !== '') {
        $where[] = "(t.transaction_ref LIKE ? OR pu.username LIKE ? OR pu.email LIKE ? OR t.full_name LIKE ?)";
        $searchTerm = "%$q%";
        $types .= "ssss";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($status !== 'all' && in_array($status, ['approved', 'pending', 'rejected', 'failed'])) {
        $where[] = "t.status = ?";
        $types .= "s";
        $params[] = $status;
    }

    if ($gateway !== 'all' && in_array($gateway, ['squad', 'manual', 'espees'])) {
        $where[] = "t.gateway = ?";
        $types .= "s";
        $params[] = $gateway;
    }

    if ($fromDate !== '') {
        $where[] = "t.created_at >= ?";
        $types .= "s";
        $params[] = $fromDate . ' 00:00:00';
    }

    if ($toDate !== '') {
        $where[] = "t.created_at <= ?";
        $types .= "s";
        $params[] = $toDate . ' 23:59:59';
    }

    $whereSql = implode(" AND ", $where);
    $sql = "
        SELECT t.id, t.transaction_ref, t.amount, t.currency, t.gateway, t.channel,
               t.proof_of_transaction, t.subscription_type, t.status, t.created_at,
               t.gift_message,
               pu.id AS user_id, pu.username AS user_username, pu.email AS user_email,
               gu.username AS gifted_username, gu.email AS gifted_email
        FROM transactions t
        JOIN praise_users pu ON t.user_id = pu.id
        LEFT JOIN praise_users gu ON t.gifted_user_id = gu.id
        WHERE $whereSql
        ORDER BY t.created_at DESC
        LIMIT 200
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $transactions = [];
    while ($row = $res->fetch_assoc()) {
        $transactions[] = $row;
    }
    $stmt->close();

    json_response(['success' => true, 'data' => $transactions]);
}

// --- Admin Transaction Audit Trail ---
if ($method === 'GET' && preg_match('#^/admin/payments/transactions/(\d+)/audit$#', $path, $m)) {
    require_admin();
    $txnId = (int)$m[1];

    $logs = [];
    $stmt = $conn->prepare("SELECT id, transaction_id, transaction_ref, action, actor_type, actor_id, reason, payload, created_at FROM payment_audit_logs WHERE transaction_id = ? ORDER BY id ASC");
    if ($stmt) {
        $stmt->bind_param("i", $txnId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['payload'])) {
                $row['payload'] = json_decode($row['payload'], true);
            }
            $logs[] = $row;
        }
        $stmt->close();
    }

    json_response(['success' => true, 'data' => $logs]);
}

// --- Admin Payment Gateway Safe Configuration Inspection ---
if ($method === 'GET' && $path === '/admin/payments/config') {
    require_admin();

    $sqEnv = defined('SQUAD_ENV') ? SQUAD_ENV : 'sandbox';
    $sqSecret = defined('SQUAD_SECRET_KEY') ? SQUAD_SECRET_KEY : '';
    $sqPublic = defined('SQUAD_PUBLIC_KEY') ? SQUAD_PUBLIC_KEY : '';
    
    // Masked public key (first 10 chars and last 4)
    $maskedPk = (strlen($sqPublic) > 14)
        ? substr($sqPublic, 0, 10) . '...' . substr($sqPublic, -4)
        : ($sqPublic ? 'Configured' : 'Missing');

    json_response([
        'success' => true,
        'data' => [
            'gateway'              => 'Squad (HabariPay / GTCO)',
            'environment'          => $sqEnv,
            'is_sandbox'           => ($sqEnv === 'sandbox'),
            'secret_key_configured'=> !empty($sqSecret),
            'public_key_masked'    => $maskedPk,
            'webhook_configured'   => !empty($sqSecret),
            'currency'             => 'NGN',
            'supported_channels'   => ['Debit/Credit Card', 'Bank Transfer', 'USSD', 'Squad Bank Account'],
            'ip_whitelist'         => '18.133.63.109'
        ]
    ]);
}

// --- Admin Subscription Cancellation ---
if ($method === 'POST' && preg_match('#^/admin/subscriptions/(\d+)/cancel$#', $path, $m)) {
    require_admin();
    require_csrf();
    $subId = (int)$m[1];
    $data = get_request_data();
    $reason = trim($data['reason'] ?? 'Cancelled by administrator');
    $adminId = $_SESSION['user_id'] ?? null;

    // Fetch subscription
    $stmt = $conn->prepare("SELECT user_id, subscription_type FROM subscriptions WHERE id = ?");
    $stmt->bind_param("i", $subId);
    $stmt->execute();
    $sub = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sub) {
        json_response(['success' => false, 'message' => 'Subscription not found.'], 404);
    }

    // Cancel subscription
    $upd = $conn->prepare("UPDATE subscriptions SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $upd->bind_param("i", $subId);
    $upd->execute();
    $upd->close();

    // Check if user has any other active subscriptions
    $check = $conn->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = ? AND end_date > NOW() AND (status IS NULL OR status != 'cancelled')");
    $check->bind_param("i", $sub['user_id']);
    $check->execute();
    $hasOtherActive = ((int)$check->get_result()->fetch_row()[0] > 0);
    $check->close();

    if (!$hasOtherActive) {
        $updUser = $conn->prepare("UPDATE praise_users SET subscription_status = 'inactive' WHERE id = ?");
        $updUser->bind_param("i", $sub['user_id']);
        $updUser->execute();
        $updUser->close();
    }

    // Audit log
    $audit = $conn->prepare("INSERT INTO payment_audit_logs (user_id, action, actor_type, actor_id, reason, created_at) VALUES (?, 'ADMIN_CANCELLED', 'admin', ?, ?, NOW())");
    $audit->bind_param("iis", $sub['user_id'], $adminId, $reason);
    $audit->execute();
    $audit->close();

    json_response(['success' => true, 'message' => 'Subscription cancelled successfully.']);
}

// --- Admin Dynamic Subscription Plans CRUD ---
if ($method === 'GET' && $path === '/admin/subscription-plans') {
    require_admin();
    $plans = [];
    $res = $conn->query("SELECT id, plan_code, name, description, category, price, currency, interval_unit, interval_count, features_json, is_active, is_featured, badge_text, sort_order FROM subscription_plans ORDER BY sort_order ASC, id ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['features'] = !empty($row['features_json']) ? json_decode($row['features_json'], true) : [];
            $plans[] = $row;
        }
    }
    json_response(['success' => true, 'data' => $plans]);
}

if ($method === 'POST' && $path === '/admin/subscription-plans') {
    require_admin();
    require_csrf();
    $data = get_request_data();

    $code = trim($data['plan_code'] ?? '');
    $name = trim($data['name'] ?? '');
    $price = (float)($data['price'] ?? 0);
    $desc = trim($data['description'] ?? '');
    $category = trim($data['category'] ?? 'membership');
    $currency = trim($data['currency'] ?? 'NGN');
    $unit = trim($data['interval_unit'] ?? 'month');
    $count = (int)($data['interval_count'] ?? 1);
    $badge = trim($data['badge_text'] ?? '');
    $isFeatured = !empty($data['is_featured']) ? 1 : 0;
    $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $featuresJson = is_array($data['features'] ?? null) ? json_encode($data['features']) : json_encode([]);

    if ($code === '' || $name === '' || $price < 0) {
        json_response(['success' => false, 'message' => 'Plan code, name, and valid price are required.'], 400);
    }

    $stmt = $conn->prepare("INSERT INTO subscription_plans (plan_code, name, description, category, price, currency, interval_unit, interval_count, features_json, is_active, is_featured, badge_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssdssisiis", $code, $name, $desc, $category, $price, $currency, $unit, $count, $featuresJson, $isActive, $isFeatured, $badge);
    if ($stmt->execute()) {
        json_response(['success' => true, 'message' => 'Subscription plan created.', 'id' => $conn->insert_id], 201);
    } else {
        json_response(['success' => false, 'message' => 'Failed to create plan: ' . $stmt->error], 500);
    }
}

if ($method === 'PUT' && preg_match('#^/admin/subscription-plans/(\d+)$#', $path, $m)) {
    require_admin();
    require_csrf();
    $id = (int)$m[1];
    $data = get_request_data();

    $name = trim($data['name'] ?? '');
    $price = (float)($data['price'] ?? 0);
    $desc = trim($data['description'] ?? '');
    $badge = trim($data['badge_text'] ?? '');
    $isFeatured = !empty($data['is_featured']) ? 1 : 0;
    $featuresJson = is_array($data['features'] ?? null) ? json_encode($data['features']) : null;

    $stmt = $conn->prepare("UPDATE subscription_plans SET name = ?, price = ?, description = ?, badge_text = ?, is_featured = ?" . ($featuresJson ? ", features_json = ?" : "") . " WHERE id = ?");
    if ($featuresJson) {
        $stmt->bind_param("sdssisi", $name, $price, $desc, $badge, $isFeatured, $featuresJson, $id);
    } else {
        $stmt->bind_param("sdssii", $name, $price, $desc, $badge, $isFeatured, $id);
    }
    if ($stmt->execute()) {
        json_response(['success' => true, 'message' => 'Plan updated successfully.']);
    } else {
        json_response(['success' => false, 'message' => 'Failed to update plan.'], 500);
    }
}

if ($method === 'POST' && preg_match('#^/admin/subscription-plans/(\d+)/toggle$#', $path, $m)) {
    require_admin();
    require_csrf();
    $id = (int)$m[1];
    $stmt = $conn->prepare("UPDATE subscription_plans SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    json_response(['success' => true, 'message' => 'Plan visibility toggled.']);
}


if ($method === 'POST' && preg_match('#^/admin/subscriptions/(\d+)/approve$#', $path, $m)) {
    require_admin();
    require_csrf();
    $transaction_id = (int)$m[1];

    // Fetch the transaction
    $stmt = $conn->prepare("SELECT user_id, gifted_user_id, subscription_type FROM transactions WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $txn = $result->fetch_assoc();
    $stmt->close();

    if (!$txn) {
        json_response(['success' => false, 'message' => 'Transaction not found or already processed.'], 404);
    }

    $target_user_id = !empty($txn['gifted_user_id']) ? $txn['gifted_user_id'] : $txn['user_id'];
    $type = $txn['subscription_type'];
    $duration_map = ['daily' => '+1 day', 'monthly' => '+1 month'];
    $duration = $duration_map[$type] ?? '+1 month';

    $start = date('Y-m-d H:i:s');
    $end = date('Y-m-d H:i:s', strtotime($duration));

    if (strpos($type, 'streaming_plan_') === 0) {
        $plan_id = (int)str_replace('streaming_plan_', '', $type);
        
        $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, subscription_type, streaming_plan_id, streaming_status, start_date, end_date) VALUES (?, 'streaming', ?, 'active', ?, ?)");
        $stmt->bind_param("iiss", $target_user_id, $plan_id, $start, $end);
        $stmt->execute();
        $stmt->close();
        
        // Provision a real channel if one doesn't exist
        $stmt = $conn->prepare("SELECT id FROM streaming_channels WHERE user_id = ? AND is_active = 1");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows == 0) {
            $stmt->close();
            
            $aws_key = getenv('AWS_ACCESS_KEY_ID');
            $aws_secret = getenv('AWS_SECRET_ACCESS_KEY');
            
            if (!$aws_key || !$aws_secret) {
                json_response(['success' => false, 'message' => 'AWS credentials missing. Cannot provision channel.'], 500);
            }
            
            try {
                if (!class_exists('Aws\Ivs\IvsClient')) {
                    require_once __DIR__ . '/../vendor/autoload.php';
                }

                $ivsClient = new Aws\Ivs\IvsClient([
                    'version' => 'latest',
                    'region'  => 'us-east-1',
                    'credentials' => [
                        'key'    => $aws_key,
                        'secret' => $aws_secret,
                    ]
                ]);

                $result = $ivsClient->createChannel([
                    'name' => 'vpr_user_' . $target_user_id,
                    'type' => 'STANDARD',
                    'latencyMode' => 'LOW', 
                    'authorized' => false
                ]);

                $channel = $result['channel'];
                $streamKey = $result['streamKey'];
                
                $insert_chan = $conn->prepare("INSERT INTO streaming_channels (user_id, aws_channel_arn, aws_stream_key_arn, ingest_endpoint, playback_url) VALUES (?, ?, ?, ?, ?)");
                $insert_chan->bind_param("issss", $target_user_id, $channel['arn'], $streamKey['value'], $channel['ingestEndpoint'], $channel['playbackUrl']);
                $insert_chan->execute();
                $insert_chan->close();
            } catch (Exception $e) {
                error_log('AWS IVS Provision Error: ' . $e->getMessage());
                json_response(['success' => false, 'message' => 'Failed to provision AWS channel.'], 500);
            }
        } else {
            $stmt->close();
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, subscription_type, start_date, end_date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $target_user_id, $type, $start, $end);
        $stmt->execute();
        $stmt->close();
    }

    // Update user subscription_status
    $stmt = $conn->prepare("UPDATE praise_users SET subscription_status = 'active' WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $stmt->close();

    // Mark transaction as approved
    $stmt = $conn->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $stmt->close();

    json_response(['success' => true, 'message' => 'Subscription approved.']);
}

if ($method === 'POST' && preg_match('#^/admin/subscriptions/(\d+)/reject$#', $path, $m)) {
    require_admin();
    require_csrf();
    $transaction_id = (int)$m[1];
    $stmt = $conn->prepare("UPDATE transactions SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $stmt->close();
    json_response(['success' => true, 'message' => 'Subscription rejected.']);
}

// --- Admin Streams ---
if ($method === 'GET' && $path === '/admin/streams') {
    require_admin();
    $streams = [];
    $result = $conn->query("SELECT id, title, description, stream_url, is_live, created_at FROM praise_live_tv ORDER BY created_at DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) $streams[] = $row;
    }
    json_response(['success' => true, 'data' => $streams]);
}

if ($method === 'POST' && $path === '/admin/streams') {
    require_admin();
    require_csrf();
    $data = get_request_data();
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $stream_url = trim($data['stream_url'] ?? '');
    $is_live = (int)($data['is_live'] ?? 0);

    if (!$title || !$stream_url) {
        json_response(['success' => false, 'message' => 'Title and stream URL are required.'], 422);
    }

    $stmt = $conn->prepare("INSERT INTO praise_live_tv (title, description, stream_url, is_live, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssi", $title, $description, $stream_url, $is_live);
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();
    json_response(['success' => true, 'message' => 'Stream created.', 'id' => $new_id], 201);
}

if ($method === 'PUT' && preg_match('#^/admin/streams/(\d+)$#', $path, $m)) {
    require_admin();
    require_csrf();
    $id = (int)$m[1];
    $data = get_request_data();
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $stream_url = trim($data['stream_url'] ?? '');
    $is_live = (int)($data['is_live'] ?? 0);

    $stmt = $conn->prepare("UPDATE praise_live_tv SET title=?, description=?, stream_url=?, is_live=? WHERE id=?");
    $stmt->bind_param("sssii", $title, $description, $stream_url, $is_live, $id);
    $stmt->execute();
    $stmt->close();
    json_response(['success' => true, 'message' => 'Stream updated.']);
}

if ($method === 'DELETE' && preg_match('#^/admin/streams/(\d+)$#', $path, $m)) {
    require_admin();
    require_csrf();
    $id = (int)$m[1];
    $stmt = $conn->prepare("DELETE FROM praise_live_tv WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    json_response(['success' => true, 'message' => 'Stream deleted.']);
}

if ($method === 'POST' && preg_match('#^/admin/streams/(\d+)/toggle$#', $path, $m)) {
    require_admin();
    require_csrf();
    $id = (int)$m[1];
    $stmt = $conn->prepare("UPDATE praise_live_tv SET is_live = 1 - is_live WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    json_response(['success' => true, 'message' => 'Stream toggled.']);
}

// --- Admin Crusades ---
if ($method === 'GET' && $path === '/admin/crusades') {
    require_admin();
    $crusades = [];
    $result = $conn->query("
        SELECT c.id, c.title, c.crusade_code, c.is_active, c.start_time, c.created_at,
               u.username AS owner,
               COUNT(s.id) AS total_views
        FROM praise_crusades c
        LEFT JOIN praise_users u ON c.user_id = u.id
        LEFT JOIN praise_crusade_stats s ON c.id = s.crusade_id
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) $crusades[] = $row;
    }
    json_response(['success' => true, 'data' => $crusades]);
}

// --- Admin Salvations ---
if ($method === 'GET' && $path === '/admin/salvations') {
    require_admin();
    $decisions = [];
    $result = $conn->query("
        SELECT psc.id, psc.ip_address, psc.created_at,
               pc.title AS crusade_title
        FROM praise_salvation_clicks psc
        LEFT JOIN praise_crusades pc ON psc.crusade_id = pc.id
        ORDER BY psc.created_at DESC
        LIMIT 500
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) $decisions[] = $row;
    }
    $total = count($decisions);
    // For accurate total count
    $res = $conn->query("SELECT COUNT(*) FROM praise_salvation_clicks");
    if ($res) $total = (int)$res->fetch_row()[0];

    json_response(['success' => true, 'data' => $decisions, 'total' => $total]);
}

// --- Admin CMS Banners (stub for future DB table) ---
if ($method === 'GET' && $path === '/admin/cms/banners') {
    require_admin();
    json_response(['success' => true, 'data' => []]);
}

if ($method === 'POST' && $path === '/admin/cms/banners') {
    require_admin();
    require_csrf();
    json_response(['success' => true, 'message' => 'Banner created (stub). CMS table not yet migrated.'], 201);
}

if ($method === 'DELETE' && preg_match('#^/admin/cms/banners/(\d+)$#', $path, $m)) {
    require_admin();
    require_csrf();
    json_response(['success' => true, 'message' => 'Banner deleted (stub).']);
}

// --- Admin Studio Plans ---
if ($method === 'GET' && $path === '/admin/streaming-plans') {
    require_admin();
    $plans = [];
    $result = $conn->query("SELECT id, name, description, price, billing_period, max_duration_hours, max_viewers, resolution, is_active FROM streaming_plans ORDER BY id DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $plans[] = $row;
        }
    }
    json_response(['success' => true, 'data' => $plans]);
}

if ($method === 'POST' && $path === '/admin/streaming-plans') {
    require_admin();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['name']) || !isset($data['price'])) {
        json_response(['success' => false, 'message' => 'Missing required fields'], 400);
    }
    
    $name = trim($data['name']);
    $desc = trim($data['description'] ?? '');
    $price = (float)($data['price']);
    $billing_period = trim($data['billing_period'] ?? 'month');
    $resolution = trim($data['resolution'] ?? '1080p');
    $max_duration = isset($data['max_duration_hours']) && $data['max_duration_hours'] !== '' ? (int)$data['max_duration_hours'] : null;
    $max_viewers = isset($data['max_viewers']) && $data['max_viewers'] !== '' ? (int)$data['max_viewers'] : null;
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;

    $stmt = $conn->prepare("INSERT INTO streaming_plans (name, description, price, billing_period, resolution, max_duration_hours, max_viewers, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        json_response(['success' => false, 'message' => 'Database prepare error: ' . $conn->error], 500);
    }
    $stmt->bind_param("ssdssiii", $name, $desc, $price, $billing_period, $resolution, $max_duration, $max_viewers, $is_active);
    
    if ($stmt->execute()) {
        json_response(['success' => true, 'message' => 'Plan added successfully', 'id' => $conn->insert_id]);
    } else {
        json_response(['success' => false, 'message' => 'Database error: ' . $stmt->error], 500);
    }
}

if ($method === 'PUT' && preg_match('#^/admin/streaming-plans/(\d+)$#', $path, $m)) {
    require_admin();
    $id = (int)$m[1];
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['name']) || !isset($data['price'])) {
        json_response(['success' => false, 'message' => 'Missing required fields'], 400);
    }
    
    $name = trim($data['name']);
    $desc = trim($data['description'] ?? '');
    $price = (float)($data['price']);
    $billing_period = trim($data['billing_period'] ?? 'month');
    $resolution = trim($data['resolution'] ?? '1080p');
    $max_duration = isset($data['max_duration_hours']) && $data['max_duration_hours'] !== '' ? (int)$data['max_duration_hours'] : null;
    $max_viewers = isset($data['max_viewers']) && $data['max_viewers'] !== '' ? (int)$data['max_viewers'] : null;
    
    $stmt = $conn->prepare("UPDATE streaming_plans SET name=?, description=?, price=?, billing_period=?, resolution=?, max_duration_hours=?, max_viewers=? WHERE id=?");
    if (!$stmt) {
        json_response(['success' => false, 'message' => 'Database prepare error: ' . $conn->error], 500);
    }
    $stmt->bind_param("ssdssiii", $name, $desc, $price, $billing_period, $resolution, $max_duration, $max_viewers, $id);
    
    if ($stmt->execute()) {
        json_response(['success' => true, 'message' => 'Plan updated successfully']);
    } else {
        json_response(['success' => false, 'message' => 'Database error: ' . $stmt->error], 500);
    }
}

if ($method === 'DELETE' && preg_match('#^/admin/streaming-plans/(\d+)$#', $path, $m)) {
    require_admin();
    $id = (int)$m[1];
    $stmt = $conn->prepare("DELETE FROM streaming_plans WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        json_response(['success' => true, 'message' => 'Plan deleted']);
    } else {
        json_response(['success' => false, 'message' => 'Database error']);
    }
}

if ($method === 'POST' && preg_match('#^/admin/streaming-plans/(\d+)/toggle$#', $path, $m)) {
    require_admin();
    $id = (int)$m[1];
    $stmt = $conn->prepare("UPDATE streaming_plans SET is_active = NOT is_active WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        json_response(['success' => true, 'message' => 'Plan status toggled']);
    } else {
        json_response(['success' => false, 'message' => 'Database error']);
    }
}
