<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once '../includes/session.php'; // Adjust path for API folder
    session_init();
}

header('Content-Type: application/json'); // Set header for JSON response

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once '../includes/db.php'; // Adjust path for API folder

// CSRF token validation
if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid session. Please refresh and try again.']);
    exit;
}

$crusade_code = isset($_POST['crusade_code']) ? $_POST['crusade_code'] : '';

if (empty($crusade_code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid crusade code provided.']);
    exit;
}

// Verify that the logged-in user is the owner of the crusade
$stmt = $conn->prepare("SELECT id, user_id FROM praise_crusades WHERE crusade_code = ?");
$stmt->bind_param("s", $crusade_code);
$stmt->execute();
$result = $stmt->get_result();
$crusade = $result->fetch_assoc();
$stmt->close();

if (!$crusade || $crusade['user_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not authorized to delete this crusade.']);
    exit;
}

// Delete the crusade
$stmt_delete = $conn->prepare("DELETE FROM praise_crusades WHERE id = ?");
$stmt_delete->bind_param("i", $crusade['id']);
if ($stmt_delete->execute()) {
    echo json_encode(['success' => true, 'message' => 'Crusade deleted successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete crusade: ' . $conn->error]);
}
$stmt_delete->close();
exit;
