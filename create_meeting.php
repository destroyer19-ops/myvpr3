<?php
ini_set("display_errors", 1);
require_once 'includes/session.php';
session_init();

// Check if user or admin is logged in
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!isset($_SESSION['user_id']) && !$is_admin) {
    // For non-authenticated users, redirect to login page.
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';

// Resolve user_id for meeting attribution
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id === 0 && $is_admin) {
    // Admin is logged in, but no user_id in session. 
    // Attempt to find a user account for this admin or use the first available user.
    $admin_username = $_SESSION['admin_username'] ?? 'admin';
    $stmt_u = $conn->prepare("SELECT id FROM praise_users WHERE username = ? LIMIT 1");
    $stmt_u->bind_param("s", $admin_username);
    $stmt_u->execute();
    $res_u = $stmt_u->get_result();
    if ($row_u = $res_u->fetch_assoc()) {
        $user_id = $row_u['id'];
    } else {
        // Fallback: pick the first user in the database
        $res_f = $conn->query("SELECT id FROM praise_users ORDER BY id ASC LIMIT 1");
        if ($row_f = $res_f->fetch_assoc()) {
            $user_id = $row_f['id'];
        }
    }
    $stmt_u->close();
    
    // If still 0, we have a problem (no users in DB), but we'll proceed for now
}

// Check user's subscription status
$is_subscribed = true; // Temporary bypass for testing
if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT end_date FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscription = $result->fetch_assoc();
    $stmt->close();

    if ($subscription) {
        $end_date = new DateTime($subscription['end_date']);
        $now = new DateTime();
        if ($end_date > $now) {
            $is_subscribed = true;
        }
    }

    // Update the user's subscription_status in praise_users table for consistency
    $new_status = $is_subscribed ? 'active' : 'inactive';
    $stmt_update = $conn->prepare("UPDATE praise_users SET subscription_status = ? WHERE id = ?");
    $stmt_update->bind_param("si", $new_status, $user_id);
    $stmt_update->execute();
    $stmt_update->close();
}


if (!$is_subscribed && !$is_admin) {
    header('Location: subscribe.php?error=not_subscribed');
    exit;
}

require_once 'includes/config.php';

// Simple function to create Daily room via API
function createDailyRoom($title) {
    if (!defined('DAILY_API_KEY') || DAILY_API_KEY === 'YOUR_DAILY_API_KEY_HERE') {
        return ['error' => 'Daily.co API Key not configured.'];
    }

    $url = 'https://api.daily.co/v1/rooms';
    $data = [
        'properties' => [
            'exp' => time() + (defined('DAILY_ROOM_DURATION') ? DAILY_ROOM_DURATION : 86400),
            'enable_chat' => true,
            'enable_mesh_sfu' => true,
            'max_participants' => 200,
            'experimental_optimize_large_calls' => true
        ]
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n" .
                         "Authorization: Bearer " . DAILY_API_KEY . "\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'ignore_errors' => true
        ],
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        return ['error' => 'Failed to connect to Daily.co API'];
    }

    $response = json_decode($result, true);
    if (isset($response['error'])) {
        return ['error' => 'Daily API Error: ' . ($response['info'] ?? $response['error'])];
    }

    return $response;
}

$meeting_code = ''; // Initialize
$title = ''; // Initialize

// Handle POST request for meeting creation
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Only allow admins to create meetings during test phase
    if (!$is_admin) {
        $_SESSION['error_message'] = 'Self-service meeting creation is currently disabled. Please contact the admin.';
        header('Location: create_meeting.php');
        exit;
    }

    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid session. Please refresh and try again.';
        header('Location: create_meeting.php');
        exit;
    }
    $title = isset($_POST['title']) ? trim($_POST['title']) : 'Untitled Meeting';
    $platform = 'daily'; // Default to daily for test phase

    if ($platform === 'daily') {
        $daily_room = createDailyRoom($title);
        if (isset($daily_room['error'])) {
            $_SESSION['error_message'] = $daily_room['error'];
            header('Location: create_meeting.php');
            exit;
        }
        $room_url = $daily_room['url'];
        $meeting_code = $daily_room['name'];

        $stmt_insert = $conn->prepare("INSERT INTO praise_meetings (user_id, title, meeting_code, daily_room_url, start_time, status, created_at) VALUES (?, ?, ?, ?, NOW(), 'in-progress', NOW())");
        $stmt_insert->bind_param("isss", $user_id, $title, $meeting_code, $room_url);
        
        if ($stmt_insert->execute()) {
            $_SESSION['success_message'] = 'Daily.co Meeting created successfully!';
            $_SESSION['current_meeting_code'] = $meeting_code;
            header('Location: meeting-room-daily.php?code=' . $meeting_code);
            exit;
        } else {
            $_SESSION['error_message'] = 'Failed to save meeting: ' . $conn->error;
            header('Location: create_meeting.php');
            exit;
        }
    }

    // Generate a unique meeting code (8 characters)
    function generateMeetingCode($length = 8) {
        return substr(bin2hex(random_bytes(ceil($length / 2))), 0, $length);
    }

    $meeting_code = generateMeetingCode();
    $max_attempts = 10;
    $attempt = 0;

    $stmt_check_code = $conn->prepare("SELECT id FROM praise_meetings WHERE meeting_code = ?");

    while ($attempt < $max_attempts) {
        $stmt_check_code->bind_param("s", $meeting_code);
        $stmt_check_code->execute();
        $result_check_code = $stmt_check_code->get_result();

        if ($result_check_code->num_rows == 0) {
            break; // Code is unique
        }

        $meeting_code = generateMeetingCode();
        $attempt++;
    }
    $stmt_check_code->close();

    if ($attempt >= $max_attempts) {
        // Fallback if unique code generation fails
        $_SESSION['error_message'] = 'Failed to generate a unique meeting code. Please try again.';
        header('Location: create_meeting.php'); // Redirect back to form with error
        exit;
    }

    // Insert meeting into database
    $stmt_insert = $conn->prepare("INSERT INTO praise_meetings (user_id, title, meeting_code, start_time, status, created_at) VALUES (?, ?, ?, NOW(), 'in-progress', NOW())");
    $stmt_insert->bind_param("iss", $user_id, $title, $meeting_code);

    if ($stmt_insert->execute()) {
        $meeting_id = $stmt_insert->insert_id;
        $_SESSION['success_message'] = 'Meeting created successfully!';
        $_SESSION['current_meeting_code'] = $meeting_code; // Store for immediate access
        header('Location: meeting-room.php?code=' . $meeting_code); // Redirect to meeting room
        exit;
    } else {
        $_SESSION['error_message'] = 'Failed to create meeting: ' . $conn->error;
        header('Location: create_meeting.php'); // Redirect back to form with error
        exit;
    }
    $stmt_insert->close();
}

// Display the form for GET requests
?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white text-center">
                        <h2 class="mb-0">Create New Meeting</h2>
                    </div>
                    <div class="card-body p-4 text-center">
                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_admin): ?>
                            <form action="create_meeting.php" method="POST" class="text-start">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Meeting Title</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                                </div>
                                
                                <div class="mb-4 d-none">
                                    <label class="form-label d-block fw-bold text-muted small uppercase">Select Video Platform</label>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-check card p-3 h-100">
                                                <input class="form-check-input" type="radio" name="platform" id="platform_kings" value="kingsconference">
                                                <label class="form-check-label ms-2" for="platform_kings">
                                                    <div class="fw-bold">KingsConference</div>
                                                    <small class="text-muted">Standard platform.</small>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check card p-3 h-100 border-success">
                                                <input class="form-check-input" type="radio" name="platform" id="platform_daily" value="daily" checked>
                                                <label class="form-check-label ms-2" for="platform_daily">
                                                    <div class="fw-bold text-success">Daily.co <span class="badge bg-success">Beta</span></div>
                                                    <small class="text-muted">Cinematic Test.</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg">Create Meeting</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="py-4">
                                <div class="mb-4">
                                    <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                                    <h4>Meeting Setup is in Test Phase</h4>
                                    <p class="text-muted">Self-service meeting creation is temporarily disabled while we optimize our new cinematic platform. Please contact the administrator to set up your meeting.</p>
                                </div>
                                <div class="d-grid">
                                    <a href="https://kingschat.online/conversations/NjliNmUzZDEwYWMxZmM0MzIxNDdmOGU0" target="_blank" class="btn btn-primary btn-lg">
                                        <i class="fab fa-kickstarter me-2"></i> Contact Admin on KingsChat
                                    </a>
                                </div>
                                <p class="small text-muted mt-3">Admin will provide you with a unique meeting code once set up.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/bottom_navbar.php'; ?>
<?php include 'includes/footer.php'; ?>
