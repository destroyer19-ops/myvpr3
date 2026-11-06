<?php
ini_set("display_errors", 1);
require_once 'includes/session.php';
session_init();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Get database connection
require_once 'includes/db.php';

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid session. Please refresh and try again.']);
    exit;
}

// Check user's subscription status
$user_id = $_SESSION['user_id'];
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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You need an active subscription to create a meeting.']);
    exit;
}

// Get request parameters
$title = isset($_POST['title']) ? $_POST['title'] : 'Untitled Meeting';
$duration = isset($_POST['duration']) ? intval($_POST['duration']) : 30;
$user_id = $_SESSION['user_id'];

// Generate a unique meeting code (8 characters)
function generateMeetingCode($length = 8) {
    // Generate a more secure random string
    return substr(bin2hex(random_bytes(ceil($length / 2))), 0, $length);
}

// Generate a unique meeting code
$meeting_code = generateMeetingCode();

// Check if code already exists (with a limit to prevent infinite loops)
$max_attempts = 10;
$attempt = 0;

$stmt = $conn->prepare("SELECT id FROM praise_meetings WHERE meeting_code = ?");
$stmt->bind_param("s", $meeting_code);

while ($attempt < $max_attempts) {
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        // Code is unique
        break;
    }
    
    // If code exists, generate a new one
    $meeting_code = generateMeetingCode();
    $stmt->bind_param("s", $meeting_code);
    $attempt++;
}

if ($attempt >= $max_attempts) {
    // If we still couldn't find a unique code, return an error
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to generate a unique meeting code.']);
    exit;
}

// Insert meeting into database
$stmt = $conn->prepare("INSERT INTO praise_meetings (user_id, title, meeting_code, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iss", $user_id, $title, $meeting_code);

if ($stmt->execute()) {
    $meeting_id = $stmt->insert_id;
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'meeting_id' => $meeting_id,
        'meeting_code' => $meeting_code,
        'title' => $title
    ]);
} else {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create meeting: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>
