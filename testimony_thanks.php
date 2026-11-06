<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'includes/header.php';
include 'includes/navbar.php';

$message = $_SESSION['message'] ?? "Your testimony has been submitted. Thank you!";
$message_type = $_SESSION['message_type'] ?? "success";
unset($_SESSION['message']);
unset($_SESSION['message_type']);
?>
<body>
    <div class="main-content-area d-flex justify-content-center align-items-center">
        <div class="container">
            <div class="card p-4 mx-auto" style="max-width: 600px;">
                <div class="card-body text-center">
                    <h2 class="card-title mb-4">Testimony Submitted!</h2>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                    <p class="mt-4">We appreciate you sharing your story. It will be reviewed by our team.</p>
                    <a href="dashboard.php" class="btn btn-primary-custom mt-3">Go to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
