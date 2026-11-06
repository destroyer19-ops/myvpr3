<?php
ini_set("display_errors", 1);
require_once 'includes/session.php';
session_init();

require_once 'includes/db.php';

// Fetch all live streams that are currently active
$all_live_streams = [];
$stmt = $conn->prepare("SELECT * FROM praise_live_tv WHERE is_live = 1 ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $all_live_streams[] = $row;
}
$stmt->close();

$global_live_event = null;
$other_live_streams = [];

if (!empty($all_live_streams)) {
    // Designate the first stream as the 'global live event' for now
    $global_live_event = $all_live_streams[0];
    // Remaining streams are 'other live streams'
    $other_live_streams = array_slice($all_live_streams, 1);
}

// Placeholder for live individuals count - this would require real-time integration
$live_individuals_count = rand(100, 1000); // Random number for demonstration
?>
<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="assets/css/live_tv.css?v=<?php echo time(); ?>">
<?php include 'includes/navbar.php'; ?>

<div class="page-container">

    <?php if ($global_live_event): ?>
        <!-- Cinematic Hero Section -->
        <div class="cinematic-hero-section" style="background-image: url('<?php echo htmlspecialchars($global_live_event['thumbnail_url'] ?: 'https://via.placeholder.com/1280x720?text=Global+Live+Stream'); ?>');">
            <div class="hero-content">
                <span class="live-badge"><i class="fas fa-circle"></i> LIVE</span>
                <h1><?php echo htmlspecialchars($global_live_event['title']); ?></h1>
                <p><?php echo htmlspecialchars(substr($global_live_event['description'], 0, 200)) . (strlen($global_live_event['description']) > 200 ? '...' : ''); ?></p>
                
                <a href="watch.php?live_stream_id=<?php echo $global_live_event['id']; ?>" class="btn btn-watch-now">Watch Now <i class="fas fa-play-circle"></i></a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($other_live_streams)): ?>
        <!-- Stream Carousel Section -->
        <div class="stream-carousel-section">
            <h2>Other Live Events</h2>
            <div class="stream-carousel">
                <?php foreach ($other_live_streams as $stream): ?>
                    <a href="watch.php?live_stream_id=<?php echo $stream['id']; ?>" class="stream-card-link" style="text-decoration: none;">
                        <div class="stream-card">
                            <div class="stream-card-img-wrapper" style="background-image: url('<?php echo htmlspecialchars($stream['thumbnail_url'] ?: 'https://via.placeholder.com/480x270?text=Live+Stream'); ?>');">
                                <span class="live-badge"><i class="fas fa-circle"></i> LIVE</span>
                            </div>
                            <div class="stream-card-body">
                                <h5 class="stream-card-title"><?php echo htmlspecialchars($stream['title']); ?></h5>
                                <p class="stream-card-text"><?php echo htmlspecialchars(substr($stream['description'], 0, 50)) . (strlen($stream['description']) > 50 ? '...' : ''); ?></p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif (empty($global_live_event)): ?>
        <div class="no-streams-message">
            <h2>There are no live streams at the moment.</h2>
            <p>Please check back later.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/bottom_navbar.php'; ?>
<?php include 'includes/footer.php'; ?>
