<?php
ini_set('session.use_only_cookies', 1);
ini_set("display_errors", 1);
require_once 'includes/session.php';
session_init();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

require_once 'includes/db.php';

$user_id = $_SESSION['user_id'];

// Determine the current quarter
$current_month = date('n');
$current_quarter = ceil($current_month / 3);

$year = date('Y');
$quarter_start_month = ($current_quarter - 1) * 3 + 1;
$quarter_end_month = $quarter_start_month + 2;

$start_date = date('Y-m-d H:i:s', mktime(0, 0, 0, $quarter_start_month, 1, $year));
$end_date = date('Y-m-d H:i:s', mktime(23, 59, 59, $quarter_end_month, date('t', mktime(0, 0, 0, $quarter_end_month, 1, $year)), $year));

// Fetch total meeting duration for the current quarter
$total_duration = 0;
$sql = "SELECT SUM(duration) as total_duration FROM praise_meeting_sessions WHERE user_id = ? AND start_time >= ? AND start_time <= ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_duration = $row['total_duration'] ? $row['total_duration'] : 0;
    $stmt->close();
}

// Fetch number of meetings attended for the current quarter
$meetings_attended = 0;
$sql = "SELECT COUNT(DISTINCT meeting_id) as meetings_attended FROM praise_meeting_sessions WHERE user_id = ? AND start_time >= ? AND start_time <= ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $meetings_attended = $row['meetings_attended'] ? $row['meetings_attended'] : 0;
    $stmt->close();
}

?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="quarterly-wrap-container container">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center">Your Quarterly Wrap</h2>
                        <p class="text-center text-muted">Here's a summary of your activity for Q<?php echo $current_quarter; ?> <?php echo $year; ?></p>
                        
                        <div class="row text-center mt-5">
                            <div class="col-md-6">
                                <h5>Total Time in Meetings</h5>
                                <p class="stat-value"><?php echo round($total_duration / 60, 2); ?> Minutes</p>
                            </div>
                            <div class="col-md-6">
                                <h5>Meetings Attended</h5>
                                <p class="stat-value"><?php echo $meetings_attended; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>
