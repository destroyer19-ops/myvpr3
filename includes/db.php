<?php
// Database configuration
// IMPORTANT: Replace with your actual database credentials
//$db_host = getenv('DB_HOST') ?: 'localhost';
//$db_name = getenv('DB_NAME') ?: 'it';
//$db_user = getenv('DB_USER') ?: 'root';
//$db_pass = getenv('DB_PASS') ?: '';
$db_host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'virtual_praise_room';
$db_user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
$db_pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';



// Check if mysqli extension is loaded
if (!function_exists('mysqli_init') || !extension_loaded('mysqli')) {
    die('The mysqli extension is not loaded. Please check your PHP configuration.');
}

// Create database connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    // For debugging: You might want to remove this in production
    // echo "Database connection successful!"; 
}

// Set character set
$conn->set_charset("utf8mb4");

// Optional: Set timezone
date_default_timezone_set('UTC');

/**
 * Helper function to sanitize user input
 * @param string $data The data to sanitize
 * @return string The sanitized data
 */
function sanitize($data)
{
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

/**
 * Helper function to log database errors
 * @param string $query The SQL query that failed
 * @param string $error The error message
 */
function logDatabaseError($query, $error)
{
    $log_file = __DIR__ . '/../logs/db_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] Query: $query | Error: $error" . PHP_EOL;

    // Create logs directory if it doesn't exist
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }

    // Append to log file
    file_put_contents($log_file, $message, FILE_APPEND);
}

function get_client_ip()
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($parts[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = trim($_SERVER['HTTP_X_REAL_IP']);
    }
    return $ip;
}

function rate_limit_allow(mysqli $conn, $key, $limit, $window_seconds)
{
    $now = date('Y-m-d H:i:s');
    $reset_at = date('Y-m-d H:i:s', time() + (int)$window_seconds);

    $stmt = $conn->prepare("SELECT attempts, reset_at FROM rate_limits WHERE rl_key = ? LIMIT 1");
    if (!$stmt) {
        return true;
    }
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        $insert = $conn->prepare("INSERT INTO rate_limits (rl_key, attempts, reset_at) VALUES (?, 1, ?)");
        if ($insert) {
            $insert->bind_param("ss", $key, $reset_at);
            $insert->execute();
            $insert->close();
        }
        return true;
    }

    if (strtotime($row['reset_at']) <= time()) {
        $update = $conn->prepare("UPDATE rate_limits SET attempts = 1, reset_at = ? WHERE rl_key = ?");
        if ($update) {
            $update->bind_param("ss", $reset_at, $key);
            $update->execute();
            $update->close();
        }
        return true;
    }

    if ((int)$row['attempts'] >= (int)$limit) {
        return false;
    }

    $update = $conn->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE rl_key = ?");
    if ($update) {
        $update->bind_param("s", $key);
        $update->execute();
        $update->close();
    }

    return true;
}

function rate_limit_reset(mysqli $conn, $key)
{
    $stmt = $conn->prepare("DELETE FROM rate_limits WHERE rl_key = ?");
    if ($stmt) {
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $stmt->close();
    }
}

function _ensureSession()
{
    if (php_sapi_name() === 'cli') {
        return;
    }
    if (function_exists('session_init')) {
        session_init();
        return;
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function csrf_token()
{
    _ensureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate($token)
{
    _ensureSession();
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    $is_valid = hash_equals($_SESSION['csrf_token'], $token);
    if ($is_valid) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $is_valid;
}
