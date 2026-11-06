<?php
ini_set("display_errors", 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once __DIR__ . '/../includes/session.php';
    session_init();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$email = $password = $confirm_password = "";
$email_err = $password_err = $confirm_password_err = $csrf_err = $action_err = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $csrf_err = "Invalid session. Please refresh and try again.";
    }

    $client_ip = get_client_ip();
    $rate_key = 'admin_reset_pw:' . ($_SESSION['admin_id'] ?? 'unknown') . ':' . $client_ip;
    if (empty($csrf_err) && !rate_limit_allow($conn, $rate_key, 10, 900)) {
        $action_err = "Too many reset attempts. Please try again later.";
    }

    if (empty($csrf_err) && empty($action_err) && empty(trim($_POST["email"]))) {
        $email_err = "Please enter the user's email.";
    } elseif (empty($csrf_err) && empty($action_err)) {
        $email = trim($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        }
    }

    if (empty($csrf_err) && empty($action_err) && empty(trim($_POST["password"]))) {
        $password_err = "Please enter a new password.";
    } elseif (empty($csrf_err) && empty($action_err) && strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } elseif (empty($csrf_err) && empty($action_err)) {
        $password = trim($_POST["password"]);
    }

    if (empty($csrf_err) && empty($action_err) && empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm the new password.";
    } elseif (empty($csrf_err) && empty($action_err)) {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password !== $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    if (empty($csrf_err) && empty($action_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {
        $stmt = $conn->prepare("SELECT id, username FROM praise_users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $email_err = "No user found with that email.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt_update = $conn->prepare("UPDATE praise_users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
            $stmt_update->bind_param("si", $hashed, $user['id']);
            if ($stmt_update->execute()) {
                $success_message = "Password reset for " . htmlspecialchars($user['username']) . " (" . htmlspecialchars($email) . ").";
                rate_limit_reset($conn, $rate_key);
                $email = $password = $confirm_password = "";
            } else {
                $action_err = "Failed to reset the password. Please try again.";
                if (function_exists('logDatabaseError')) {
                    logDatabaseError("UPDATE praise_users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?", $stmt_update->error);
                }
            }
            $stmt_update->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Reset User Password</title>
    <link rel="icon" href="../logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7f6;
        }
        .admin-container {
            padding: 80px 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="admin-container container">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-3">Reset User Password</h2>
                        <p class="text-center text-muted">Enter the user's email and set a new password.</p>

                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($action_err)): ?>
                            <div class="alert alert-danger"><?php echo $action_err; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($csrf_err)): ?>
                            <div class="alert alert-danger"><?php echo $csrf_err; ?></div>
                        <?php endif; ?>

                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <div class="mb-3">
                                <label for="email" class="form-label">User Email</label>
                                <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" placeholder="user@example.com">
                                <div class="invalid-feedback"><?php echo $email_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="At least 6 characters">
                                <div class="invalid-feedback"><?php echo $password_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Retype the new password">
                                <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
