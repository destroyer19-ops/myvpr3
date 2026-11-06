<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once __DIR__ . '/includes/session.php';
    session_init();
}

require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: salvation_gift.php');
    exit;
}

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = 'Invalid session. Please refresh and try again.';
    header('Location: salvation_gift.php');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_message'] = 'Please provide a valid name and email.';
    header('Location: salvation_gift.php');
    exit;
}

$ip_address = get_client_ip();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$stmt = $conn->prepare("INSERT INTO praise_salvation_gifts (full_name, email, ip_address, user_agent) VALUES (?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("ssss", $name, $email, $ip_address, $user_agent);
    if (!$stmt->execute()) {
        $_SESSION['error_message'] = 'Could not save your details. Please try again.';
        $stmt->close();
        header('Location: salvation_gift.php');
        exit;
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = 'Could not save your details. Please try again.';
    header('Location: salvation_gift.php');
    exit;
}

$file_path = __DIR__ . '/assets/Now-That-Your-Born-Again.pdf';
if (!file_exists($file_path)) {
    $_SESSION['error_message'] = 'Gift document not available. Please try again later.';
    header('Location: salvation_gift.php');
    exit;
}

$filename = 'Now-That-Your-Born-Again.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;
?>
