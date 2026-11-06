<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('session.use_only_cookies', 1);
// login.php
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}

require_once 'includes/config.php';

// Function to validate remember me cookie
function validateRememberMe($conn) {
    if (isset($_COOKIE['remember_me']) && !isset($_SESSION['user_id'])) {
        $cookie_value = $_COOKIE['remember_me'];
        if (strpos($cookie_value, ':') === false) {
            setcookie('remember_me', '', time() - 3600, '/');
            return false;
        }

        list($selector, $authenticator) = explode(':', $cookie_value, 2);
        if (!ctype_xdigit($selector) || !ctype_xdigit($authenticator)) {
            setcookie('remember_me', '', time() - 3600, '/');
            return false;
        }

        $sql = "SELECT id, username, password, account_type, remember_token, token_expiry FROM praise_users WHERE remember_selector = ? AND remember_token IS NOT NULL AND token_expiry > NOW() LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $selector);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if ($row && password_verify($authenticator, $row['remember_token'])) {
                // Valid token found, log the user in
                $_SESSION["loggedin"] = true;
                $_SESSION["user_id"] = $row['id'];
                $_SESSION["username"] = $row['username'];
                $_SESSION["account_type"] = $row['account_type'];

                // Regenerate new tokens to prevent stolen token attacks
                $new_selector = bin2hex(random_bytes(8));
                $new_authenticator = bin2hex(random_bytes(32));
                $new_hashed_authenticator = password_hash($new_authenticator, PASSWORD_DEFAULT);
                $new_expiry = date('Y-m-d H:i:s', strtotime('+30 days'));

                $update_token_sql = "UPDATE praise_users SET remember_selector = ?, remember_token = ?, token_expiry = ? WHERE id = ?";
                if ($stmt_update = $conn->prepare($update_token_sql)) {
                    $stmt_update->bind_param("sssi", $new_selector, $new_hashed_authenticator, $new_expiry, $row['id']);
                    $stmt_update->execute();
                    $stmt_update->close();

                    setcookie(
                        'remember_me',
                        $new_selector . ':' . $new_authenticator,
                        [
                            'expires' => strtotime('+30 days'),
                            'httponly' => true,
                            'secure' => true, // Set to true if using HTTPS
                            'samesite' => 'Lax',
                        ]
                    );
                }
                $stmt->close();
                return true;
            }
            $stmt->close();
        }

        if (!empty($selector)) {
            $clear_sql = "UPDATE praise_users SET remember_selector = NULL, remember_token = NULL, token_expiry = NULL WHERE remember_selector = ?";
            if ($stmt_clear = $conn->prepare($clear_sql)) {
                $stmt_clear->bind_param("s", $selector);
                $stmt_clear->execute();
                $stmt_clear->close();
            }
        }
        setcookie('remember_me', '', time() - 3600, '/');
    }
    return false;
}

// Include database connection
require_once 'includes/db.php';

// Try to log in with remember me cookie if not already logged in
validateRememberMe($conn);

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to intended page or default to index
    $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'index.php';
    unset($_SESSION['redirect_after_login']); // Clear the redirect
    header("Location: $redirect");
    exit;
}

// Initialize variables
$username = $password = "";
$username_err = $password_err = $login_err = $csrf_err = "";

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $csrf_err = "Invalid session. Please refresh and try again.";
    }

    $client_ip = get_client_ip();
    $rate_key = 'login:' . $client_ip . ':' . strtolower(trim($_POST["username"] ?? ''));
    if (empty($csrf_err) && !rate_limit_allow($conn, $rate_key, 5, 900)) {
        $login_err = "Too many login attempts. Please try again later.";
    }
    
    // Validate username
    if (empty($csrf_err) && empty($login_err) && empty(trim($_POST["username"]))) {
        $username_err = "Please enter your username.";
    } elseif (empty($csrf_err) && empty($login_err)) {
        $username = sanitize(trim($_POST["username"]));
    }
    
    // Validate password
    if (empty($csrf_err) && empty($login_err) && empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } elseif (empty($csrf_err) && empty($login_err)) {
        $password = trim($_POST["password"]);
    }
    
    // Check input errors before authenticating
    if (empty($csrf_err) && empty($login_err) && empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, username, password, account_type FROM praise_users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = $username;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                // Check if username exists, if yes then verify password
                if ($stmt->num_rows == 1) {
                    // Bind result variables
                    $stmt->bind_result($id, $username, $hashed_password, $account_type);
                    
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            rate_limit_reset($conn, $rate_key);
                            // Password is correct, store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["account_type"] = $account_type;

                            // Handle "Remember me" functionality
                            if (isset($_POST['remember_me']) && $_POST['remember_me'] == 'on') {
                                $selector = bin2hex(random_bytes(8));
                                $authenticator = bin2hex(random_bytes(32));

                                // Store the hash of the authenticator in the database
                                $hashed_authenticator = password_hash($authenticator, PASSWORD_DEFAULT);
                                $expiry = date('Y-m-d H:i:s', strtotime('+30 days')); // Token valid for 30 days

                                $update_token_sql = "UPDATE praise_users SET remember_selector = ?, remember_token = ?, token_expiry = ? WHERE id = ?";
                                if ($stmt_update = $conn->prepare($update_token_sql)) {
                                    $stmt_update->bind_param("sssi", $selector, $hashed_authenticator, $expiry, $id);
                                    $stmt_update->execute();
                                    $stmt_update->close();

                                    // Set the cookie
                                    setcookie(
                                        'remember_me',
                                        $selector . ':' . $authenticator,
                                        [
                                            'expires' => strtotime('+30 days'),
                                            'httponly' => true,
                                            'secure' => true, // Set to true if using HTTPS
                                            'samesite' => 'Lax',
                                        ]
                                    );
                                }
                            }
                            
                            $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'dashboard.php';
                            if (!empty($_SESSION['pending_share_crusade_code'])) {
                                $pending = $_SESSION['pending_share_crusade_code'];
                                unset($_SESSION['pending_share_crusade_code']);
                                $redirect = 'crusade_room.php?code=' . urlencode($pending) . '&share=1';
                            }
                            unset($_SESSION['redirect_after_login']); // Clear the redirect
                            header("Location: $redirect");
                            exit;
                        } else {
                            // Password is not valid
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    // Username doesn't exist
                    $login_err = "Invalid username or password.";
                }
            } else {
                $login_err = "Oops! Something went wrong. Please try again later.";
                if (function_exists('logDatabaseError')) {
                    logDatabaseError($sql, $conn->error);
                }
            }
            
            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    $conn->close();
}
?>
<?php
$body_classes = "d-flex justify-content-center align-items-center min-vh-100";
?>
<?php include 'includes/header.php'; ?>
<body class="d-flex justify-content-center align-items-center min-vh-100">
    <div class="login-container page-container">
        <div class="card cta-block">
            <div class="card-header text-center">
                <img src="logo.png" alt="Virtual Praise Room Logo" style="height: 50px; margin-bottom: 15px;">
                <h3>Welcome Back!</h3>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo $_SESSION['success_message']; 
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($login_err)): ?>
                    <div class="alert alert-danger"><?php echo $login_err; ?></div>
                <?php endif; ?>
                <?php if (!empty($csrf_err)): ?>
                    <div class="alert alert-danger"><?php echo $csrf_err; ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['registered']) && $_GET['registered'] == 'true'): ?>
                    <div class="alert alert-success">Registration successful! Please log in.</div>
                <?php endif; ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <div class="mb-3">
                        <input type="text" name="username" class="form-control form-control-dark <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>" placeholder="Username">
                        <div class="invalid-feedback"><?php echo $username_err; ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control form-control-dark <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fa fa-eye-slash" aria-hidden="true"></i>
                            </button>
                            <div class="invalid-feedback"><?php echo $password_err; ?></div>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                        <label class="form-check-label" for="rememberMe">Remember me</label>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary-custom">Login</button>
                    </div>
                </form>

                <!-- KingsChat Login Button -->
                <div class="text-center mt-3">
                    <div class="hr-theme-slash-2">
                        <div class="hr-line"></div>
                        <div class="hr-icon"><small class="text-muted">OR</small></div>
                        <div class="hr-line"></div>
                    </div>
                    <button id="kingschat-login" class="btn btn-outline-primary w-100 mt-2" style="background-color: #007bff; color: white; border: none;">
                        <img src="https://kingsch.at/favicon.ico" alt="KC" style="height: 20px; margin-right: 10px;">
                        Login with KingsChat
                    </button>
                </div>

                <div class="text-center mt-3">
                    <a href="forgot_password.php">Forgot password?</a>
                </div>
                <div class="text-center mt-4">
                    <p>Don't have an account? <a href="register.php">Register</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- KingsChat SDK (Browser-ready via esm.sh) -->
    <script type="module">
        import kingsChatWebSdk from 'https://esm.sh/kingschat-web-sdk';
        window.kingsChatWebSdk = kingsChatWebSdk;
        console.log('KingsChat SDK Status: Loaded via esm.sh');
    </script>
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

            // KingsChat Login Logic
            const kcBtn = document.getElementById('kingschat-login');
            if (kcBtn) {
                kcBtn.addEventListener('click', function() {
                    const clientId = '<?php echo defined("KINGSCHAT_CLIENT_ID") ? KINGSCHAT_CLIENT_ID : ""; ?>';

                    if (typeof kingsChatWebSdk === 'undefined') {
                        alert("KingsChat SDK is still loading or failed to load. Please try again in a moment.");
                        return;
                    }

                    const loginOptions = {
                        scopes: ['user', 'user_profile'],
                        clientId: clientId
                    };

                    console.log('Launching KingsChat Login with Client ID:', clientId);

                    kingsChatWebSdk.login(loginOptions)
                        .then(tokenResponse => {
                            console.log('Success! Received Token:', tokenResponse.accessToken);
                            // Send token to our backend
                            return fetch('kingschat_auth.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ access_token: tokenResponse.accessToken })
                            });
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                window.location.href = 'dashboard.php';
                            } else {
                                alert("Login failed: " + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('KingsChat SDK Error:', error);
                            alert("KingsChat login encountered an error. Check console for details.");
                        });
                });
            }
        });
    </script>

    <?php include 'includes/footer.php'; ?>
