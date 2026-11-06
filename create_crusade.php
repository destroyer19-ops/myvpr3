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

// Check user's subscription status
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT end_date FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$subscription = $result->fetch_assoc();
$stmt->close();

$is_subscribed = false;
$subscription_error = 'not_subscribed_no_record'; // Default error if no record found

if ($subscription) {
    $end_date = new DateTime($subscription['end_date']);
    $now = new DateTime();
    if ($end_date > $now) {
        $is_subscribed = true;
    } else {
        $subscription_error = 'not_subscribed_expired'; // Subscription found but expired
    }
}

// Update the user's subscription_status in praise_users table for consistency
$new_status = $is_subscribed ? 'active' : 'inactive';
$stmt_update = $conn->prepare("UPDATE praise_users SET subscription_status = ? WHERE id = ?");
$stmt_update->bind_param("si", $new_status, $user_id);
$stmt_update->execute();
$stmt_update->close();


if (!$is_subscribed) {
    header('Location: subscribe.php?error=' . $subscription_error);
    exit;
}

$title = $description = $video_url = "";
$title_err = $description_err = $video_url_err = $schedule_err = "";

// Fetch live streams for the dropdown
$streams_sql = "SELECT id, title FROM praise_live_tv WHERE is_live = 1 ORDER BY title ASC";
$streams_result = $conn->query($streams_sql);


if (isset($_GET['video_url'])) {
    $video_url = $_GET['video_url'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $title_err = "Invalid session. Please refresh and try again.";
    }
    // Validate title
    if (empty($title_err) && empty(trim($_POST["title"]))) {
        $title_err = "Please enter a title for the crusade.";
    } elseif (empty($title_err)) {
        $title = trim($_POST["title"]);
    }

    // Validate description
    if (empty($title_err) && empty(trim($_POST["description"]))) {
        $description_err = "Please enter a description for the crusade.";
    } elseif (empty($title_err)) {
        $description = trim($_POST["description"]);
    }

    $selected_live_stream_id = $_POST['live_stream_id'] ?? '';
    $manual_video_url = trim($_POST['video_url'] ?? '');

    if (empty($selected_live_stream_id) && empty($manual_video_url)) {
        $video_url_err = "Please select an official live stream or provide a video URL.";
    } elseif (!empty($manual_video_url) && !filter_var($manual_video_url, FILTER_VALIDATE_URL)) {
        $video_url_err = "Please enter a valid video URL.";
    } elseif (!empty($selected_live_stream_id)) {
        // Extract the ID from the selected option (e.g., "official_stream_123")
        if (strpos($selected_live_stream_id, 'official_stream_') === 0) {
            $live_stream_db_id = (int)str_replace('official_stream_', '', $selected_live_stream_id);
            $video_url = 'LIVE_STREAM_OFFICIAL_' . $live_stream_db_id; // Store the reference string
        } else {
            // This case should ideally not happen if options are well-formed
            $video_url_err = "Invalid official live stream selection.";
        }
    } elseif (!empty($manual_video_url)) {
        $video_url = $manual_video_url;
    }

    $start_time = NULL;
    $end_time = NULL;
    $is_active = 1;

    if (isset($_POST['schedule_crusade'])) {
        if (empty(trim($_POST["start_time"]))) {
            $schedule_err = "Please provide a start time for the scheduled crusade.";
        } else {
            $start_time = trim($_POST["start_time"]);
            $is_active = 0; // It's scheduled, so not active yet
        }

        if (!empty(trim($_POST["end_time"]))) {
            $end_time = trim($_POST["end_time"]);
            if (!empty($start_time) && strtotime($end_time) <= strtotime($start_time)) {
                $schedule_err = "End time must be after the start time.";
            }
        }
    }
    
    if (empty($title_err) && empty($description_err) && empty($video_url_err) && empty($schedule_err)) {
        $crusade_code = bin2hex(random_bytes(8));
        $user_id = $_SESSION['user_id'];

        $sql = "INSERT INTO praise_crusades (user_id, title, description, crusade_code, video_url, created_at, is_active, start_time, end_time) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("issssiss", $user_id, $title, $description, $crusade_code, $video_url, $is_active, $start_time, $end_time);

            if ($stmt->execute()) {
                if ($is_active) {
                    $_SESSION['current_crusade_code'] = $crusade_code;
                    header("location: crusade_room.php?code=" . $crusade_code . "&success=true");
                } else {
                    header("location: dashboard.php?scheduled=true");
                }
                exit;
            }
            else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }

    
}
?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="create-crusade-container container">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card cta-block">
                    <div class="card-body">
                        <h2 class="card-title text-center">Create a New Crusade</h2>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" name="title" class="form-control form-control-dark <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $title; ?>">
                                <div class="invalid-feedback"><?php echo $title_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" class="form-control form-control-dark <?php echo (!empty($description_err)) ? 'is-invalid' : ''; ?>"><?php echo $description; ?></textarea>
                                <div class="invalid-feedback"><?php echo $description_err; ?></div>
                            </div>

                            <div class="mb-3">
                                <label for="live_stream_id" class="form-label">Official Live Stream</label>
                                <select name="live_stream_id" id="live_stream_id" class="form-select form-control-dark">
                                    <option value="">-- None --</option>
                                    <?php
                                    if ($streams_result->num_rows > 0) {
                                        while($row = $streams_result->fetch_assoc()) {
                                            echo "<option value='official_stream_" . $row["id"] . "'>" . htmlspecialchars($row["title"]) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="form-text text-muted d-block">Select an official live stream, or provide your own video URL below.</small>
                            </div>

                            <div class="mb-3" id="video_url_group">
                                <label for="video_url" class="form-label">Video URL</label>
                                <div class="input-group">
                                    <input type="text" name="video_url" id="video_url" class="form-control form-control-dark <?php echo (!empty($video_url_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $video_url; ?>">
                                    <a href="videos.php" class="btn btn-outline-light">Select from library</a>
                                </div>
                                <div class="invalid-feedback"><?php echo $video_url_err; ?></div>
                            </div>
                            <div id="video_preview" class="mb-3" style="display: none;">
                                <label class="form-label">Preview</label>
                                <div class="ratio ratio-16x9" id="video_preview_iframe" style="display: none;">
                                    <iframe title="Video Preview" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                </div>
                                <div id="video_preview_player" style="display: none;">
                                    <video id="previewVideo" controls class="w-100"></video>
                                </div>
                                <small class="form-text text-muted d-block mt-2" id="video_preview_note"></small>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="schedule_crusade" id="schedule_crusade">
                                <label class="form-check-label" for="schedule_crusade">
                                    Schedule Crusade
                                </label>
                            </div>

                            <div id="scheduling_fields" style="display: none;">
                                <div class="mb-3">
                                    <label for="start_time" class="form-label">Start Time</label>
                                    <input type="datetime-local" name="start_time" id="start_time" class="form-control form-control-dark">
                                </div>
                                <div class="mb-3">
                                    <label for="end_time" class="form-label">End Time</label>
                                    <input type="datetime-local" name="end_time" id="end_time" class="form-control form-control-dark">
                                </div>
                                <?php if (!empty($schedule_err)): ?>
                                    <div class="alert alert-danger"><?php echo $schedule_err; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary-custom">Create Crusade</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const scheduleCheckbox = document.getElementById('schedule_crusade');
            const schedulingFields = document.getElementById('scheduling_fields');
            const liveStreamSelect = document.getElementById('live_stream_id');
            const videoUrlGroup = document.getElementById('video_url_group');
            const videoUrlInput = document.getElementById('video_url');
            const previewContainer = document.getElementById('video_preview');
            const previewIframeWrap = document.getElementById('video_preview_iframe');
            const previewIframe = previewIframeWrap ? previewIframeWrap.querySelector('iframe') : null;
            const previewPlayerWrap = document.getElementById('video_preview_player');
            const previewVideo = document.getElementById('previewVideo');
            const previewNote = document.getElementById('video_preview_note');

            scheduleCheckbox.addEventListener('change', function() {
                schedulingFields.style.display = this.checked ? 'block' : 'none';
            });

            function toggleVideoUrlInput() {
                if (liveStreamSelect.value) {
                    videoUrlGroup.style.display = 'none';
                    previewContainer.style.display = 'none';
                } else {
                    videoUrlGroup.style.display = 'block';
                    updatePreview();
                }
            }

            liveStreamSelect.addEventListener('change', toggleVideoUrlInput);
            toggleVideoUrlInput(); // Initial check

            function updatePreview() {
                if (!videoUrlInput || !previewContainer) return;
                const url = (videoUrlInput.value || '').trim();
                if (!url) {
                    previewContainer.style.display = 'none';
                    return;
                }

                const isYouTube = url.includes('youtube.com/watch') || url.includes('youtu.be/');
                previewContainer.style.display = 'block';

                if (isYouTube && previewIframe && previewIframeWrap) {
                    let videoId = '';
                    if (url.includes('youtube.com/watch')) {
                        const query = url.split('?')[1] || '';
                        const params = new URLSearchParams(query);
                        videoId = params.get('v') || '';
                    } else {
                        videoId = url.split('/').pop();
                    }
                    if (videoId) {
                        previewIframe.src = `https://www.youtube.com/embed/${videoId}`;
                        previewIframeWrap.style.display = 'block';
                        if (previewPlayerWrap) previewPlayerWrap.style.display = 'none';
                        if (previewNote) previewNote.textContent = 'YouTube preview.';
                    }
                    return;
                }

                if (previewPlayerWrap && previewVideo) {
                    previewIframeWrap.style.display = 'none';
                    previewPlayerWrap.style.display = 'block';
                    previewVideo.src = url;
                    if (previewNote) previewNote.textContent = 'Direct video preview (HLS/MP4).';
                }
            }

            if (videoUrlInput) {
                videoUrlInput.addEventListener('input', updatePreview);
                updatePreview();
            }
        });

        <?php if (isset($_GET['success']) && $_GET['success'] == 'true'): ?>
            Toastify({
                text: "Crusade created successfully!",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
            }).showToast();
        <?php endif; ?>
    </script>
    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
