<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}

if (isset($_SESSION['user_id'])) {
    require_once 'includes/db.php';
    $stmt = $conn->prepare("UPDATE praise_users SET remember_selector = NULL, remember_token = NULL, token_expiry = NULL WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
}

if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to home page
header("Location: index.php");
exit;
?>
