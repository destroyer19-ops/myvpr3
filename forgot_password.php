<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}

require_once 'includes/db.php';

$email_err = $success_message = $csrf_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $csrf_err = "Invalid session. Please refresh and try again.";
    }

    $client_ip = get_client_ip();
    $rate_key = 'forgot:' . $client_ip;
    if (empty($csrf_err) && !rate_limit_allow($conn, $rate_key, 5, 900)) {
        $email_err = "Too many requests. Please try again later.";
    }

    if (empty($csrf_err) && empty($email_err) && empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email address.";
    } elseif (empty($csrf_err) && empty($email_err)) {
        $email = sanitize(trim($_POST["email"]));

        // Check if email exists
        $sql = "SELECT id, username FROM praise_users WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = $email;

            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($user_id, $db_username);
                    $stmt->fetch();

                    // Generate a unique reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $hashed_reset_token = password_hash($reset_token, PASSWORD_DEFAULT);
                    $expiry_time = date("Y-m-d H:i:s", strtotime('+1 hour')); // Token valid for 1 hour

                    // Store token in the database
                    $update_sql = "UPDATE praise_users SET reset_token = ?, reset_expiry = ? WHERE id = ?";
                    if ($update_stmt = $conn->prepare($update_sql)) {
                        $update_stmt->bind_param("ssi", $hashed_reset_token, $expiry_time, $user_id);
                        if ($update_stmt->execute()) {
                            // Send email with reset link using Brevo
                            require_once 'includes/mail_helper.php';
                            
                            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $reset_token . "&email=" . urlencode($email);
                            
                            $subject = "Password Reset - Virtual Praise Room";
                            $html_content = "
                                <html>
                                <head>
                                    <title>Password Reset</title>
                                </head>
                                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                                    <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                                        <h2 style='color: #007bff; text-align: center;'>Virtual Praise Room</h2>
                                        <p>Hello <strong>" . htmlspecialchars($db_username) . "</strong>,</p>
                                        <p>You recently requested to reset your password for your Virtual Praise Room account associated with the username: <strong>" . htmlspecialchars($db_username) . "</strong>. Click the button below to reset it:</p>
                                        <div style='text-align: center; margin: 30px 0;'>
                                            <a href='$reset_link' style='background-color: #007bff; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Reset Your Password</a>
                                        </div>
                                        <p>If you did not request a password reset, please ignore this email. This link is valid for 1 hour.</p>
                                        <p>Best regards,<br>The Virtual Praise Room Team</p>
                                        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                                        <p style='font-size: 0.8rem; color: #888;'>If you're having trouble clicking the password reset button, copy and paste the URL below into your web browser:</p>
                                        <p style='font-size: 0.8rem; color: #888; word-break: break-all;'>$reset_link</p>
                                    </div>
                                </body>
                                </html>
                            ";

                            $result = send_brevo_email($email, $db_username, $subject, $html_content);

                            if ($result['success']) {
                                $success_message = "A password reset link has been sent to your email. Please check your inbox (and spam folder).";
                            } else {
                                // If email sending fails, we might still want to show a generic success for security, 
                                // but log the internal error.
                                error_log("Brevo Email Sending Failed: " . $result['message']);
                                $success_message = "If an account with that email address exists, a password reset link has been sent to your email. Please check your inbox.";
                            }

                        } else {
                            error_log("Error updating reset token: " . $update_stmt->error);
                            $email_err = "Oops! Something went wrong. Please try again later.";
                        }
                        $update_stmt->close();
                    }
                } else {
                    // Email not found, but we give a generic message for security reasons
                    $success_message = "If an account with that email address exists, a password reset link has been sent to your email. Please check your inbox (and spam folder).";
                }
            } else {
                error_log("Error executing email check: " . $stmt->error);
                $email_err = "Oops! Something went wrong. Please try again later.";
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
                <h3>Forgot Your Password?</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($csrf_err)): ?>
                    <div class="alert alert-danger"><?php echo $csrf_err; ?></div>
                <?php endif; ?>
                <?php if (!empty($email_err)): ?>
                    <div class="alert alert-danger"><?php echo $email_err; ?></div>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Enter your email address</label>
                        <input type="email" name="email" id="email" class="form-control form-control-dark <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" placeholder="Email address" required>
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary-custom">Reset Password</button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <p>Remembered your password? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'includes/footer.php'; ?>
</body>
</html>
