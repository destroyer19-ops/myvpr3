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
$meeting_id = isset($_POST['meeting_id']) ? intval($_POST['meeting_id']) : 0;
$duration = isset($_POST['duration']) ? intval($_POST['duration']) : 30;

// Validate meeting ID
if (empty($meeting_id)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Meeting ID is required']);
    exit;
}

// Check if the meeting exists
$stmt = $conn->prepare("SELECT id FROM praise_meetings WHERE id = ?");
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid meeting ID']);
    exit;
}

// Insert meeting session into database
$stmt = $conn->prepare("INSERT INTO praise_meeting_sessions (meeting_id, user_id, start_time, duration) VALUES (?, ?, NOW(), ?)");
$stmt->bind_param("iii", $meeting_id, $user_id, $duration);

if ($stmt->execute()) {
    $session_id = $stmt->insert_id;
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'meeting_id' => $meeting_id,
        'duration' => $duration
    ]);
} else {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to start meeting session: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>
