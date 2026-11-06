<?php
ini_set("display_errors", 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once __DIR__ . '/../includes/session.php';
    session_init();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

if (isset($_GET['id']) && isset($_GET['action'])) {
    $transaction_id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        // Get transaction details
        $stmt = $conn->prepare("SELECT user_id, gifted_user_id, subscription_type FROM transactions WHERE id = ?");
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();
        $stmt->close();

        if ($transaction) {
            $user_id = $transaction['user_id'];
            $gifted_user_id = $transaction['gifted_user_id'] ?? null;
            $target_user_id = !empty($gifted_user_id) ? (int)$gifted_user_id : (int)$user_id;
            $subscription_type = $transaction['subscription_type'];

            // Update transaction status
            $stmt = $conn->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?");
            $stmt->bind_param("i", $transaction_id);
            $stmt->execute();
            $stmt->close();

            // Update user's subscription status
            $stmt = $conn->prepare("UPDATE praise_users SET subscription_status = 'active' WHERE id = ?");
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            $stmt->close();

            // Add to subscriptions table
            $start_date = date('Y-m-d H:i:s');
            if ($subscription_type === 'daily') {
                $end_date = date('Y-m-d H:i:s', strtotime('+1 day'));
            } elseif ($subscription_type === 'monthly') {
                $end_date = date('Y-m-d H:i:s', strtotime('+1 month'));
            }

            $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, subscription_type, start_date, end_date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $target_user_id, $subscription_type, $start_date, $end_date);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($action === 'reject') {
        // Update transaction status
        $stmt = $conn->prepare("UPDATE transactions SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: manage_subscriptions.php');
exit;
?>
