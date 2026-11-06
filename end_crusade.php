<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}

header('Content-Type: application/json'); // Set header for JSON response

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once 'includes/db.php';

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
    echo json_encode(['success' => false, 'message' => 'You are not authorized to end this crusade.']);
    exit;
}

// Update the crusade status to inactive
$stmt_update = $conn->prepare("UPDATE praise_crusades SET is_active = 0 WHERE id = ?");
$stmt_update->bind_param("i", $crusade['id']);
$stmt_update->execute();
$stmt_update->close();

// Clear the current crusade from the session
unset($_SESSION['current_crusade_code']);

echo json_encode(['success' => true, 'message' => 'Crusade ended successfully.']);
exit;
?>
