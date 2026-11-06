<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}

require_once 'includes/db.php';

$token = $email = "";
$password_err = $confirm_password_err = $reset_error = $csrf_err = "";
$valid_request = false;

// Validate the token and email from the URL
if (isset($_GET["token"]) && isset($_GET["email"])) {
    $token = sanitize($_GET["token"]);
    $email = sanitize($_GET["email"]);

    // Find the user by email and check the token
    $sql = "SELECT id, reset_token, reset_expiry FROM praise_users WHERE email = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $param_email);
        $param_email = $email;

        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($user_id, $hashed_reset_token, $reset_expiry);
                $stmt->fetch();

                // Check if token is valid and not expired
                if (password_verify($token, $hashed_reset_token) && strtotime($reset_expiry) > time()) {
                    $valid_request = true;
                } else {
                    $reset_error = "Invalid or expired reset link. Please try again.";
                }
            } else {
                $reset_error = "Invalid reset link.";
            }
        } else {
            error_log("Error executing token check: " . $stmt->error);
            $reset_error = "Oops! Something went wrong. Please try again later.";
        }
        $stmt->close();
    }
} else {
    $reset_error = "Missing reset token or email.";
}


// Process form submission for new password
if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_request) {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $csrf_err = "Invalid session. Please refresh and try again.";
    }
    // Validate password
    if (empty($csrf_err) && empty(trim($_POST["password"]))) {
        $password_err = "Please enter a new password.";
    } elseif (empty($csrf_err) && strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } elseif (empty($csrf_err)) {
        $new_password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty($csrf_err) && empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm the new password.";
    } elseif (empty($csrf_err)) {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($new_password != $confirm_password)) {
            $confirm_password_err = "Passwords did not match.";
        }
    }

    // Check input errors before updating password
    if (empty($csrf_err) && empty($password_err) && empty($confirm_password_err)) {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password and clear reset token
        $update_sql = "UPDATE praise_users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?";
        if ($stmt = $conn->prepare($update_sql)) {
            $stmt->bind_param("si", $hashed_password, $user_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Your password has been reset successfully. Please log in with your new password.";
                header("Location: login.php");
                exit;
            } else {
                error_log("Error updating password: " . $stmt->error);
                $reset_error = "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>

<?php include 'includes/header.php'; ?>
<body class="d-flex justify-content-center align-items-center min-vh-100">
    <div class="login-container page-container">
        <div class="card cta-block">
            <div class="card-header text-center">
                <h3>Reset Your Password</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($csrf_err)): ?>
                    <div class="alert alert-danger"><?php echo $csrf_err; ?></div>
                <?php endif; ?>
                <?php if (!empty($reset_error)): ?>
                    <div class="alert alert-danger"><?php echo $reset_error; ?></div>
                <?php elseif ($valid_request): ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?token=" . urlencode($token) . "&email=" . urlencode($email); ?>" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" class="form-control form-control-dark <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fa fa-eye-slash" aria-hidden="true"></i>
                                </button>
                                <div class="invalid-feedback"><?php echo $password_err; ?></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-dark <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="fa fa-eye-slash" aria-hidden="true"></i>
                                </button>
                                <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                            </div>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary-custom">Set New Password</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const confirmPassword = document.getElementById('confirm_password');

            if (togglePassword && password) {
                togglePassword.addEventListener('click', function () {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    const eyeIcon = this.querySelector('i');
                    eyeIcon.classList.toggle('fa-eye');
                    eyeIcon.classList.toggle('fa-eye-slash');
                });
            }

            if (toggleConfirmPassword && confirmPassword) {
                toggleConfirmPassword.addEventListener('click', function () {
                    const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPassword.setAttribute('type', type);
                    const eyeIcon = this.querySelector('i');
                    eyeIcon.classList.toggle('fa-eye');
                    eyeIcon.classList.toggle('fa-eye-slash');
                });
            }
        });
    </script>
<?php include 'includes/footer.php'; ?>
</body>
</html>
