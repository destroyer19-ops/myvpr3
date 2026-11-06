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

// Initialize filter variables
$filter_language = $_GET['language'] ?? '';
$filter_children_outreach = isset($_GET['children_outreach']) ? 1 : 0;
$filter_movie_outreach = isset($_GET['movie_outreach']) ? 1 : 0;
$filter_ministrations = isset($_GET['ministrations']) ? 1 : 0;

// Base SQL query
$sql = "SELECT * FROM praise_videos WHERE 1=1";
$params = [];
$types = "";

// Add filters to the query
if ($filter_language) {
    $sql .= " AND language = ?";
    $params[] = $filter_language;
    $types .= "s";
}
if ($filter_children_outreach) {
    $sql .= " AND category_children_outreach = 1";
}
if ($filter_movie_outreach) {
    $sql .= " AND category_movie_outreach = 1";
}
if ($filter_ministrations) {
    $sql .= " AND category_ministrations = 1";
}

$sql .= " ORDER BY title";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    // Handle prepare error
    die("Error preparing the SQL statement: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$all_videos = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $all_videos[] = $row;
    }
}

// Organize videos into categories in PHP
$categorized_videos = [
    'Children Outreach' => [],
    'Movie Outreach' => [],
    'Ministrations' => [],
    'Other' => [] // A catch-all for videos that don't fit other categories
];

foreach ($all_videos as $video) {
    $in_category = false;
    if (!empty($video['category_children_outreach'])) {
        $categorized_videos['Children Outreach'][] = $video;
        $in_category = true;
    }
    if (!empty($video['category_movie_outreach'])) {
        $categorized_videos['Movie Outreach'][] = $video;
        $in_category = true;
    }
    if (!empty($video['category_ministrations'])) {
        $categorized_videos['Ministrations'][] = $video;
        $in_category = true;
    }
    if (!$in_category) {
        $categorized_videos['Other'][] = $video;
    }
}


// Fetch distinct languages for filter dropdown
$languages = [];
$lang_result = $conn->query("SELECT DISTINCT language FROM praise_videos WHERE language IS NOT NULL AND language != ''");
if ($lang_result && $lang_result->num_rows > 0) {
    while($row = $lang_result->fetch_assoc()) {
        $languages[] = $row['language'];
    }
}
?>
<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="assets/css/video_library.css?v=<?php echo time(); ?>">

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="video-library-container container-fluid page-container">
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <h2 class="text-center my-4">Video Library</h2>

                <!-- Filter Form -->
                <form action="videos.php" method="GET" class="video-filter-form">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="languageFilter" class="form-label">Language</label>
                            <select class="form-select" id="languageFilter" name="language">
                                <option value="">All Languages</option>
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?php echo htmlspecialchars($lang); ?>" <?php echo ($filter_language == $lang) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lang); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Categories</label>
                            <div class="d-flex flex-wrap">
                                <div class="form-check form-check-inline me-3">
                                    <input class="form-check-input" type="checkbox" id="childrenOutreach" name="children_outreach" value="1" <?php echo ($filter_children_outreach) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="childrenOutreach">Children Outreach</label>
                                </div>
                                <div class="form-check form-check-inline me-3">
                                    <input class="form-check-input" type="checkbox" id="movieOutreach" name="movie_outreach" value="1" <?php echo ($filter_movie_outreach) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="movieOutreach">Movie Outreach</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="ministrations" name="ministrations" value="1" <?php echo ($filter_ministrations) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="ministrations">Ministrations</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex mt-3 mt-md-auto">
                            <button type="submit" class="btn btn-primary w-100 me-2">Filter</button>
                            <a href="videos.php" class="btn btn-secondary w-100">Clear</a>
                        </div>
                    </div>
                </form>

                <?php if (empty($all_videos)): ?>
                    <div class="no-videos-message">
                        <h2>No videos found matching your criteria.</h2>
                    </div>
                <?php else: ?>
                    <?php foreach ($categorized_videos as $category_name => $videos): ?>
                        <?php if (!empty($videos)): ?>
                            <div class="video-carousel-section mt-5">
                                <h3><?php echo htmlspecialchars($category_name); ?></h3>
                                <div class="video-carousel">
                                    <?php foreach ($videos as $video): ?>
                                        <a href="create_crusade.php?video_url=<?php echo urlencode($video['video_url']); ?>" class="video-card-link">
                                            <div class="video-card">
                                                <div class="video-card-img-wrapper" style="background-image: url('<?php echo htmlspecialchars($video['thumbnail_url'] ?: 'https://via.placeholder.com/480x270?text=Video'); ?>');">
                                                    <i class="fas fa-play-circle play-icon"></i>
                                                </div>
                                                <div class="video-card-body">
                                                    <h5 class="video-card-title"><?php echo htmlspecialchars($video['title']); ?></h5>
                                                    <p class="video-card-text"><?php echo htmlspecialchars(substr($video['description'] ?? '', 0, 70)) . (strlen($video['description'] ?? '') > 70 ? '...' : ''); ?></p>
                                                    <button class="btn btn-primary btn-sm mt-auto">Select Video</button>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
