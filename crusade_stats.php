<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
require_once 'includes/session.php';
session_init();
}
if (!isset($_SESSION['user_id'])) {
  $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
  header('Location: login.php');
  exit;
}

require_once 'includes/db.php';

$crusade_code = isset($_GET['code']) ? $_GET['code'] : '';

if (empty($crusade_code)) {
    die('Invalid crusade code.');
}

// Fetch crusade details
$stmt = $conn->prepare("SELECT id, user_id, title FROM praise_crusades WHERE crusade_code = ?");
$stmt->bind_param("s", $crusade_code);
$stmt->execute();
$result = $stmt->get_result();
$crusade = $result->fetch_assoc();
$stmt->close();

if (!$crusade) {
    die('Crusade not found.');
}

// Check if the logged-in user is the owner of the crusade
if ($_SESSION['user_id'] != $crusade['user_id']) {
    die('You are not authorized to view these statistics.');
}

$crusade_id = $crusade['id'];
$crusade_title = htmlspecialchars($crusade['title']);

// Fetch total views
$stmt_views = $conn->prepare("SELECT COUNT(*) AS total_views FROM praise_crusade_stats WHERE crusade_id = ?");
$stmt_views->bind_param("i", $crusade_id);
$stmt_views->execute();
$result_views = $stmt_views->get_result();
$views_data = $result_views->fetch_assoc();
$total_views = $views_data['total_views'];
$stmt_views->close();

// Fetch salvation clicks
$stmt_salvation = $conn->prepare("SELECT COUNT(*) AS total_salvation_clicks FROM praise_salvation_clicks WHERE crusade_id = ?");
$stmt_salvation->bind_param("i", $crusade_id);
$stmt_salvation->execute();
$result_salvation = $stmt_salvation->get_result();
$salvation_data = $result_salvation->fetch_assoc();
$total_salvation_clicks = $salvation_data['total_salvation_clicks'];
$stmt_salvation->close();

// Fetch viewer countries and regions
$stmt_countries = $conn->prepare("SELECT country, region, COUNT(*) AS count FROM praise_crusade_stats WHERE crusade_id = ? AND country IS NOT NULL GROUP BY country, region ORDER BY count DESC");
$stmt_countries->bind_param("i", $crusade_id);
$stmt_countries->execute();
$result_countries = $stmt_countries->get_result();
$viewer_locations = [];
while ($row = $result_countries->fetch_assoc()) {
    $viewer_locations[] = $row;
}
$stmt_countries->close();

$conn->close();

?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container stats-container page-container">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <h2 class="text-center mb-4">Statistics for "<?php echo $crusade_title; ?>"</h2>

                <div class="row text-center mb-4">
                    <div class="col-md-6">
                        <div class="card stats-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Views</h5>
                                <p class="stat-value"><?php echo number_format($total_views); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card stats-card">
                            <div class="card-body">
                                <h5 class="card-title">Salvation Decisions</h5>
                                <p class="stat-value"><?php echo number_format($total_salvation_clicks); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title">Viewers by Location</h4>
                        <?php if (!empty($viewer_locations)): ?>
                            <ul class="list-group">
                                <?php foreach ($viewer_locations as $location): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($location['country']); ?></strong>
                                            <?php if ($location['region']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($location['region']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-primary rounded-pill"><?php echo number_format($location['count']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>No location data available yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button class="btn btn-primary share-btn" data-share-type="facebook"><i class="fab fa-facebook"></i> Share on Facebook</button>
                    <button class="btn btn-info share-btn" data-share-type="twitter"><i class="fab fa-twitter"></i> Share on Twitter</button>
                    <button class="btn btn-success share-btn" data-share-type="whatsapp"><i class="fab fa-whatsapp"></i> Share on WhatsApp</button>
                    <button class="btn btn-secondary share-btn" data-share-type="copy"><i class="fas fa-copy"></i> Copy Link</button>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const shareButtons = document.querySelectorAll('.share-btn');
            const statsPageUrl = window.location.href; // The URL of this stats page

            shareButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const shareType = this.dataset.shareType;
                    const crusadeTitle = "<?php echo $crusade_title; ?>";
                    const totalViews = "<?php echo $total_views; ?>";
                    const totalSalvation = "<?php echo $total_salvation_clicks; ?>";

                    let shareText = `Check out the stats for my crusade "${crusadeTitle}"! Total Views: ${totalViews}, Salvation Decisions: ${totalSalvation}.`;
                    let urlToShare = statsPageUrl;

                    switch (shareType) {
                        case 'facebook':
                            window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(urlToShare)}&quote=${encodeURIComponent(shareText)}`, '_blank');
                            break;
                        case 'twitter':
                            window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}&url=${encodeURIComponent(urlToShare)}`, '_blank');
                            break;
                        case 'whatsapp':
                            window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(shareText + ' ' + urlToShare)}`, '_blank');
                            break;
                        case 'copy':
                            navigator.clipboard.writeText(urlToShare).then(() => {
                                alert('Link copied to clipboard!');
                            }).catch(err => {
                                console.error('Failed to copy text: ', err);
                            });
                            break;
                    }
                });
            });
        });
    </script>
    <?php include 'includes/bottom_navbar.php'; ?>
<?php include 'includes/footer.php'; ?>
