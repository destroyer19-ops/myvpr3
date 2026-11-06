<?php
ini_set("display_errors", 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once __DIR__ . '/includes/session.php';
    session_init();
}
header('Content-Type: application/json');

require_once __DIR__ . '/includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}
$user_id = $_SESSION['user_id'];

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid session. Please refresh and try again.']);
    exit;
}

// Get stream_key from POST request
$stream_key = isset($_POST['stream_key']) ? trim($_POST['stream_key']) : '';

if (empty($stream_key)) {
    echo json_encode(['success' => false, 'message' => 'Stream key is required.']);
    exit;
}

// Verify that the logged-in user is the creator of this shared stream
$stmt_check = $conn->prepare("SELECT id FROM praise_user_shared_streams WHERE stream_key = ? AND user_id = ?");
$stmt_check->bind_param("si", $stream_key, $user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized or stream not found.']);
    exit;
}
$stmt_check->close();

// Update the shared stream to set is_active to 0
$stmt_update = $conn->prepare("UPDATE praise_user_shared_streams SET is_active = 0 WHERE stream_key = ? AND user_id = ?");
$stmt_update->bind_param("si", $stream_key, $user_id);

if ($stmt_update->execute()) {
    echo json_encode(['success' => true, 'message' => 'Shared stream ended successfully.']);
} else {
    logDatabaseError("UPDATE praise_user_shared_streams SET is_active = 0 WHERE stream_key = ? AND user_id = ?", $stmt_update->error);
    echo json_encode(['success' => false, 'message' => 'Failed to end shared stream.']);
}

$stmt_update->close();
$conn->close();
?>
