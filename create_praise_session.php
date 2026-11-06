<?php
require_once 'includes/session.php';
session_init();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';

$title = $duration = "";
$title_err = $duration_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $title_err = "Invalid session. Please refresh and try again.";
    }
    // Validate title
    if (empty($title_err) && empty(trim($_POST["title"]))) {
        $title_err = "Please enter a session title.";
    } elseif (empty($title_err)) {
        $title = trim($_POST["title"]);
    }

    // Validate duration
    if (empty($title_err) && empty(trim($_POST["duration"]))) {
        $duration_err = "Please select a duration.";
    } elseif (empty($title_err)) {
        $duration = (int)$_POST["duration"];
    }

    // If no errors, proceed to create session
    if (empty($title_err) && empty($duration_err)) {
        $user_id = $_SESSION['user_id'];
        $session_code = bin2hex(random_bytes(16)); // Generate a unique session code

        $sql = "INSERT INTO praise_sessions (user_id, title, duration, session_code) VALUES (?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("isss", $user_id, $title, $duration, $session_code);

            if ($stmt->execute()) {
                header("Location: praise_session_room.php?code=" . $session_code);
                exit;
            } else {
                echo "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>
<?php include 'includes/header.php'; ?>
    <?php include 'includes/navbar.php'; ?>
    <div class="create-praise-session-container">
        <div class="container">
            <div class="card p-4">
                <h2 class="card-title text-center mb-4">Create Meeting</h2>
                <form id="createSessionForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <div class="mb-3">
                        <label for="sessionTitle" class="form-label">Meeting Title</label>
                        <input type="text" class="form-control form-control-dark <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" id="sessionTitle" name="title" required value="<?php echo htmlspecialchars($title); ?>">
                        <div class="invalid-feedback"><?php echo $title_err; ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="sessionDuration" class="form-label">Duration (minutes)</label>
                        <select class="form-select form-control-dark <?php echo (!empty($duration_err)) ? 'is-invalid' : ''; ?>" id="sessionDuration" name="duration">
                            <option value="">Select Duration</option>
                            <option value="15" <?php echo ($duration == 15) ? 'selected' : ''; ?>>15 Minutes</option>
                            <option value="30" <?php echo ($duration == 30) ? 'selected' : ''; ?>>30 Minutes</option>
                            <option value="60" <?php echo ($duration == 60) ? 'selected' : ''; ?>>60 Minutes</option>
                        </select>
                        <div class="invalid-feedback"><?php echo $duration_err; ?></div>
                    </div>
                    <button type="submit" class="btn btn-primary-custom w-100 mt-3">Start Meeting</button>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
