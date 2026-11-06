<?php
ini_set("display_errors", 1);
if (session_status() == PHP_SESSION_NONE) {
require_once 'includes/session.php';
session_init();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

require_once __DIR__ . '/includes/db.php';

// Get stream key from URL
if (!isset($_GET['stream_key']) || empty($_GET['stream_key'])) {
    die("No stream key provided.");
}
$stream_key = $_GET['stream_key'];

// Fetch the shared stream details and verify ownership
$stmt = $conn->prepare("
    SELECT
        s.id AS shared_stream_id,
        s.crusade_id,
        s.user_id AS sharer_user_id,
        s.is_active,
        c.title AS crusade_title,
        c.description AS crusade_description
    FROM praise_user_shared_streams s
    JOIN praise_crusades c ON s.crusade_id = c.id
    WHERE s.stream_key = ?
");
$stmt->bind_param("s", $stream_key);
$stmt->execute();
$result = $stmt->get_result();
$shared_stream_details = $result->fetch_assoc();
$stmt->close();

if (!$shared_stream_details) {
    die("Invalid stream key or shared stream not found.");
}

// Ensure the logged-in user is the owner of this shared stream
if ($shared_stream_details['sharer_user_id'] != $user_id) {
    die("Unauthorized access to shared stream stats.");
}

$crusade_title = htmlspecialchars($shared_stream_details['crusade_title']);
$crusade_description = htmlspecialchars($shared_stream_details['crusade_description']);
$is_active = $shared_stream_details['is_active'];

// Fetch stats for this specific shared stream (using stream_key)
$stats_query = $conn->prepare("SELECT COUNT(*) AS total_views, COUNT(DISTINCT ip_address) AS unique_views, SUM(CASE WHEN stayed_for_a_minute = 1 THEN 1 ELSE 0 END) AS engaged_views FROM praise_crusade_stats WHERE stream_key = ?");
$stats_query->bind_param("s", $stream_key);
$stats_query->execute();
$stats_result = $stats_query->get_result();
$stats_data = $stats_result->fetch_assoc();
$stats_query->close();

$total_views = $stats_data['total_views'] ?? 0;
$unique_views = $stats_data['unique_views'] ?? 0;
$engaged_views = $stats_data['engaged_views'] ?? 0;

// Fetch detailed raw data for this shared stream
$raw_stats = [];
$raw_stats_query = $conn->prepare("SELECT ip_address, user_agent, created_at, stayed_for_a_minute FROM praise_crusade_stats WHERE stream_key = ? ORDER BY created_at DESC");
$raw_stats_query->bind_param("s", $stream_key);
$raw_stats_query->execute();
$raw_stats_result = $raw_stats_query->get_result();
while ($row = $raw_stats_result->fetch_assoc()) {
    $raw_stats[] = $row;
}
$raw_stats_query->close();

$conn->close();
?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="stats-container container">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Stats for Shared Stream: <?php echo $crusade_title; ?></h2>
                        <p class="text-center"><em>Description: <?php echo $crusade_description; ?></em></p>
                        <p class="text-center mb-4">Status: <span class="<?php echo $is_active ? 'text-status-active' : 'text-status-ended'; ?>"><?php echo $is_active ? 'Active' : 'Ended'; ?></span></p>

                        <div class="row text-center my-4">
                            <div class="col-md-4">
                                <h3>Total Views</h3>
                                <p class="fs-1"><?php echo $total_views; ?></p>
                            </div>
                            <div class="col-md-4">
                                <h3>Unique Views</h3>
                                <p class="fs-1"><?php echo $unique_views; ?></p>
                            </div>
                            <div class="col-md-4">
                                <h3>Engaged Views (5+ min)</h3>
                                <p class="fs-1"><?php echo $engaged_views; ?></p>
                            </div>
                        </div>

                        <canvas id="viewsChart"></canvas>

                        <h3 class="text-center mt-5">Raw Data for this Shared Stream</h3>
                        <?php if (empty($raw_stats)): ?>
                            <p class="text-center text-muted">No visitors yet for this shared stream.</p>
                        <?php else: ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>IP Address</th>
                                        <th>User Agent</th>
                                        <th>Time</th>
                                        <th>Stayed for 5+ Minutes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($raw_stats as $stat):
                                        $formatted_time = date("F j, Y, g:i a", strtotime($stat['created_at']));
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($stat['ip_address']); ?></td>
                                            <td><?php echo htmlspecialchars($stat['user_agent']); ?></td>
                                            <td><?php echo $formatted_time; ?></td>
                                            <td><?php echo $stat['stayed_for_a_minute'] ? 'Yes' : 'No'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
