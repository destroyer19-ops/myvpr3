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

function base_url_local()
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

$crusade_code = isset($_GET['code']) ? $_GET['code'] : '';

if (empty($crusade_code)) {
    die('Invalid crusade code.');
}

$stmt = $conn->prepare("SELECT id, user_id, title, description, video_url, is_active, crusade_code, start_time, end_time FROM praise_crusades WHERE crusade_code = ?");
$stmt->bind_param("s", $crusade_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die('Invalid crusade code.');
}

$crusade = $result->fetch_assoc();

$now = new DateTime();
$start_time_dt = !empty($crusade['start_time']) ? new DateTime($crusade['start_time']) : null;
$end_time_dt = !empty($crusade['end_time']) ? new DateTime($crusade['end_time']) : null;

$is_owner = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $crusade['user_id']);

// Determine crusade status
$crusade_status = 'INVALID'; // Default status

// Case 1: Crusade is explicitly active
if ((int)$crusade['is_active'] === 1) {
    if ($end_time_dt && $now >= $end_time_dt) {
        // Crusade was active but end time has passed
        $crusade_status = 'ENDED_VIA_DEACTIVATION';
    } else {
        $crusade_status = 'LIVE';
    }
} 
// Case 2: Crusade is not active, but might be scheduled or ended
elseif ((int)$crusade['is_active'] === 0) {
    if ($start_time_dt && $now < $start_time_dt) {
        // Scheduled for the future
        $crusade_status = 'NOT_STARTED';
    } elseif ($start_time_dt && $now >= $start_time_dt && (!$end_time_dt || $now < $end_time_dt)) {
        // Start time has passed, but it's not active yet. Needs activation.
        $crusade_status = 'LIVE_PENDING_ACTIVATION';
    } elseif ($end_time_dt && $now >= $end_time_dt) {
        // End time has passed (or start time passed, and no end time, implies ended)
        $crusade_status = 'ENDED';
    } else {
        // Default catch-all for inactive (e.g., manually deactivated, or no schedule info)
        $crusade_status = 'ENDED'; 
    }
}


// Auto-activate logic if needed
if ($crusade_status === 'LIVE_PENDING_ACTIVATION') {
    $stmt_start = $conn->prepare("UPDATE praise_crusades SET is_active = 1 WHERE id = ?");
    $stmt_start->bind_param("i", $crusade['id']);
    $stmt_start->execute();
    $stmt_start->close();
    $crusade['is_active'] = 1;
    $crusade_status = 'LIVE';
}

// Auto-deactivate logic if needed (only if it was LIVE and should now be ended)
if ($crusade_status === 'ENDED_VIA_DEACTIVATION' || ($crusade_status === 'LIVE' && $end_time_dt && $now >= $end_time_dt)) {
    $stmt_end = $conn->prepare("UPDATE praise_crusades SET is_active = 0 WHERE id = ?");
    $stmt_end->bind_param("i", $crusade['id']);
    $stmt_end->execute();
    $stmt_end->close();
    $crusade['is_active'] = 0;
    $crusade_status = 'ENDED';
    // If it's the owner and the crusade just ended, redirect to stats
    if ($is_owner && isset($_SESSION['current_crusade_code']) && $_SESSION['current_crusade_code'] === $crusade_code) {
        unset($_SESSION['current_crusade_code']);
        header('Location: crusade_stats.php?code=' . urlencode($crusade_code));
        exit;
    }
}

// Handle non-owner access based on status
if (!$is_owner) {
    if ($crusade_status === 'NOT_STARTED') {
        $page_title = "Crusade Not Started";
        $page_description = "This crusade is scheduled to begin at " . ($start_time_dt ? $start_time_dt->format('F j, Y, g:i A T') : 'a future date');
        include 'includes/header.php';
        include 'includes/navbar.php';
        ?>
        <div class="container text-center py-5">
            <h2 class="mb-3">Crusade Not Started Yet</h2>
            <p class="lead"><?php echo htmlspecialchars($crusade['title']); ?> is scheduled to begin on:</p>
            <h3 class="text-primary"><?php echo $start_time_dt ? htmlspecialchars($start_time_dt->format('F j, Y, g:i A T')) : 'Unknown Time'; ?></h3>
            <p class="mt-4">Please check back then!</p>
            <a href="dashboard.php" class="btn btn-primary mt-3">Go to Dashboard</a>
        </div>
        <?php
        include 'includes/footer.php';
        exit;
    } elseif ($crusade_status === 'ENDED') {
        $page_title = "Crusade Ended";
        $page_description = "This crusade has already concluded.";
        include 'includes/header.php';
        include 'includes/navbar.php';
        ?>
        <div class="container text-center py-5">
            <h2 class="mb-3">Crusade Has Ended</h2>
            <p class="lead"><?php echo htmlspecialchars($crusade['title']); ?> has already concluded.</p>
            <p class="mt-4">Thank you for your interest!</p>
            <a href="dashboard.php" class="btn btn-primary mt-3">Go to Dashboard</a>
        </div>
        <?php
        include 'includes/footer.php';
        exit;
    }
}
// If owner, or if status is LIVE for non-owner, proceed to render the room
$_SESSION['current_crusade_code'] = $crusade_code; // Store the current crusade code in session

$page_title = $crusade['title'] ?? 'Crusade';
$page_description = trim((string)($crusade['description'] ?? ''));
if ($page_description === '') {
    $page_description = "Join this Crusade: {$page_title}.";
}
$page_image = base_url_local() . '/logo.png';

// Check if the original video_url refers to an official stream
$actual_video_to_embed = $crusade['video_url'];
if (strpos($crusade['video_url'], 'LIVE_STREAM_OFFICIAL_') === 0) {
    $official_stream_id = (int)str_replace('LIVE_STREAM_OFFICIAL_', '', $crusade['video_url']);
    $stmt_official_stream = $conn->prepare("SELECT stream_url FROM praise_live_tv WHERE id = ?");
    $stmt_official_stream->bind_param("i", $official_stream_id);
    $stmt_official_stream->execute();
    $result_official_stream = $stmt_official_stream->get_result();
    if ($row_official_stream = $result_official_stream->fetch_assoc()) {
        $actual_video_to_embed = $row_official_stream['stream_url'];
    } else {
        $actual_video_to_embed = ''; // Official stream not found or inactive
    }
    $stmt_official_stream->close();
}

// Basic video embed logic
$embed_url = '';
$direct_video_url = '';
$is_youtube = false;
if (!empty($actual_video_to_embed)) {
    if (strpos($actual_video_to_embed, 'youtube.com/watch') !== false || strpos($actual_video_to_embed, 'youtu.be/') !== false) {
        $video_id = '';
        if (strpos($actual_video_to_embed, 'youtube.com/watch') !== false) {
            $query = parse_url($actual_video_to_embed, PHP_URL_QUERY);
            parse_str($query ?? '', $params);
            $video_id = $params['v'] ?? '';
        } else {
            $video_id = ltrim(parse_url($actual_video_to_embed, PHP_URL_PATH), '/');
        }
        if (!empty($video_id)) {
            $embed_url = 'https://www.youtube.com/embed/' . $video_id;
            $is_youtube = true;
        }
    }
    if (!$is_youtube) {
        $direct_video_url = $actual_video_to_embed;
    }
}


// Log the visit (dedupe within 5 minutes per IP)
$stat_id = 0;
$ip_address = $_SERVER['REMOTE_ADDR'];
$stmt_check = $conn->prepare("SELECT id FROM praise_crusade_stats WHERE crusade_id = ? AND ip_address = ? AND created_at >= (NOW() - INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1");
$stmt_check->bind_param("is", $crusade['id'], $ip_address);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$existing = $result_check->fetch_assoc();
$stmt_check->close();

if ($existing) {
    $stat_id = (int)$existing['id'];
} else {
    // Attempt to get country and region immediately for the initial log
    $country = null;
    $region = null;
    $ip_data = @json_decode(file_get_contents("http://ip-api.com/json/" . $ip_address));
    if ($ip_data && $ip_data->status == 'success') {
        $country = $ip_data->country;
        $region = $ip_data->city;
    }

    $stmt = $conn->prepare("INSERT INTO praise_crusade_stats (crusade_id, ip_address, user_agent, country, region, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issss", $crusade['id'], $ip_address, $_SERVER['HTTP_USER_AGENT'], $country, $region);
    $stmt->execute();
    $stat_id = $stmt->insert_id;
    $stmt->close();
}

?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="crusade-room-container container">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <?php if (isset($_GET['share']) && $_GET['share'] == '1'): ?>
                    <div class="alert alert-success text-center">
                        You're ready to share. Click "Share My Personal Room" to create your watch party link.
                    </div>
                <?php endif; ?>
                <h2 class="text-center mb-4"><?php echo htmlspecialchars($crusade['title']); ?></h2>
                <?php
                // Fetch view count
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
                <?php if ($is_youtube && !empty($embed_url)): ?>
                    <div class="video-container ratio ratio-16x9">
                        <iframe src="<?php echo htmlspecialchars($embed_url); ?>" title="Crusade Video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                <?php elseif (!empty($direct_video_url)): ?>
                    <div class="video-container">
                        <video id="videoPlayer" controls class="w-100"></video>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <?php if ($crusade['video_url'] === 'LIVE_STREAM_OFFICIAL'): ?>
                            The official live stream is not active at the moment. Please check back later.
                        <?php else: ?>
                            The video URL for this crusade is not valid or empty.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="text-center mt-4">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $crusade['user_id']): ?>
                        <button type="button" class="btn btn-danger btn-lg mt-2 mx-2" onclick="endCrusade('<?php echo htmlspecialchars($crusade['crusade_code']); ?>')">End & View Stats</button>
                    <?php endif; ?>
                    <p>Want to host a watch party? Create your own shareable page!</p>
                    <button type="button" class="btn btn-secondary-custom btn-lg mt-2 mx-2" data-bs-toggle="modal" data-bs-target="#giveLifeModal" data-crusade-code="<?php echo htmlspecialchars($crusade['crusade_code']); ?>">
                        Give Your Life to Christ
                    </button>
                    <button type="button" class="btn btn-success btn-lg mt-2 mx-2" data-bs-toggle="modal" data-bs-target="#givingModal">
                        Give Offerings & Partnership
                    </button>
                    <form action="start_share.php" method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="crusade_code" value="<?php echo htmlspecialchars($crusade['crusade_code']); ?>">
                        <button type="submit" class="btn btn-primary-custom btn-lg mt-2 mx-2">Share My Personal Room</button>
                    </form>
                </div>

                <div class="comment-section-container mt-4">
                    <div id="comments-section"></div>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script> <!-- HLS.js CDN -->
    <script src="N-gage/Ngage/Ngage.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var video = document.getElementById('videoPlayer');
            var videoSrc = '<?php echo htmlspecialchars($direct_video_url); ?>';

            if (!video || !videoSrc) {
                return;
            }

            if (videoSrc.includes('.m3u8') && Hls.isSupported()) { // Check if it's an HLS stream
                var hls = new Hls();
                hls.loadSource(videoSrc);
                hls.attachMedia(video);
                hls.on(Hls.Events.MANIFEST_PARSED, function() {
                    video.play();
                });
            } else if (video.canPlayType('video/mp4') || video.canPlayType('application/vnd.apple.mpegurl')) { // Fallback for MP4 or native HLS
                video.src = videoSrc;
                video.addEventListener('loadedmetadata', function() {
                    video.play();
                });
            } else {
                // Fallback for browsers that don't support either
                console.error("Browser does not support HLS or MP4 playback directly.");
            }
        });

        <?php if (isset($_GET['salvation']) && $_GET['salvation'] == 'true'): ?>
            Toastify({
                text: "Your decision for Christ has been recorded. Welcome to the family!",
                duration: 5000,
                gravity: "top",
                position: "right",
                backgroundColor: "linear-gradient(to right, #28a745, #218838)",
            }).showToast();
        <?php endif; ?>

        const myCommentBox = new Ngage({
            anchor: "#comments-section",
            id: "<?php echo $crusade_code; ?>",
            userID: "<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'default'; ?>",
            username: "<?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'default'; ?>",
            endpoint: "N-gage/Ngage/Ngage.php"
        });
        myCommentBox.init();

        function showToast(message, success = true) {
            Toastify({
                text: message,
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: success ? "linear-gradient(to right, #00b09b, #96c93d)" : "linear-gradient(to right, #ff5f6d, #ffc371)",
            }).showToast();
        }

        function endCrusade(crusadeCode) {
            if (confirm("Are you sure you want to end this crusade? Viewers will be redirected.")) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                fetch('end_crusade.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'crusade_code=' + crusadeCode + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(response => response.text())
                .then(data => {
                    console.log(data); // Log success or error message from end_crusade.php
                    window.location.href = 'crusade_stats.php?code=' + crusadeCode;
                })
                .catch(error => {
                    console.error('Error ending crusade:', error);
                    showToast("Error ending crusade.", false);
                });
            }
        }
    </script>
    <script>
        setTimeout(() => {
            const formData = new FormData();
            formData.append('stat_id', <?php echo $stat_id; ?>);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            formData.append('csrf_token', csrfToken);

            fetch('track_stay.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                console.log(data);
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }, 60000); // 1 minute
    </script>
<?php include 'includes/bottom_navbar.php'; ?>
<?php include 'includes/footer.php'; ?>
