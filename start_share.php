<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once __DIR__ . '/includes/session.php';
    session_init();
}
require_once __DIR__ . '/includes/db.php';

// 1. User Authentication Check
if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['crusade_code'])) {
        $_SESSION['pending_share_crusade_code'] = $_POST['crusade_code'];
    }
    $_SESSION['redirect_after_login'] = 'crusade_room.php?code=' . urlencode($_POST['crusade_code'] ?? '');
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// Check user's subscription status
$stmt = $conn->prepare("SELECT end_date FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$subscription = $result->fetch_assoc();
$stmt->close();

$is_subscribed = false;
if ($subscription) {
    $end_date = new DateTime($subscription['end_date']);
    $now = new DateTime();
    if ($end_date > $now) {
        $is_subscribed = true;
    }
}

// Update the user's subscription_status in praise_users table for consistency
$new_status = $is_subscribed ? 'active' : 'inactive';
$stmt_update = $conn->prepare("UPDATE praise_users SET subscription_status = ? WHERE id = ?");
$stmt_update->bind_param("si", $new_status, $user_id);
$stmt_update->execute();
$stmt_update->close();


if (!$is_subscribed) {
    header('Location: subscribe.php?error=not_subscribed');
    exit;
}

// 2. Require POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = 'Invalid session. Please refresh and try again.';
    header('Location: dashboard.php');
    exit;
}

// 3. Get crusade_code from POST
if (empty($_POST['crusade_code'])) {
    $_SESSION['error_message'] = 'Crusade code is required.';
    header('Location: dashboard.php');
    exit;
}
$crusade_code = $_POST['crusade_code'];

// 4. Validate crusade_code and get crusade_id
$stmt = $conn->prepare("SELECT id, is_active FROM praise_crusades WHERE crusade_code = ?");
$stmt->bind_param("s", $crusade_code);
$stmt->execute();
$result = $stmt->get_result();
$crusade = $result->fetch_assoc();
$stmt->close();

if (!$crusade) {
    $_SESSION['error_message'] = 'Invalid crusade code. Cannot create a share link for a non-existent crusade.';
    header('Location: dashboard.php');
    exit;
}

if ((int)$crusade['is_active'] !== 1) {
    $_SESSION['error_message'] = 'This crusade is not active.';
    header('Location: crusade_room.php?code=' . urlencode($crusade_code));
    exit;
}
$crusade_id = $crusade['id'];

// 5. Reuse existing active share if present
$stmt_existing = $conn->prepare("SELECT stream_key FROM praise_user_shared_streams WHERE user_id = ? AND crusade_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
$stmt_existing->bind_param("ii", $user_id, $crusade_id);
$stmt_existing->execute();
$result_existing = $stmt_existing->get_result();
$existing = $result_existing->fetch_assoc();
$stmt_existing->close();

if ($existing && !empty($existing['stream_key'])) {
    header('Location: watch.php?stream_key=' . $existing['stream_key'] . '&shared=existing');
    exit;
}

// 6. Generate a Unique stream_key
do {
    $stream_key = bin2hex(random_bytes(16));
    $stmt_check = $conn->prepare("SELECT id FROM praise_user_shared_streams WHERE stream_key = ?");
    $stmt_check->bind_param("s", $stream_key);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
} while ($result_check->num_rows > 0);
$stmt_check->close();

// 7. Insert into the database
$stmt_insert = $conn->prepare(
    "INSERT INTO praise_user_shared_streams (user_id, crusade_id, stream_key) VALUES (?, ?, ?)"
);
$stmt_insert->bind_param("iis", $user_id, $crusade_id, $stream_key);

if (!$stmt_insert->execute()) {
    // A more robust solution could find their existing key and redirect.
    die("Error creating share link: " . $stmt_insert->error);
}
$stmt_insert->close();

// 8. Redirect the user
header('Location: watch.php?stream_key=' . $stream_key . '&shared=created');
exit;
