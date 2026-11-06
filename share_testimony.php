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

require_once 'includes/db.php';
include 'includes/header.php';

// Fetch user data for pre-filling the form
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, country FROM praise_users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

$full_name = htmlspecialchars($user_data['username'] ?? ''); // Assuming username for full name
$email = htmlspecialchars($user_data['email'] ?? '');
$country = htmlspecialchars($user_data['country'] ?? '');

$conn->close(); // Close connection after fetching user data

// Error/Success messages from form submission
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="main-content-area d-flex justify-content-center align-items-center">
        <div class="container">
            <div class="card p-4 mx-auto" style="max-width: 800px;">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Share Your Testimony</h2>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> text-center">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <form action="process_testimony.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="full_name" class="form-control form-control-dark" value="<?php echo $full_name; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control form-control-dark" value="<?php echo $email; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="country" class="form-label">Country</label>
                            <input type="text" name="country" id="country" class="form-control form-control-dark" value="<?php echo $country; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="testimony_text" class="form-label">Your Testimony</label>
                            <textarea name="testimony_text" id="testimony_text" class="form-control form-control-dark" rows="8" required></textarea>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary-custom">Submit Testimony</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
</body>
