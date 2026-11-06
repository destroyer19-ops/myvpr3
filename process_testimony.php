<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = "Invalid session. Please refresh and try again.";
        $_SESSION['message_type'] = "danger";
        header('Location: share_testimony.php');
        exit;
    }
    $user_id = $_SESSION['user_id']; // Corrected for security
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $testimony_text = trim($_POST['testimony_text'] ?? '');

    // Basic Validation
    if (empty($full_name) || empty($email) || empty($testimony_text)) {
        $_SESSION['message'] = "Please fill in all required fields.";
        $_SESSION['message_type'] = "danger";
        header('Location: share_testimony.php');
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = "Please enter a valid email address.";
        $_SESSION['message_type'] = "danger";
        header('Location: share_testimony.php');
        exit;
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO praise_testimonies (user_id, full_name, email, country, testimony_text, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("issss", $user_id, $full_name, $email, $country, $testimony_text);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Your testimony has been submitted and is awaiting approval. Thank you for sharing!";
        $_SESSION['message_type'] = "success";
        header('Location: testimony_thanks.php'); // Redirect to a thanks page
        exit;
    } else {
        $_SESSION['message'] = "Error submitting your testimony: " . $conn->error;
        $_SESSION['message_type'] = "danger";
        header('Location: share_testimony.php');
        exit;
    }
    $stmt->close();
} else {
    header('Location: share_testimony.php');
    exit;
}

$conn->close();
?>
