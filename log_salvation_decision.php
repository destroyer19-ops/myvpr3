<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}
require_once 'includes/db.php';

$crusade_code = isset($_GET['crusade_code']) ? $_GET['crusade_code'] : null;

if ($crusade_code) {
    // Get crusade_id from crusade_code
    $stmt = $conn->prepare("SELECT id FROM praise_crusades WHERE crusade_code = ?");
    $stmt->bind_param("s", $crusade_code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $crusade_id = $row['id'];
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        // Log the decision
        $stmt_log = $conn->prepare("INSERT INTO praise_salvation_clicks (crusade_id, user_id, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt_log->bind_param("isss", $crusade_id, $user_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        $stmt_log->execute();
        $stmt_log->close();
    }
    $stmt->close();
}

// Redirect to a thank you page or back to the crusade
header("Location: salvation_gift.php?crusade_code=" . $crusade_code);
exit;
?>
