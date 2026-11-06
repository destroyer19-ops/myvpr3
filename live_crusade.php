<?php
ini_set('session.use_only_cookies', 1);
ini_set("display_errors", 1);
require_once 'includes/session.php';
session_init();
if (!isset($_SESSION['user_id'])) {
  $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
  header('Location: login.php');
  exit;
}

require_once 'includes/db.php';

// --- Ensure a permanent 'official-live' crusade record exists for sharing ---
$official_crusade_code = 'official-live';
$stmt_check = $conn->prepare("SELECT id FROM praise_crusades WHERE crusade_code = ?");
$stmt_check->bind_param("s", $official_crusade_code);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$official_crusade_record = $result_check->fetch_assoc();
$stmt_check->close();

$official_crusade_id = null;
if ($official_crusade_record) {
    $official_crusade_id = $official_crusade_record['id'];
} else {
    // Record doesn't exist, so we create it. User ID 1 is assumed to be an admin.
    $stmt_create = $conn->prepare("INSERT INTO praise_crusades (user_id, title, description, crusade_code, video_url) VALUES (1, 'Official Live Crusade', 'Main live event stream.', ?, '')");
    $stmt_create->bind_param("s", $official_crusade_code);
    $stmt_create->execute();
    $official_crusade_id = $conn->insert_id;
    $stmt_create->close();
}
// --- End of check ---

// Find the active live stream from the dedicated table
$stmt = $conn->prepare("SELECT * FROM praise_live_tv WHERE is_live = 1 ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$live_stream = $result->fetch_assoc();
$stmt->close();

$crusade = null;
if ($live_stream) {
    // To minimize frontend changes, we'll create a crusade-like object from the live stream data.
    $crusade = [
        'id' => $official_crusade_id, // Use the real, permanent ID
        'title' => $live_stream['title'],
        'description' => $live_stream['description'],
        'video_url' => $live_stream['stream_url'],
        'crusade_code' => $official_crusade_code
    ];
}

if (!$crusade) {
    die('The official live stream is not active at the moment. Please check back later.');
}

// Log the visit using the real crusade ID
$stmt_log = $conn->prepare("INSERT INTO praise_crusade_stats (crusade_id, ip_address, user_agent) VALUES (?, ?, ?)");
$stmt_log->bind_param("iss", $crusade['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
$stmt_log->execute();
$stmt_log->close();

?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="crusade-room-container container page-container">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <h2 class="text-center mb-4"><?php echo htmlspecialchars($crusade['title']); ?></h2>
                <?php
                // Fetch view count for the official crusade
                $stmt_views = $conn->prepare("SELECT COUNT(*) AS total_views FROM praise_crusade_stats WHERE crusade_id = ?");
                $stmt_views->bind_param("i", $crusade['id']);
                $stmt_views->execute();
                $result_views = $stmt_views->get_result();
                $views_data = $result_views->fetch_assoc();
                $total_views = $views_data['total_views'];
                $stmt_views->close();
                ?>
                <p class="text-center text-muted">Views: <?php echo $total_views; ?></p>
                <p class="text-center"><?php echo htmlspecialchars($crusade['description']); ?></p>

                <?php if (!empty($crusade['video_url'])): ?>
                    <div class="video-container">
                        <iframe src="<?php echo htmlspecialchars($crusade['video_url']); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">The live stream URL has not been set by the administrator.</div>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <p>Want to host a watch party? Create your own shareable page!</p>
                    <button type="button" class="btn btn-secondary-custom btn-lg mt-2 mx-2" data-bs-toggle="modal" data-bs-target="#giveLifeModal" data-crusade-code="<?php echo htmlspecialchars($crusade['crusade_code']); ?>">
                        Give Your Life to Christ
                    </button>
                    <button type="button" class="btn btn-primary-custom btn-lg mt-2 mx-2" data-bs-toggle="modal" data-bs-target="#givingModal">
                        Give Offerings & Partnership
                    </button>
                    <a href="start_share.php?crusade_code=<?php echo htmlspecialchars($crusade['crusade_code']); ?>" class="btn btn-primary mt-2 mx-2">Share My Personal Room</a>
                </div>

                <div class="comment-section-container mt-4">
                    <div id="comments-section"></div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="N-gage/Ngage/Ngage.js"></script>
    <script>
        const myCommentBox = new Ngage({
            anchor: "#comments-section",
            id: "<?php echo $crusade_code; ?>",
            userID: "<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'default'; ?>",
            username: "<?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'default'; ?>",
            endpoint: "N-gage/Ngage/Ngage.php"
        });
        myCommentBox.init();
    </script>
    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
