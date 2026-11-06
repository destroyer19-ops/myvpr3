<?php
require_once __DIR__ . '/config.php';

// Safe error display: do not leak internals or keys in production
$is_production = (defined('SQUAD_ENV') && SQUAD_ENV === 'production') || (getenv('APP_ENV') === 'production');
if ($is_production) {
    ini_set("display_errors", 0);
    ini_set("log_errors", 1);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
} else {
    ini_set("display_errors", 1);
    error_reporting(E_ALL);
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . "/check_db.php";

// CORS and Preflight Handling
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = [
    defined('REACT_APP_URL') ? rtrim(REACT_APP_URL, '/') : 'http://localhost:5173',
    'http://localhost:5173',
    'http://localhost:3000',
    'http://127.0.0.1:5173',
    'https://myvpr.org',
    'http://myvpr.org'
];

if (!empty($origin)) {
    $origin_clean = rtrim($origin, '/');
    $is_allowed = in_array($origin_clean, $allowed_origins) 
        || (strpos($origin_clean, 'myvpr.org') !== false)
        || (defined('REACT_APP_URL') && $origin_clean === rtrim(REACT_APP_URL, '/'));
    
    if ($is_allowed) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With, x-squad-encrypted-body, x-squad-signature");
        header("Access-Control-Max-Age: 86400");
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/includes/session.php';
session_init();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = get_request_path();
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

if ($path === '/' && $method === 'GET') {
    json_response(['success' => true, 'message' => 'API online']);
}

if (empty($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $cookie_value = $_COOKIE['remember_me'];
    if (strpos($cookie_value, ':') !== false) {
        list($selector, $authenticator) = explode(':', $cookie_value, 2);
        if (ctype_xdigit($selector) && ctype_xdigit($authenticator)) {
            $stmt = $conn->prepare("SELECT id, username, account_type, remember_token FROM praise_users WHERE remember_selector = ? AND remember_token IS NOT NULL AND token_expiry > NOW() LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $selector);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                if ($row && password_verify($authenticator, $row['remember_token'])) {
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['account_type'] = $row['account_type'];

                    $new_selector = bin2hex(random_bytes(8));
                    $new_authenticator = bin2hex(random_bytes(32));
                    $new_hashed = password_hash($new_authenticator, PASSWORD_DEFAULT);
                    $new_expiry = date('Y-m-d H:i:s', strtotime('+30 days'));

                    $update_stmt = $conn->prepare("UPDATE praise_users SET remember_selector = ?, remember_token = ?, token_expiry = ? WHERE id = ?");
                    if ($update_stmt) {
                        $update_stmt->bind_param("sssi", $new_selector, $new_hashed, $new_expiry, $row['id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                        setcookie('remember_me', $new_selector . ':' . $new_authenticator, [
                            'expires' => strtotime('+30 days'),
                            'path' => '/',
                            'httponly' => true,
                            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                            'samesite' => 'Lax'
                        ]);
                    }
                } else {
                    $clear_stmt = $conn->prepare("UPDATE praise_users SET remember_selector = NULL, remember_token = NULL, token_expiry = NULL WHERE remember_selector = ?");
                    if ($clear_stmt) {
                        $clear_stmt->bind_param("s", $selector);
                        $clear_stmt->execute();
                        $clear_stmt->close();
                    }
                    setcookie('remember_me', '', time() - 3600, '/');
                }
            }
        }
    } else {
        setcookie('remember_me', '', time() - 3600, '/');
    }
}

if ($method === 'GET' && $path === '/csrf') {
    json_response(['success' => true, 'csrf_token' => csrf_token()]);
}

// ══════════════════════════════════════════════════════════════════════
// KingsChat OAuth — Step 1: Generate state & return authorization URL
// GET /auth/kingschat/start
// ══════════════════════════════════════════════════════════════════════
if ($method === 'GET' && $path === '/auth/kingschat/start') {
    if (KC_CLIENT_ID === '') {
        json_response(['success' => false, 'message' => 'KingsChat OAuth is not configured.'], 503);
    }

    // Preserve post-login redirect path in session if provided
    if (!empty($_GET['redirect'])) {
        $r = trim($_GET['redirect']);
        if (strpos($r, '/') === 0 && strpos($r, '//') === false) {
            $_SESSION['kc_oauth_redirect'] = $r;
        }
    }

    // Generate a cryptographically secure state value
    $state = bin2hex(random_bytes(32)); // 64 hex chars

    // Store state in DB with a 10-minute TTL (cleanup old states too)
    $stmt = $conn->prepare("DELETE FROM oauth_states WHERE created_at < NOW() - INTERVAL 10 MINUTE");
    if ($stmt) { $stmt->execute(); $stmt->close(); }

    $stmt = $conn->prepare("INSERT INTO oauth_states (state, created_at) VALUES (?, NOW())");
    if (!$stmt) {
        json_response(['success' => false, 'message' => 'Failed to initiate OAuth.'], 500);
    }
    $stmt->bind_param('s', $state);
    $stmt->execute();
    $stmt->close();

    // Build the KingsChat authorization URL
    $auth_params = [
        'clientId'  => KC_CLIENT_ID,
        'client_id' => KC_CLIENT_ID,
        'scopes'    => '["send_chat_message"]',
        'origin'    => $state,
        'state'     => $state,
    ];
    if (KC_REDIRECT_URI !== '') {
        $auth_params['redirect_uri'] = KC_REDIRECT_URI;
    }
    $auth_url = KC_AUTH_URL . '?' . http_build_query($auth_params);

    json_response(['success' => true, 'url' => $auth_url]);
}

// ══════════════════════════════════════════════════════════════════════
// KingsChat OAuth — Step 2: Callback handler
// GET/POST /auth/kingschat/callback
// KingsChat sends: code=AUTH_CODE&origin=STATE (or state=STATE)
// ══════════════════════════════════════════════════════════════════════
if ($path === '/auth/kingschat/callback') {
    $redirect_error = REACT_APP_URL . '/login?error=';

    // 0. Handle denied, cancelled, or error responses from KingsChat
    $oauth_error = trim($_GET['error_description'] ?? $_POST['error_description'] ?? $_GET['error'] ?? $_POST['error'] ?? '');
    if ($oauth_error !== '') {
        $userFriendlyMsg = (stripos($oauth_error, 'denied') !== false || stripos($oauth_error, 'cancel') !== false)
            ? 'KingsChat login was cancelled or access was denied.'
            : 'KingsChat login error: ' . $oauth_error;
        header('Location: ' . $redirect_error . urlencode($userFriendlyMsg));
        exit;
    }

    // Support both GET and POST callbacks, and both origin / state param keys
    $code   = trim($_GET['code']   ?? $_POST['code']   ?? '');
    $state  = trim($_GET['origin'] ?? $_POST['origin'] ?? $_GET['state'] ?? $_POST['state'] ?? '');

    // 1. Validate inputs
    if ($code === '' || $state === '') {
        header('Location: ' . $redirect_error . urlencode('Missing authorization code or login state. Please try logging in again.'));
        exit;
    }

    // 2. Validate state against DB (CSRF protection)
    $stmt = $conn->prepare("SELECT state FROM oauth_states WHERE state = ? AND created_at > NOW() - INTERVAL 10 MINUTE");
    if (!$stmt) {
        header('Location: ' . $redirect_error . urlencode('Server error during state validation.'));
        exit;
    }
    $stmt->bind_param('s', $state);
    $stmt->execute();
    $stmt->store_result();
    $valid_state = $stmt->num_rows === 1;
    $stmt->close();

    if (!$valid_state) {
        header('Location: ' . $redirect_error . urlencode('Invalid or expired login state. Please try again.'));
        exit;
    }

    // Consume state (delete it so it can't be reused)
    $stmt = $conn->prepare("DELETE FROM oauth_states WHERE state = ?");
    if ($stmt) { $stmt->bind_param('s', $state); $stmt->execute(); $stmt->close(); }

    // 3. Exchange authorization code for tokens
    $token_payload = json_encode([
        'grant_type'    => 'code',
        'client_id'     => KC_CLIENT_ID,
        'code'          => $code,
        'client_secret' => KC_CLIENT_SECRET,
        'api_key'       => KC_CLIENT_SECRET,
    ]);

    $ch = curl_init(KC_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $token_payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $token_response = curl_exec($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);

    if ($curl_errno || !$token_response) {
        header('Location: ' . $redirect_error . urlencode('Failed to connect to KingsChat. Please try again.'));
        exit;
    }

    $token_data = json_decode($token_response, true);
    $access_token  = $token_data['access_token']  ?? '';
    $refresh_token = $token_data['refresh_token'] ?? '';
    $expires_in_ms = $token_data['expires_in_millis'] ?? 0;

    if ($access_token === '') {
        error_log('KingsChat token exchange failed: ' . $token_response);
        header('Location: ' . $redirect_error . urlencode('KingsChat authentication failed. Please try again.'));
        exit;
    }

    $token_expires_at = null;
    if ($expires_in_ms > 0) {
        $token_expires_at = date('Y-m-d H:i:s', time() + intval($expires_in_ms / 1000));
    }

    // 4. Fetch KingsChat user info
    $ch = curl_init(KC_USERINFO_URL);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $access_token", 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $user_response = curl_exec($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);

    if ($curl_errno || !$user_response) {
        header('Location: ' . $redirect_error . urlencode('Could not retrieve KingsChat profile.'));
        exit;
    }

    $kc_user = json_decode($user_response, true);
    $profile = $kc_user['profile'] ?? $kc_user['user'] ?? $kc_user;
    $kc_user_id  = (string)($profile['userId'] ?? $profile['user_id'] ?? $profile['id'] ?? $kc_user['userId'] ?? $kc_user['id'] ?? '');
    $kc_email    = strtolower(trim($profile['email'] ?? $kc_user['email'] ?? ''));
    $kc_username = trim($profile['displayName'] ?? $profile['name'] ?? $profile['username'] ?? $kc_user['displayName'] ?? $kc_user['name'] ?? '');
    $kc_handle   = trim($profile['tag'] ?? $profile['handle'] ?? $kc_user['tag'] ?? $kc_user['handle'] ?? '');

    if ($kc_user_id === '') {
        error_log('KingsChat userinfo missing userId: ' . $user_response);
        header('Location: ' . $redirect_error . urlencode('Could not identify KingsChat account.'));
        exit;
    }

    $hasKingsChatCol = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM praise_users LIKE 'kingschat_id'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasKingsChatCol = true;
    }

    // 5. Find or create local VPR user
    // Priority 1: Check if this KingsChat account is already in user_oauth_providers
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.account_type
        FROM user_oauth_providers op
        JOIN praise_users u ON u.id = op.user_id
        WHERE op.provider = 'kingschat' AND op.provider_user_id = ?
    ");
    $stmt->bind_param('s', $kc_user_id);
    $stmt->execute();
    $stmt->bind_result($local_user_id, $local_username, $local_account_type);
    $stmt->fetch();
    $stmt->close();

    // Priority 2: Check legacy praise_users.kingschat_id column
    if (!$local_user_id && $hasKingsChatCol) {
        $stmt = $conn->prepare("SELECT id, username, account_type FROM praise_users WHERE kingschat_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $kc_user_id);
            $stmt->execute();
            $stmt->bind_result($local_user_id, $local_username, $local_account_type);
            $stmt->fetch();
            $stmt->close();
        }
    }

    // Priority 3: Match existing user by verified email
    if (!$local_user_id && $kc_email !== '') {
        $stmt = $conn->prepare("SELECT id, username, account_type FROM praise_users WHERE LOWER(email) = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $kc_email);
            $stmt->execute();
            $stmt->bind_result($local_user_id, $local_username, $local_account_type);
            $stmt->fetch();
            $stmt->close();
        }
    }

    // Priority 4: If user is currently logged in, link to existing account
    if (!$local_user_id && !empty($_SESSION['user_id'])) {
        $sess_uid = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id, username, account_type FROM praise_users WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $sess_uid);
            $stmt->execute();
            $stmt->bind_result($local_user_id, $local_username, $local_account_type);
            $stmt->fetch();
            $stmt->close();
        }
    }

    if (!$local_user_id) {
        // Create a new VPR user
        // Build a unique username from KC handle/displayName
        $base_username = preg_replace('/[^a-zA-Z0-9_]/', '_', $kc_handle ?: $kc_username ?: 'kc_user');
        $base_username = substr($base_username, 0, 40);
        $new_username  = $base_username;
        $counter = 1;
        while (true) {
            $check = $conn->prepare("SELECT id FROM praise_users WHERE username = ?");
            $check->bind_param('s', $new_username);
            $check->execute();
            $check->store_result();
            $exists = $check->num_rows > 0;
            $check->close();
            if (!$exists) break;
            $new_username = $base_username . '_' . $counter++;
        }

        $placeholder_email    = $kc_email ?: (substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $kc_user_id), 0, 36) . '@kingschat.placeholder');
        $placeholder_password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        if ($hasKingsChatCol) {
            $stmt = $conn->prepare("
                INSERT INTO praise_users (username, email, password, account_type, kingschat_id, created_at)
                VALUES (?, ?, ?, 'individual', ?, NOW())
            ");
            $stmt->bind_param('ssss', $new_username, $placeholder_email, $placeholder_password, $kc_user_id);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO praise_users (username, email, password, account_type, created_at)
                VALUES (?, ?, ?, 'individual', NOW())
            ");
            $stmt->bind_param('sss', $new_username, $placeholder_email, $placeholder_password);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            header('Location: ' . $redirect_error . urlencode('Could not create your VPR account. Please try again.'));
            exit;
        }
        $local_user_id      = $conn->insert_id;
        $local_username     = $new_username;
        $local_account_type = 'individual';
        $stmt->close();

        // Link this KingsChat account in user_oauth_providers
        $stmt = $conn->prepare("
            INSERT INTO user_oauth_providers (user_id, provider, provider_user_id, access_token, refresh_token, token_expires_at)
            VALUES (?, 'kingschat', ?, ?, ?, ?)
        ");
        $stmt->bind_param('issss', $local_user_id, $kc_user_id, $access_token, $refresh_token, $token_expires_at);
        $stmt->execute();
        $stmt->close();
    } else {
        // Ensure user_oauth_providers is linked/updated
        $checkLink = $conn->prepare("SELECT id FROM user_oauth_providers WHERE provider = 'kingschat' AND provider_user_id = ?");
        $checkLink->bind_param('s', $kc_user_id);
        $checkLink->execute();
        $checkLink->store_result();
        $isLinked = ($checkLink->num_rows > 0);
        $checkLink->close();

        if ($isLinked) {
            $stmt = $conn->prepare("
                UPDATE user_oauth_providers
                SET access_token = ?, refresh_token = ?, token_expires_at = ?, updated_at = NOW()
                WHERE provider = 'kingschat' AND provider_user_id = ?
            ");
            $stmt->bind_param('ssss', $access_token, $refresh_token, $token_expires_at, $kc_user_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO user_oauth_providers (user_id, provider, provider_user_id, access_token, refresh_token, token_expires_at)
                VALUES (?, 'kingschat', ?, ?, ?, ?)
            ");
            $stmt->bind_param('issss', $local_user_id, $kc_user_id, $access_token, $refresh_token, $token_expires_at);
            $stmt->execute();
            $stmt->close();
        }

        // Keep legacy kingschat_id column synchronized if present
        if ($hasKingsChatCol) {
            $stmt = $conn->prepare("UPDATE praise_users SET kingschat_id = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $kc_user_id, $local_user_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // 6. Establish VPR session (same as normal login)
    $_SESSION['loggedin']     = true;
    $_SESSION['user_id']      = $local_user_id;
    $_SESSION['username']     = $local_username;
    $_SESSION['account_type'] = $local_account_type ?? 'individual';

    // Synchronize admin permissions if user is an admin
    $is_admin = false;
    $admStmt = $conn->prepare("SELECT id FROM praise_admins WHERE username = ? LIMIT 1");
    if ($admStmt) {
        $admStmt->bind_param('s', $local_username);
        $admStmt->execute();
        $admStmt->store_result();
        if ($admStmt->num_rows > 0) {
            $is_admin = true;
        }
        $admStmt->close();
    }
    if (!$is_admin) {
        $adminColCheck = $conn->query("SHOW COLUMNS FROM praise_users LIKE 'is_admin'");
        if ($adminColCheck && $adminColCheck->num_rows > 0) {
            $uAdm = $conn->query("SELECT is_admin FROM praise_users WHERE id = {$local_user_id}");
            if ($uAdm && ($row = $uAdm->fetch_assoc()) && !empty($row['is_admin'])) {
                $is_admin = true;
            }
        }
    }
    if ($is_admin) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user_id']   = $local_user_id;
    }

    // 7. Redirect back to React dashboard or preserved destination
    $destPath = '/dashboard';
    if (!empty($_SESSION['kc_oauth_redirect'])) {
        $candidate = $_SESSION['kc_oauth_redirect'];
        unset($_SESSION['kc_oauth_redirect']);
        if (strpos($candidate, '/') === 0 && strpos($candidate, '//') === false) {
            $destPath = $candidate;
        }
    }

    // Explicitly commit session to prevent race conditions during browser redirection
    session_write_close();

    header('Location: ' . REACT_APP_URL . $destPath);
    exit;
}

if ($method === 'POST' && $path === '/auth/login') {
    $data = get_request_data();
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');

    if ($username === '' || $password === '') {
        json_response(['success' => false, 'message' => 'Username and password are required.'], 422);
    }

    $client_ip = get_client_ip();
    $rate_key = 'api_login:' . $client_ip . ':' . strtolower($username);
    if (!rate_limit_allow($conn, $rate_key, 5, 900)) {
        json_response(['success' => false, 'message' => 'Too many login attempts. Please try again later.'], 429);
    }

    $sql = "SELECT id, username, password, account_type FROM praise_users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_response(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows !== 1) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Invalid username or password.'], 401);
    }

    $stmt->bind_result($id, $uname, $hashed_password, $account_type);
    $stmt->fetch();
    if (!password_verify($password, $hashed_password)) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Invalid username or password.'], 401);
    }
    $stmt->close();
    rate_limit_reset($conn, $rate_key);

    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = $id;
    $_SESSION['username'] = $uname;
    $_SESSION['account_type'] = $account_type;

    if (!empty($data['remember_me'])) {
        $selector = bin2hex(random_bytes(8));
        $authenticator = bin2hex(random_bytes(32));
        $hashed_authenticator = password_hash($authenticator, PASSWORD_DEFAULT);
        $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));

        $update_token_sql = "UPDATE praise_users SET remember_selector = ?, remember_token = ?, token_expiry = ? WHERE id = ?";
        if ($stmt_update = $conn->prepare($update_token_sql)) {
            $stmt_update->bind_param("sssi", $selector, $hashed_authenticator, $expiry, $id);
            $stmt_update->execute();
            $stmt_update->close();

            setcookie('remember_me', $selector . ':' . $authenticator, [
                'expires' => strtotime('+30 days'),
                'path' => '/',
                'httponly' => true,
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'samesite' => 'Lax'
            ]);
        }
    }

    json_response([
        'success' => true,
        'user' => [
            'id' => $id,
            'username' => $uname,
            'account_type' => $account_type,
        ],
        'csrf_token' => csrf_token(),
    ]);
}

if ($method === 'POST' && $path === '/auth/register') {
    $data = get_request_data();
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = trim($data['password'] ?? '');
    $account_type = trim($data['account_type'] ?? 'individual');
    $kc_handle = trim($data['kc_handle'] ?? '');
    $country = trim($data['country'] ?? '');
    $city = trim($data['city'] ?? '');
    $region = trim($data['region'] ?? '');
    $satellite_campus = trim($data['satellite_campus'] ?? '');
    $church = trim($data['church'] ?? '');

    if ($username === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        json_response(['success' => false, 'message' => 'Invalid username.'], 422);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['success' => false, 'message' => 'Invalid email.'], 422);
    }
    if ($password === '' || strlen($password) < 6) {
        json_response(['success' => false, 'message' => 'Password must be at least 6 characters.'], 422);
    }

    $stmt = $conn->prepare("SELECT id FROM praise_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Username already taken.'], 409);
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT id FROM praise_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Email already registered.'], 409);
    }
    $stmt->close();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO praise_users (username, email, password, account_type, kc_handle, country, city, region, satellite_campus, church, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_response(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param("ssssssssss", $username, $email, $hashed_password, $account_type, $kc_handle, $country, $city, $region, $satellite_campus, $church);
    if (!$stmt->execute()) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Failed to register.'], 500);
    }
    $user_id = $stmt->insert_id;
    $stmt->close();

    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['account_type'] = $account_type;

    json_response([
        'success' => true,
        'user' => [
            'id' => $user_id,
            'username' => $username,
            'account_type' => $account_type,
        ],
        'csrf_token' => csrf_token(),
    ], 201);
}

if ($method === 'POST' && $path === '/auth/forgot') {
    $data = get_request_data();
    $email = trim($data['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['success' => false, 'message' => 'Invalid email.'], 422);
    }

    $client_ip = get_client_ip();
    $rate_key = 'api_forgot:' . $client_ip;
    if (!rate_limit_allow($conn, $rate_key, 5, 900)) {
        json_response(['success' => false, 'message' => 'Too many requests. Please try again later.'], 429);
    }

    $sql = "SELECT id FROM praise_users WHERE email = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($user_id);
                $stmt->fetch();
                $reset_token = bin2hex(random_bytes(32));
                $hashed_reset_token = password_hash($reset_token, PASSWORD_DEFAULT);
                $expiry_time = date("Y-m-d H:i:s", strtotime('+1 hour'));

                $update_sql = "UPDATE praise_users SET reset_token = ?, reset_expiry = ? WHERE id = ?";
                if ($update_stmt = $conn->prepare($update_sql)) {
                    $update_stmt->bind_param("ssi", $hashed_reset_token, $expiry_time, $user_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }

                $reset_link = REACT_APP_URL . "/reset-password?token=" . $reset_token . "&email=" . urlencode($email);
                error_log("Password reset link for $email: $reset_link");
            }
        }
        $stmt->close();
    }

    json_response(['success' => true, 'message' => 'If an account exists, a reset link has been sent.']);
}

if ($method === 'POST' && $path === '/auth/reset') {
    $data = get_request_data();
    $email = trim($data['email'] ?? '');
    $token = trim($data['token'] ?? '');
    $password = trim($data['password'] ?? '');

    if (empty($email) || empty($token) || empty($password)) {
        json_response(['success' => false, 'message' => 'Missing required fields.'], 422);
    }
    if (strlen($password) < 6) {
        json_response(['success' => false, 'message' => 'Password must be at least 6 characters.'], 422);
    }

    $sql = "SELECT id, reset_token, reset_expiry FROM praise_users WHERE email = ? AND reset_token IS NOT NULL AND reset_expiry > NOW()";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_response(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows !== 1) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Invalid or expired reset token.'], 400);
    }

    $stmt->bind_result($user_id, $hashed_reset_token, $reset_expiry);
    $stmt->fetch();
    if (!password_verify($token, $hashed_reset_token)) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Invalid or expired reset token.'], 400);
    }
    $stmt->close();

    $new_hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $update_sql = "UPDATE praise_users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_hashed_password, $user_id);
    if ($update_stmt->execute()) {
        $update_stmt->close();
        json_response(['success' => true, 'message' => 'Password reset successfully.']);
    }
    $update_stmt->close();
    json_response(['success' => false, 'message' => 'Failed to update password.'], 500);
}

if ($method === 'POST' && preg_match('#^/crusades/([A-Za-z0-9]+)/end$#', $path, $matches)) {
    require_auth();
    require_csrf();
    $code = $matches[1];

    $stmt = $conn->prepare("SELECT id, user_id FROM praise_crusades WHERE crusade_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $crusade = $result->fetch_assoc();
    $stmt->close();

    if (!$crusade) {
        json_response(['success' => false, 'message' => 'Invalid crusade code.'], 404);
    }
    if ((int)$crusade['user_id'] !== (int)$_SESSION['user_id']) {
        json_response(['success' => false, 'message' => 'Not authorized.'], 403);
    }

    $stmt = $conn->prepare("UPDATE praise_crusades SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $crusade['id']);
    if (!$stmt->execute()) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Failed to end crusade.'], 500);
    }
    $stmt->close();
    json_response(['success' => true]);
}

if ($method === 'POST' && $path === '/auth/logout') {
    require_auth();
    require_csrf();
    
    if (isset($_COOKIE['remember_me'])) {
        $cookie_value = $_COOKIE['remember_me'];
        if (strpos($cookie_value, ':') !== false) {
            list($selector, $authenticator) = explode(':', $cookie_value, 2);
            $clear_stmt = $conn->prepare("UPDATE praise_users SET remember_selector = NULL, remember_token = NULL, token_expiry = NULL WHERE remember_selector = ?");
            if ($clear_stmt) {
                $clear_stmt->bind_param("s", $selector);
                $clear_stmt->execute();
                $clear_stmt->close();
            }
        }
        setcookie('remember_me', '', time() - 3600, '/');
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    json_response(['success' => true]);
}

if ($method === 'GET' && $path === '/comments') {
    $property = trim($_GET['property'] ?? '');
    if ($property === '') {
        json_response(['success' => false, 'message' => 'Property is required.'], 422);
    }
    $limit = (int)($_GET['limit'] ?? 30);
    if ($limit < 1 || $limit > 100) {
        $limit = 30;
    }

    $stmt = $conn->prepare("SELECT id, date, time, property, name, xid, comment FROM comments WHERE property = ? ORDER BY id DESC LIMIT ?");
    $stmt->bind_param("si", $property, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    $stmt->close();

    $comments = array_reverse($comments);
    json_response(['success' => true, 'comments' => $comments]);
}

if ($method === 'GET' && $path === '/comments/updates') {
    $property = trim($_GET['property'] ?? '');
    $since_id = (int)($_GET['since_id'] ?? 0);
    $xid = trim($_GET['xid'] ?? '');
    if ($property === '') {
        json_response(['success' => false, 'message' => 'Property is required.'], 422);
    }

    $stmt = $conn->prepare("SELECT id, date, time, property, name, xid, comment FROM comments WHERE property = ? AND id > ? AND xid != ? ORDER BY id ASC");
    $stmt->bind_param("sis", $property, $since_id, $xid);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    $stmt->close();

    json_response(['success' => true, 'comments' => $comments]);
}

if ($method === 'POST' && $path === '/comments') {
    $data = get_request_data();
    $comment = trim($data['comment'] ?? '');
    $name = trim($data['name'] ?? '');
    $property = trim($data['property'] ?? '');
    $xid = trim($data['xid'] ?? '');

    if ($comment === '' || $name === '' || $property === '' || $xid === '') {
        json_response(['success' => false, 'message' => 'comment, name, property, and xid are required.'], 422);
    }

    $client_ip = get_client_ip();
    $rate_key = 'comments:' . $client_ip . ':' . strtolower($property);
    if (!rate_limit_allow($conn, $rate_key, 20, 300)) {
        json_response(['success' => false, 'message' => 'Too many comments. Please slow down.'], 429);
    }

    $profane = get_profane_words();
    foreach ($profane as $bad) {
        if (strcasecmp($bad, $comment) === 0) {
            json_response(['success' => false, 'message' => 'This comment contains censored words.'], 422);
        }
    }

    $date = strtotime(date('d M Y'));
    $time = date('h:i:a');
    $clean_comment = strip_tags($comment);
    $clean_name = strip_tags($name);
    $clean_property = strip_tags($property);
    $clean_xid = strip_tags($xid);

    $stmt = $conn->prepare("INSERT INTO comments (date, time, property, name, xid, comment) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $date, $time, $clean_property, $clean_name, $clean_xid, $clean_comment);
    if (!$stmt->execute()) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Failed to add comment.'], 500);
    }
    $id = $stmt->insert_id;
    $stmt->close();

    json_response([
        'success' => true,
        'comment' => [
            'id' => $id,
            'date' => $date,
            'time' => $time,
            'property' => $clean_property,
            'name' => $clean_name,
            'xid' => $clean_xid,
            'comment' => $clean_comment,
        ],
    ], 201);
}

if ($method === 'GET' && preg_match('#^/me$#', $path)) {
    if (empty($_SESSION['user_id'])) {
        json_response(['success' => false, 'message' => 'Not authenticated.'], 401);
    }
    json_response([
        'success' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'account_type' => $_SESSION['account_type'] ?? '',
        ],
    ]);
}

if ($method === 'GET' && preg_match('#^/crusades/([A-Za-z0-9]+)$#', $path, $matches)) {
    $code = $matches[1];
    $stmt = $conn->prepare("SELECT id, user_id, title, description, crusade_code, video_url, is_active, start_time, end_time, created_at FROM praise_crusades WHERE crusade_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $crusade = $result->fetch_assoc();
    $stmt->close();

    if (!$crusade) {
        json_response(['success' => false, 'message' => 'Crusade not found.'], 404);
    }

    json_response([
        'success' => true,
        'crusade' => $crusade,
    ]);
}

if ($method === 'GET' && preg_match('#^/crusades/([A-Za-z0-9]+)/stats$#', $path, $matches)) {
    $code = $matches[1];
    $stmt = $conn->prepare("SELECT id FROM praise_crusades WHERE crusade_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $crusade = $result->fetch_assoc();
    $stmt->close();

    if (!$crusade) {
        json_response(['success' => false, 'message' => 'Crusade not found.'], 404);
    }

    $crusade_id = (int)$crusade['id'];

    $stmt_views = $conn->prepare("SELECT COUNT(*) AS total_views, COUNT(DISTINCT ip_address) AS unique_views, SUM(CASE WHEN stayed_for_a_minute = 1 THEN 1 ELSE 0 END) AS engaged_views FROM praise_crusade_stats WHERE crusade_id = ?");
    $stmt_views->bind_param("i", $crusade_id);
    $stmt_views->execute();
    $views = $stmt_views->get_result()->fetch_assoc();
    $stmt_views->close();

    $stmt_salvation = $conn->prepare("SELECT COUNT(*) AS total_salvation_clicks FROM praise_salvation_clicks WHERE crusade_id = ?");
    $stmt_salvation->bind_param("i", $crusade_id);
    $stmt_salvation->execute();
    $salvation = $stmt_salvation->get_result()->fetch_assoc();
    $stmt_salvation->close();

    json_response([
        'success' => true,
        'stats' => [
            'total_views' => (int)($views['total_views'] ?? 0),
            'unique_views' => (int)($views['unique_views'] ?? 0),
            'engaged_views' => (int)($views['engaged_views'] ?? 0),
            'salvation_clicks' => (int)($salvation['total_salvation_clicks'] ?? 0),
        ],
    ]);
}

if ($method === 'GET' && $path === '/crusades') {
    if (!empty($_GET['mine'])) {
        require_auth();
        $user_id = $_SESSION['user_id'];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per_page = (int)($_GET['per_page'] ?? 20);
        if ($per_page < 1 || $per_page > 100) {
            $per_page = 20;
        }
        $offset = ($page - 1) * $per_page;

        $stmt = $conn->prepare("SELECT id, title, description, crusade_code, video_url, is_active, start_time, end_time, created_at FROM praise_crusades WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param("iii", $user_id, $per_page, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $crusades = [];
        while ($row = $result->fetch_assoc()) {
            $crusades[] = $row;
        }
        $stmt->close();

        $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM praise_crusades WHERE user_id = ?");
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $total = (int)($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $count_stmt->close();

        json_response([
            'success' => true,
            'crusades' => $crusades,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
            ],
        ]);
    }
    json_response(['success' => false, 'message' => 'Invalid query.'], 400);
}

if ($method === 'POST' && $path === '/crusades') {
    require_auth();
    require_csrf();
    $data = get_request_data();

    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $video_url = trim($data['video_url'] ?? '');
    $live_stream_id = trim($data['live_stream_id'] ?? '');
    $schedule = !empty($data['schedule_crusade']);
    $start_time = $schedule ? trim($data['start_time'] ?? '') : null;
    $end_time = $schedule ? trim($data['end_time'] ?? '') : null;

    if ($title === '') {
        json_response(['success' => false, 'message' => 'Title is required.'], 422);
    }
    if ($description === '') {
        json_response(['success' => false, 'message' => 'Description is required.'], 422);
    }

    if ($live_stream_id !== '' && strpos($live_stream_id, 'official_stream_') === 0) {
        $stream_id = (int)str_replace('official_stream_', '', $live_stream_id);
        if ($stream_id <= 0) {
            json_response(['success' => false, 'message' => 'Invalid live stream selection.'], 422);
        }
        $video_url = 'LIVE_STREAM_OFFICIAL_' . $stream_id;
    } elseif ($video_url === '') {
        json_response(['success' => false, 'message' => 'Video URL is required.'], 422);
    } elseif (!filter_var($video_url, FILTER_VALIDATE_URL) && strpos($video_url, 'VPR_VOD_') !== 0 && strpos($video_url, 'LIVE_STREAM_OFFICIAL_') !== 0) {
        json_response(['success' => false, 'message' => 'Video URL is invalid.'], 422);
    }

    if ($schedule) {
        if ($start_time === '') {
            json_response(['success' => false, 'message' => 'Start time is required for scheduled crusades.'], 422);
        }
        if (!empty($end_time) && strtotime($end_time) <= strtotime($start_time)) {
            json_response(['success' => false, 'message' => 'End time must be after start time.'], 422);
        }
    }

    $user_id = $_SESSION['user_id'];
    require_active_subscription($conn, $user_id);

    $crusade_code = bin2hex(random_bytes(8));
    $is_active = $schedule ? 0 : 1;

    $sql = "INSERT INTO praise_crusades (user_id, title, description, crusade_code, video_url, created_at, is_active, start_time, end_time) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_response(['success' => false, 'message' => 'Database error.'], 500);
    }
    $stmt->bind_param("issssiss", $user_id, $title, $description, $crusade_code, $video_url, $is_active, $start_time, $end_time);
    if (!$stmt->execute()) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Failed to create crusade.'], 500);
    }
    $stmt->close();
    json_response([
        'success' => true,
        'crusade_code' => $crusade_code,
        'is_active' => (bool)$is_active,
    ], 201);
}

if ($method === 'POST' && preg_match('#^/crusades/([A-Za-z0-9]+)/share$#', $path, $matches)) {
    require_auth();
    require_csrf();

    $code = $matches[1];
    $user_id = $_SESSION['user_id'];
    require_active_subscription($conn, $user_id);

    $stmt = $conn->prepare("SELECT id, is_active FROM praise_crusades WHERE crusade_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $crusade = $result->fetch_assoc();
    $stmt->close();

    if (!$crusade) {
        json_response(['success' => false, 'message' => 'Invalid crusade code.'], 404);
    }
    if ((int)$crusade['is_active'] !== 1) {
        json_response(['success' => false, 'message' => 'Crusade is not active.'], 422);
    }

    $stmt_existing = $conn->prepare("SELECT stream_key FROM praise_user_shared_streams WHERE user_id = ? AND crusade_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $stmt_existing->bind_param("ii", $user_id, $crusade['id']);
    $stmt_existing->execute();
    $result_existing = $stmt_existing->get_result();
    $existing = $result_existing->fetch_assoc();
    $stmt_existing->close();

    if ($existing && !empty($existing['stream_key'])) {
        $link = base_url() . '/new/crusade/' . $crusade['crusade_code'] . '?party=' . $existing['stream_key'];
        json_response(['success' => true, 'stream_key' => $existing['stream_key'], 'share_url' => $link, 'reused' => true]);
    }

    do {
        $stream_key = bin2hex(random_bytes(16));
        $stmt_check = $conn->prepare("SELECT id FROM praise_user_shared_streams WHERE stream_key = ?");
        $stmt_check->bind_param("s", $stream_key);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
    } while ($result_check->num_rows > 0);
    $stmt_check->close();

    $stmt_insert = $conn->prepare("INSERT INTO praise_user_shared_streams (user_id, crusade_id, stream_key) VALUES (?, ?, ?)");
    $stmt_insert->bind_param("iis", $user_id, $crusade['id'], $stream_key);
    if (!$stmt_insert->execute()) {
        $stmt_insert->close();
        json_response(['success' => false, 'message' => 'Failed to create share link.'], 500);
    }
    $stmt_insert->close();

    $link = base_url() . '/new/crusade/' . $crusade['crusade_code'] . '?party=' . $stream_key;
    json_response(['success' => true, 'stream_key' => $stream_key, 'share_url' => $link, 'reused' => false]);
}

if ($method === 'POST' && $path === '/shared/end') {
    require_auth();
    require_csrf();
    $data = get_request_data();
    $stream_key = trim($data['stream_key'] ?? '');
    if ($stream_key === '') {
        json_response(['success' => false, 'message' => 'Stream key is required.'], 422);
    }

    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE praise_user_shared_streams SET is_active = 0 WHERE stream_key = ? AND user_id = ?");
    $stmt->bind_param("si", $stream_key, $user_id);
    if (!$stmt->execute()) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Failed to end shared stream.'], 500);
    }
    $stmt->close();
    json_response(['success' => true]);
}

if ($method === 'POST' && $path === '/meetings') {
    require_auth();
    require_csrf();
    $data = get_request_data();

    $title = trim($data['title'] ?? 'Untitled Meeting');
    $user_id = $_SESSION['user_id'];
    require_active_subscription($conn, $user_id);

    $meeting_code = substr(bin2hex(random_bytes(8)), 0, 8);
    $max_attempts = 10;
    $attempt = 0;

    $stmt_check = $conn->prepare("SELECT id FROM praise_meetings WHERE meeting_code = ?");
    while ($attempt < $max_attempts) {
        $stmt_check->bind_param("s", $meeting_code);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows === 0) {
            break;
        }
        $meeting_code = substr(bin2hex(random_bytes(8)), 0, 8);
        $attempt++;
    }
    $stmt_check->close();

    if ($attempt >= $max_attempts) {
        json_response(['success' => false, 'message' => 'Failed to generate a unique meeting code.'], 500);
    }

    $stmt = $conn->prepare("INSERT INTO praise_meetings (user_id, title, meeting_code, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $title, $meeting_code);
    if (!$stmt->execute()) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Failed to create meeting.'], 500);
    }
    $meeting_id = $stmt->insert_id;
    $stmt->close();

    json_response([
        'success' => true,
        'meeting_id' => $meeting_id,
        'meeting_code' => $meeting_code,
    ], 201);
}

if ($method === 'POST' && $path === '/meetings/start') {
    require_auth();
    require_csrf();
    $data = get_request_data();
    $meeting_id = (int)($data['meeting_id'] ?? 0);
    $duration = (int)($data['duration'] ?? 30);
    $user_id = $_SESSION['user_id'];

    if ($meeting_id <= 0) {
        json_response(['success' => false, 'message' => 'Meeting ID is required.'], 422);
    }

    $stmt = $conn->prepare("SELECT id FROM praise_meetings WHERE id = ?");
    $stmt->bind_param("i", $meeting_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Invalid meeting ID.'], 404);
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO praise_meeting_sessions (meeting_id, user_id, start_time, duration) VALUES (?, ?, NOW(), ?)");
    $stmt->bind_param("iii", $meeting_id, $user_id, $duration);
    if (!$stmt->execute()) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Failed to start meeting session.'], 500);
    }
    $session_id = $stmt->insert_id;
    $stmt->close();

    json_response([
        'success' => true,
        'session_id' => $session_id,
        'meeting_id' => $meeting_id,
        'duration' => $duration,
    ]);
}

if ($method === 'POST' && $path === '/meetings/end') {
    require_auth();
    require_csrf();
    $data = get_request_data();
    $meeting_code = trim($data['meeting_code'] ?? '');
    $user_id = $_SESSION['user_id'];

    if ($meeting_code === '') {
        json_response(['success' => false, 'message' => 'Meeting code is required.'], 422);
    }

    $stmt = $conn->prepare("SELECT id FROM praise_meetings WHERE meeting_code = ?");
    $stmt->bind_param("s", $meeting_code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Invalid meeting code.'], 404);
    }
    $meeting = $result->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE praise_meeting_sessions SET end_time = NOW() WHERE meeting_id = ? AND user_id = ? AND end_time IS NULL ORDER BY start_time DESC LIMIT 1");
    $stmt->bind_param("ii", $meeting['id'], $user_id);
    if (!$stmt->execute()) {
        $stmt->close();
        json_response(['success' => false, 'message' => 'Failed to end meeting session.'], 500);
    }
    $stmt->close();

    json_response(['success' => true, 'message' => 'Meeting session ended successfully.']);
}

if ($method === 'GET' && $path === '/streams/live') {
    $result = $conn->query("SELECT id, title, description, stream_url, is_live, created_at FROM praise_live_tv WHERE is_live = 1 ORDER BY created_at DESC");
    if (!$result) {
        json_response(['success' => false, 'message' => 'Failed to load live streams.'], 500);
    }
    $streams = [];
    while ($row = $result->fetch_assoc()) {
        $streams[] = $row;
    }
    json_response(['success' => true, 'streams' => $streams]);
}

require_once __DIR__ . '/endpoints/user.php';
require_once __DIR__ . '/endpoints/crusades.php';
require_once __DIR__ . '/endpoints/meetings.php';
require_once __DIR__ . '/endpoints/videos.php';
require_once __DIR__ . '/endpoints/admin.php';
require_once __DIR__ . '/endpoints/streaming.php';
require_once __DIR__ . '/endpoints/realtime.php';
require_once __DIR__ . '/endpoints/metadata.php';
require_once __DIR__ . '/endpoints/testimonies.php';
require_once __DIR__ . '/endpoints/salvations.php';
require_once __DIR__ . '/endpoints/offerings.php';
require_once __DIR__ . '/endpoints/subscriptions.php';
require_once __DIR__ . '/endpoints/programs.php';

json_response(['success' => false, 'message' => 'Not found.', 'path' => $path, 'method' => $method], 404);
