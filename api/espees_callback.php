<?php
/**
 * api/espees_callback.php
 * Finalizes subscription after successful Espees payment
 */
require_once '../includes/session.php';
session_init();
require_once '../includes/db.php';
require_once '../includes/config.php';

// Espees redirects with query parameters
$payment_ref = $_GET['payment_ref'] ?? null;

if (!$payment_ref) {
    die("Error: No payment reference received.");
}

// 1. Verify the payment (Step 3: Confirm Payment)
$confirm_url = "https://api.espees.org/v2/payment/confirm/";
$payload = [
    'payment_ref' => $payment_ref
];

$ch = curl_init($confirm_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "x-api-key: " . ESPEES_API_KEY
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

// Check if transaction_status is 'APPROVED' as per docs
if ($http_code === 200 && ($result['transaction_status'] ?? '') === 'APPROVED') {
    // Payment Verified!
    $user_id = $_SESSION['user_id'] ?? null;
    $subscription_type = $_SESSION['pending_espees_plan'] ?? 'daily';
    
    if (!$user_id) {
        die("Error: Session expired. Payment Ref: $payment_ref confirmed.");
    }

    // 2. Mark as approved in DB
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, proof_of_transaction, subscription_type, status) VALUES (?, ?, ?, ?, 'approved')");
    $amount = (float)($result['price'] ?? 0);
    $proof = "Espees Ref: $payment_ref";
    $stmt->bind_param("idss", $user_id, $amount, $proof, $subscription_type);
    $stmt->execute();
    $stmt->close();

    // 3. Activate Subscription
    $stmt = $conn->prepare("UPDATE praise_users SET subscription_status = 'active' WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $start_date = date('Y-m-d H:i:s');
    $end_date = ($subscription_type === 'daily') ? date('Y-m-d H:i:s', strtotime('+1 day')) : date('Y-m-d H:i:s', strtotime('+1 month'));

    $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, subscription_type, start_date, end_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $subscription_type, $start_date, $end_date);
    $stmt->execute();
    $stmt->close();

    unset($_SESSION['pending_espees_plan']);
    
    header("Location: ../dashboard.php?payment=success");
    exit;
} else {
    // Payment failed or was rejected
    header("Location: ../subscribe.php?error=payment_failed&status=" . ($result['transaction_status'] ?? 'unknown'));
    exit;
}
