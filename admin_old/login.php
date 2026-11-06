<?php
ini_set("display_errors", 1);
require_once __DIR__ . '/../includes/session.php';
session_init();

require_once '../includes/db.php';

$username = $password = "";
$username_err = $password_err = $login_err = $csrf_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $csrf_err = "Invalid session. Please refresh and try again.";
    }

    $client_ip = get_client_ip();
    $rate_key = 'admin_login:' . $client_ip;
    if (empty($csrf_err) && !rate_limit_allow($conn, $rate_key, 5, 900)) {
        $login_err = "Too many login attempts. Please try again later.";
    }

    if (empty($csrf_err) && empty($login_err) && empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } elseif (empty($csrf_err) && empty($login_err)) {
        $username = trim($_POST["username"]);
    }

    if (empty($csrf_err) && empty($login_err) && empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } elseif (empty($csrf_err) && empty($login_err)) {
        $password = trim($_POST["password"]);
    }

    if (empty($csrf_err) && empty($login_err) && empty($username_err) && empty($password_err)) {
        $sql = "SELECT id, username, password FROM praise_admins WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = $username;
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $username, $hashed_password);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            rate_limit_reset($conn, $rate_key);
                            $_SESSION["admin_logged_in"] = true;
                            $_SESSION["admin_id"] = $id;
                            $_SESSION["admin_username"] = $username;
                            header("location: index.php");
                        } else {
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    $login_err = "Invalid username or password.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="icon" href="../logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #1de9b6;
            --dark-color: #263238;
            --light-color: #f4f7f6;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-container {
            max-width: 400px;
            width: 100%;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
        }

        .card-header {
            background-color: var(--primary-color);
            color: #fff;
            text-align: center;
            padding: 30px;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }

        .card-header h3 {
            font-weight: 700;
        }

        .card-body {
            padding: 40px;
        }

        .form-control {
            border-radius: 30px;
            padding: 12px 20px;
            border: 1px solid #ddd;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: none;
        }

        .btn-primary-custom {
            background-color: var(--primary-color);
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            background-color: #0056b3;
        }

        .invalid-feedback {
            margin-left: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <h3>Admin Login</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($login_err)): ?>
                    <div class="alert alert-danger"><?php echo $login_err; ?></div>
                <?php endif; ?>
                <?php if (!empty($csrf_err)): ?>
                    <div class="alert alert-danger"><?php echo $csrf_err; ?></div>
                <?php endif; ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <div class="mb-3">
                        <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>" placeholder="Username">
                        <div class="invalid-feedback"><?php echo $username_err; ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fa fa-eye-slash" aria-hidden="true"></i>
                            </button>
                            <div class="invalid-feedback"><?php echo $password_err; ?></div>
                        </div>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary-custom">Login</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');

            if (togglePassword && password) {
                togglePassword.addEventListener('click', function () {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    const eyeIcon = this.querySelector('i');
                    eyeIcon.classList.toggle('fa-eye');
                    eyeIcon.classList.toggle('fa-eye-slash');
                });
            }
        });
    </script>
</body>
</html>
