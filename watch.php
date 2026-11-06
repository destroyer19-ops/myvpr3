<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}
require_once __DIR__ . '/includes/db.php';

function base_url_local()
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

$crusade_title = "Live Stream"; // Default title
$crusade_description = "";
$shared_by = "Admin"; // Default for direct live streams
$shared_by_raw = "Admin";
$embed_url = '';
$stream_source_id = null; // To store either crusade_id or live_stream_id for logging
$stream_type = ''; // 'shared' or 'live_tv'

if (!isset($_SESSION['user_id']) && empty($_SESSION['redirect_after_login'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'watch.php';
}

if (isset($_GET['live_stream_id']) && !empty($_GET['live_stream_id'])) {
    $live_stream_id = (int)$_GET['live_stream_id'];
    $stream_type = 'live_tv';

    // Fetch live stream details from praise_live_tv
    $stmt = $conn->prepare("SELECT id, title, description, stream_url FROM praise_live_tv WHERE id = ?");
    $stmt->bind_param("i", $live_stream_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $live_tv_stream = $result->fetch_assoc();
    $stmt->close();

    if (!$live_tv_stream) {
        die("Live stream not found.");
    }

    $crusade_title = htmlspecialchars($live_tv_stream['title']);
    $crusade_description = $live_tv_stream['description'] ?? '';
    $video_url = $live_tv_stream['stream_url'];
    $stream_source_id = $live_tv_stream['id']; // Use live stream ID for logging

} elseif (isset($_GET['stream_key']) && !empty($_GET['stream_key'])) {
    $stream_key = $_GET['stream_key'];
    $stream_type = 'shared';

    // Fetch the shared stream details
    $stmt = $conn->prepare("
        SELECT
            s.id AS shared_stream_id,
            s.crusade_id,
            s.user_id,
            s.is_active AS shared_stream_is_active,
            c.title AS crusade_title,
            c.description AS crusade_description,
            c.crusade_code,
            c.video_url AS original_video_url,
            c.is_active AS crusade_is_active,
            c.start_time AS crusade_start_time,
            c.end_time AS crusade_end_time,
            u.username AS shared_by_username
        FROM praise_user_shared_streams s
        JOIN praise_crusades c ON s.crusade_id = c.id
        JOIN praise_users u ON s.user_id = u.id
        WHERE s.stream_key = ?
    ");
    $stmt->bind_param("s", $stream_key);
    $stmt->execute();
    $result = $stmt->get_result();
    $stream = $result->fetch_assoc();
    $stmt->close();

    if (!$stream) {
        die("Invalid stream key.");
    }

    $now = new DateTime();
    $crusade_start_time = !empty($stream['crusade_start_time']) ? new DateTime($stream['crusade_start_time']) : null;
    $crusade_end_time = !empty($stream['crusade_end_time']) ? new DateTime($stream['crusade_end_time']) : null;

    if ($crusade_end_time && $now >= $crusade_end_time) {
        if ((int)$stream['crusade_is_active'] === 1) {
            $stmt_end = $conn->prepare("UPDATE praise_crusades SET is_active = 0 WHERE id = ?");
            $stmt_end->bind_param("i", $stream['crusade_id']);
            $stmt_end->execute();
            $stmt_end->close();
        }
        $stmt_end_share = $conn->prepare("UPDATE praise_user_shared_streams SET is_active = 0 WHERE stream_key = ?");
        $stmt_end_share->bind_param("s", $stream_key);
        $stmt_end_share->execute();
        $stmt_end_share->close();
        header('Location: index.php?crusade_ended=true');
        exit;
    }

    if ((int)$stream['crusade_is_active'] === 0 && $crusade_start_time && $now >= $crusade_start_time && (!$crusade_end_time || $now < $crusade_end_time)) {
        $stmt_start = $conn->prepare("UPDATE praise_crusades SET is_active = 1 WHERE id = ?");
        $stmt_start->bind_param("i", $stream['crusade_id']);
        $stmt_start->execute();
        $stmt_start->close();
        $stream['crusade_is_active'] = 1;
    }

    // Check if the shared stream is active
    if ($stream['shared_stream_is_active'] == 0) {
        die("This shared stream has ended.");
    }

    // Check if the underlying crusade is active
    if ($stream['crusade_is_active'] == 0) {
        header('Location: index.php?crusade_ended=true'); // Redirect if the main crusade is inactive
        exit;
    }

    $crusade_title = htmlspecialchars($stream['crusade_title']);
    $crusade_description = $stream['crusade_description'] ?? '';
    $shared_by_raw = $stream['shared_by_username'] ?? 'Admin';
    $shared_by = htmlspecialchars($shared_by_raw);
    $video_url = $stream['original_video_url'];
    $stream_source_id = $stream['crusade_id']; // Use crusade ID for logging

    // If the original URL is the placeholder for an official stream, we must get the CURRENT live video URL
    if (strpos($video_url, 'LIVE_STREAM_OFFICIAL_') === 0) {
        $official_stream_id = (int)str_replace('LIVE_STREAM_OFFICIAL_', '', $video_url);
        $live_stmt = $conn->prepare("SELECT stream_url FROM praise_live_tv WHERE id = ?");
        $live_stmt->bind_param("i", $official_stream_id);
        $live_stmt->execute();
        $live_result = $live_stmt->get_result();
        if ($live_row = $live_result->fetch_assoc()) {
            $video_url = $live_row['stream_url']; // Override with the current live URL
        } else {
            $video_url = ''; // Official stream not found or inactive
        }
        $live_stmt->close();
    } else if ($video_url === 'LIVE_STREAM_OFFICIAL') { // Fallback for the older placeholder
        $live_stmt = $conn->prepare("SELECT stream_url FROM praise_live_tv WHERE is_live = 1 ORDER BY created_at DESC LIMIT 1");
        $live_stmt->execute();
        $live_result = $live_stmt->get_result();
        if ($live_row = $live_result->fetch_assoc()) {
            $video_url = $live_row['stream_url']; // Override with the current live URL
        } else {
            $video_url = ''; // No official stream is live right now
        }
        $live_stmt->close();
    }
} else {
    die("No stream source provided.");
}

$page_title = $crusade_title;
if ($stream_type === 'shared') {
    $page_title = $crusade_title . ' — Watch Party by ' . $shared_by_raw;
}
$page_description = trim((string)$crusade_description);
if ($page_description === '') {
    if ($stream_type === 'shared') {
        $page_description = "{$shared_by_raw} shared a link for you to join their Crusade: {$crusade_title}.";
    } else {
        $page_description = "Watch {$crusade_title} live.";
    }
}
$page_image = base_url_local() . '/logo.png';


// Basic video embed logic
$is_youtube = false;
$direct_video_url = '';
if (!empty($video_url)) {
    if (strpos($video_url, 'youtube.com/watch') !== false || strpos($video_url, 'youtu.be/') !== false) {
        $video_id = '';
        if (strpos($video_url, 'youtube.com/watch') !== false) {
            $query = parse_url($video_url, PHP_URL_QUERY);
            parse_str($query ?? '', $params);
            $video_id = $params['v'] ?? '';
        } else {
            $video_id = ltrim(parse_url($video_url, PHP_URL_PATH), '/');
        }
        if (!empty($video_id)) {
            $embed_url = 'https://www.youtube.com/embed/' . $video_id;
            $is_youtube = true;
        }
    }
    if (!$is_youtube) {
        $direct_video_url = $video_url;
    }
}

// Log the visit (dedupe within 5 minutes per IP + stream)
$stat_id = 0;
$ip_address = $_SERVER['REMOTE_ADDR'];
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
}

$dummy_stream_key = ($stream_type == 'shared') ? $stream_key : 'DIRECT_LIVE_TV';

$stmt_check = $conn->prepare("SELECT id FROM praise_crusade_stats WHERE crusade_id = ? AND stream_key = ? AND ip_address = ? AND created_at >= (NOW() - INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1");
$stmt_check->bind_param("iss", $stream_source_id, $dummy_stream_key, $ip_address);
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

    $stmt_log = $conn->prepare("INSERT INTO praise_crusade_stats (crusade_id, stream_key, ip_address, user_agent, country, region, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt_log->bind_param("isssss", $stream_source_id, $dummy_stream_key, $ip_address, $_SERVER['HTTP_USER_AGENT'], $country, $region);
    $stmt_log->execute();
    $stat_id = $stmt_log->insert_id;
    $stmt_log->close();
}
?>
<?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="assets/css/watch.css?v=<?php echo time(); ?>">

<body class="cinema-mode">
    <?php include 'includes/navbar.php'; ?>

    <div class="watch-page-wrapper">
        <div class="cinema-container container-fluid">
            <!-- Video Column -->
            <div class="video-column">
                <div class="video-player-wrapper">
                    <?php if ($is_youtube && !empty($embed_url)): ?>
                        <div class="ratio ratio-16x9">
                            <iframe src="<?php echo htmlspecialchars($embed_url); ?>?autoplay=1" title="Stream Video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        </div>
                    <?php elseif (!empty($direct_video_url)): ?>
                        <div class="ratio ratio-16x9">
                            <video id="videoPlayer" controls class="w-100" autoplay></video>
                        </div>
                    <?php else: ?>
                        <div class="p-5 text-center">
                            <div class="alert alert-warning d-inline-block">
                                <i class="fas fa-exclamation-triangle me-2"></i> The live stream is not active at the moment.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="stream-info-bar">
                    <?php if ($stream_type == 'shared'): ?>
                        <span class="shared-info-badge">
                            <i class="fas fa-users me-1"></i> Watch Party by <?php echo $shared_by; ?>
                        </span>
                    <?php endif; ?>
                    <h1 class="stream-title"><?php echo $crusade_title; ?></h1>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($crusade_description)); ?></p>
                </div>

                <!-- Floating Action Bar -->
                <div class="action-strip">
                    <button type="button" class="btn btn-cinema btn-give-life" data-bs-toggle="modal" data-bs-target="#giveLifeModal" <?php echo ($stream_type == 'shared') ? 'data-crusade-code="'.htmlspecialchars($stream['crusade_code']).'"' : ''; ?>>
                        <i class="fas fa-heart me-2"></i> Give Your Life to Christ
                    </button>
                    <button type="button" class="btn btn-cinema btn-partnership" data-bs-toggle="modal" data-bs-target="#givingModal">
                        <i class="fas fa-hand-holding-heart me-2"></i> Offerings & Partnership
                    </button>
                </div>

                <?php if (isset($_SESSION['user_id']) && $stream_type == 'shared' && $_SESSION['user_id'] == $stream['user_id']) : ?>
                    <div class="host-controls">
                        <h5 class="text-center mb-3 text-muted small uppercase">Host Controls: Share this party</h5>
                        <div class="d-flex justify-content-center flex-wrap gap-2">
                            <button id="copy-link-btn" class="btn btn-sm btn-outline-light"><i class="fas fa-copy me-1"></i> Copy Link</button>
                            <div class="social-icons-row">
                                <a id="facebook-share-btn" href="#" class="social-icon-btn bg-facebook" target="_blank"><i class="fab fa-facebook-f"></i></a>
                                <a id="twitter-share-btn" href="#" class="social-icon-btn bg-twitter" target="_blank"><i class="fab fa-twitter"></i></a>
                                <a id="whatsapp-share-btn" href="#" class="social-icon-btn bg-whatsapp" target="_blank"><i class="fab fa-whatsapp"></i></a>
                            </div>
                            <button id="end-share-btn" class="btn btn-sm btn-danger"><i class="fas fa-stop-circle me-1"></i> End Party</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar Column -->
            <div class="sidebar-column">
                <div class="p-3 border-bottom border-secondary">
                    <h5 class="mb-0"><i class="fas fa-comments me-2 text-primary"></i> Live Interactions</h5>
                </div>
                <div class="comment-section-container">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="alert alert-dark small py-2 mb-3">
                            <a href="login.php" class="text-primary fw-bold">Log in</a> to join the conversation.
                        </div>
                    <?php endif; ?>
                    <div id="comments-section"></div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script> <!-- HLS.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var video = document.getElementById('videoPlayer');
            var videoSrc = '<?php echo htmlspecialchars($direct_video_url); ?>';

            if (!video || !videoSrc) {
                return;
            }

            if (videoSrc.includes('.m3u8') && Hls.isSupported()) {
                var hls = new Hls();
                hls.loadSource(videoSrc);
                hls.attachMedia(video);
                hls.on(Hls.Events.MANIFEST_PARSED, function() {
                    video.play();
                });
            } else if (video.canPlayType('video/mp4') || video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = videoSrc;
                video.addEventListener('loadedmetadata', function() {
                    video.play();
                });
            }
        });
    </script>
    <script src="N-gage/Ngage/Ngage.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($stream_type == 'shared' && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $stream['user_id']) : ?>
                const shareUrl = new URL(window.location.href);
                shareUrl.searchParams.delete('shared');
                const shareUrlString = shareUrl.toString();
                const crusadeTitle = "<?php echo htmlspecialchars($crusade_title, ENT_QUOTES); ?>";
                const sharedBy = "<?php echo htmlspecialchars($shared_by_raw, ENT_QUOTES); ?>";
                const shareText = `Join ${sharedBy}'s watch party for ${crusadeTitle}`;

                // Copy Link
                const copyBtn = document.getElementById('copy-link-btn');
                if (copyBtn) {
                    copyBtn.addEventListener('click', function() {
                        const onSuccess = () => Toastify({
                            text: "Watch party link copied!",
                            duration: 2500,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
                        }).showToast();
                        const onFailure = () => Toastify({
                            text: "Could not copy link. Please try again.",
                            duration: 2500,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "linear-gradient(to right, #ff5f6d, #ffc371)",
                        }).showToast();

                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(shareUrlString).then(onSuccess, onFailure);
                        } else {
                            const tempInput = document.createElement('input');
                            tempInput.value = shareUrlString;
                            document.body.appendChild(tempInput);
                            tempInput.select();
                            try {
                                document.execCommand('copy') ? onSuccess() : onFailure();
                            } catch (e) {
                                onFailure();
                            }
                            document.body.removeChild(tempInput);
                        }
                    });
                }

                // Facebook Share
                const facebookBtn = document.getElementById('facebook-share-btn');
                if (facebookBtn) {
                    facebookBtn.href = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrlString)}`;
                }

                // Twitter Share
                const twitterBtn = document.getElementById('twitter-share-btn');
                if (twitterBtn) {
                    twitterBtn.href = `https://twitter.com/intent/tweet?url=${encodeURIComponent(shareUrlString)}&text=${encodeURIComponent(shareText + ':')}`;
                }

                // WhatsApp Share
                const whatsappBtn = document.getElementById('whatsapp-share-btn');
                if (whatsappBtn) {
                    whatsappBtn.href = `https://api.whatsapp.com/send?text=${encodeURIComponent(shareText + ': ' + shareUrlString)}`;
                }

                const endShareBtn = document.getElementById('end-share-btn');
                if (endShareBtn) {
                    endShareBtn.addEventListener('click', function() {
                        if (!confirm('End this shared watch party?')) return;
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        const body = `stream_key=<?php echo isset($stream_key) ? htmlspecialchars($stream_key) : ''; ?>&csrf_token=${encodeURIComponent(csrfToken)}`;
                        fetch('end_shared_stream.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.success) {
                                Toastify({
                                    text: "Share ended.",
                                    duration: 2500,
                                    gravity: "top",
                                    position: "right",
                                    backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
                                }).showToast();
                                window.location.href = 'crusade_room.php?code=<?php echo isset($stream['crusade_code']) ? htmlspecialchars($stream['crusade_code']) : ''; ?>';
                            } else {
                                Toastify({
                                    text: data?.message || "Could not end share.",
                                    duration: 2500,
                                    gravity: "top",
                                    position: "right",
                                    backgroundColor: "linear-gradient(to right, #ff5f6d, #ffc371)",
                                }).showToast();
                            }
                        })
                        .catch(() => {
                            Toastify({
                                text: "Could not end share.",
                                duration: 2500,
                                gravity: "top",
                                position: "right",
                                backgroundColor: "linear-gradient(to right, #ff5f6d, #ffc371)",
                            }).showToast();
                        });
                    });
                }
            <?php endif; ?>
        });
    </script>
    <script>
        const myCommentBox = new Ngage({
            anchor: "#comments-section",
            // Use the stream_key for a unique comment section for shared watch parties, or a unique ID for live_tv
            id: "<?php echo htmlspecialchars($stream_type == 'shared' ? $stream_key : 'live_tv_' . $stream_source_id); ?>",
            userID: "<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'default'; ?>",
            username: "<?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'default'; ?>",
            endpoint: "N-gage/Ngage/Ngage.php"
        });
        myCommentBox.init();
    </script>
    <script>
        function showToast(message, success = true) {
            Toastify({
                text: message,
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: success ? "linear-gradient(to right, #00b09b, #96c93d)" : "linear-gradient(to right, #ff5f6d, #ffc371)",
            }).showToast();
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
                console.log('Engagement tracked:', data);
            })
            .catch(error => {
                console.error('Error tracking engagement:', error);
            });
        }, 60000); // 1 minute
    </script>
</body>

</html>
