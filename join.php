<?php
ini_set("display_errors", 1);
require_once 'includes/session.php';
session_init();

require_once 'includes/db.php';

// Get the meeting code from the URL
$meeting_code = isset($_GET['code']) ? trim($_GET['code']) : '';

// If no code is provided, redirect to the home page
if (empty($meeting_code)) {
    header('Location: index.php');
    exit;
}

// Fetch meeting details
$stmt = $conn->prepare("SELECT id, title, daily_room_url FROM praise_meetings WHERE meeting_code = ?");
$stmt->bind_param("s", $meeting_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // If the meeting code is invalid, redirect to an error page or show a message
    die('Invalid meeting code.');
}

$meeting = $result->fetch_assoc();
$meeting_id = $meeting['id'];
$meeting_title = $meeting['title'];
$is_daily = !empty($meeting['daily_room_url']);

// If the user is not logged in, redirect to the login page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = "join.php?code=$meeting_code";
    header('Location: login.php');
    exit;
}

// Fetch participants
$participants_stmt = $conn->prepare("SELECT u.username FROM praise_meeting_sessions s JOIN praise_users u ON s.user_id = u.id WHERE s.meeting_id = ? AND s.end_time IS NULL");
$participants_stmt->bind_param("i", $meeting_id);
$participants_stmt->execute();
$participants_result = $participants_stmt->get_result();

$participants = [];
while ($row = $participants_result->fetch_assoc()) {
    $participants[] = $row['username'];
}

// If the user is logged in, show the join page
?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="join-page-container">
        <div class="join-container">
            <div class="card">
                <div class="card-header">
                    <h3><?php echo htmlspecialchars($meeting_title); ?></h3>
                </div>
                <div class="card-body">
                    <p>You are about to join the meeting. Click the button below to proceed.</p>
                    
                    <?php if (count($participants) > 0): ?>
                        <h5>Participants already in the meeting:</h5>
                        <ul class="participants-list">
                            <?php foreach ($participants as $participant): ?>
                                <li><?php echo htmlspecialchars($participant); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>You will be the first to join.</p>
                    <?php endif; ?>
                    
                    <a href="<?php echo $is_daily ? 'meeting-room-daily.php' : 'meeting-room.php'; ?>?code=<?php echo $meeting_code; ?>" class="btn btn-primary btn-lg mt-4">
                        <i class="fas fa-video me-2"></i>Join Now
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
