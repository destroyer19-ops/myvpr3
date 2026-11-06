<?php
ini_set("display_errors", 1);
require_once 'includes/session.php';
session_init();

// Check if user or admin is logged in
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!isset($_SESSION['user_id']) && !$is_admin) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Get database connection
require_once 'includes/db.php';

// Resolve user_id for meeting attribution
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id === 0 && $is_admin) {
    $admin_username = $_SESSION['admin_username'] ?? 'admin';
    $stmt_u = $conn->prepare("SELECT id FROM praise_users WHERE username = ? LIMIT 1");
    $stmt_u->bind_param("s", $admin_username);
    $stmt_u->execute();
    $res_u = $stmt_u->get_result();
    if ($row_u = $res_u->fetch_assoc()) {
        $user_id = $row_u['id'];
    } else {
        $res_f = $conn->query("SELECT id FROM praise_users ORDER BY id ASC LIMIT 1");
        if ($row_f = $res_f->fetch_assoc()) {
            $user_id = $row_f['id'];
        }
    }
    $stmt_u->close();
}

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid session. Please refresh and try again.']);
    exit;
}

// Get request parameters
$meeting_id = isset($_POST['meeting_id']) ? trim($_POST['meeting_id']) : '';

// Validate meeting ID
if (empty($meeting_id)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Meeting ID is required']);
    exit;
}

// Find the meeting ID from the meeting code
$stmt = $conn->prepare("SELECT id FROM praise_meetings WHERE meeting_code = ?");
$stmt->bind_param("s", $meeting_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid meeting ID']);
    exit;
}

$meeting = $result->fetch_assoc();
$meeting_db_id = $meeting['id'];

// Update the latest meeting session for this meeting and user
$stmt = $conn->prepare("
    UPDATE praise_meeting_sessions 
    SET end_time = NOW() 
    WHERE meeting_id = ? AND user_id = ? AND end_time IS NULL 
    ORDER BY start_time DESC 
    LIMIT 1
");
$stmt->bind_param("ii", $meeting_db_id, $user_id);

if ($stmt->execute()) {
    $response = [
        'success' => true,
        'message' => 'Meeting session ended successfully'
    ];
} else {
    $response = [
        'success' => false,
        'message' => 'Failed to end meeting session: ' . $conn->error
    ];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
