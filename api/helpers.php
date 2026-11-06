<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

session_init();

function json_response($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function get_request_path()
{
    if (!empty($_SERVER['PATH_INFO'])) {
        return $_SERVER['PATH_INFO'];
    }
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!$uri) {
        return '/';
    }
    $decoded_uri = rawurldecode($uri);
    $script_base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'), '/');
    if ($script_base === '') {
        $script_base = '/';
    }
    if (strpos($decoded_uri, '/v2/api') === 0) {
        $decoded_uri = substr($decoded_uri, strlen('/v2/api'));
        if ($decoded_uri === '') {
            $decoded_uri = '/';
        }
        return $decoded_uri;
    }
    if (strpos($decoded_uri, $script_base) === 0) {
        $decoded_uri = substr($decoded_uri, strlen($script_base));
    }
    
    // If the URI still starts with /index.php (e.g. if PATH_INFO wasn't set), strip it
    if (strpos($decoded_uri, '/index.php') === 0) {
        $decoded_uri = substr($decoded_uri, 10);
    }
    
    if ($decoded_uri === '') {
        $decoded_uri = '/';
    }
    
    return $decoded_uri;
}

function get_json_body()
{
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($content_type, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [];
}

function get_request_data()
{
    if (!empty($_POST)) {
        return $_POST;
    }
    $json = get_json_body();
    if (!empty($json)) {
        return $json;
    }
    return [];
}

function require_auth()
{
    if (empty($_SESSION['user_id'])) {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }
}

function require_csrf()
{
    $data = get_request_data();
    $token = $data['csrf_token'] ?? '';
    if (empty($token)) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (!csrf_validate($token)) {
        json_response(['success' => false, 'message' => 'Invalid session.'], 403);
    }
}

function require_active_subscription(mysqli $conn, $user_id)
{
    $stmt = $conn->prepare("SELECT end_date, status FROM subscriptions WHERE user_id = ? AND (status IS NULL OR status != 'cancelled') ORDER BY end_date DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscription = $result->fetch_assoc();
    $stmt->close();

    $is_subscribed = false;
    if ($subscription && ($subscription['status'] === null || $subscription['status'] !== 'cancelled')) {
        $end_date = new DateTime($subscription['end_date']);
        $now = new DateTime();
        if ($end_date > $now) {
            $is_subscribed = true;
        }
    }

    $new_status = $is_subscribed ? 'active' : 'inactive';
    $stmt_update = $conn->prepare("UPDATE praise_users SET subscription_status = ? WHERE id = ?");
    $stmt_update->bind_param("si", $new_status, $user_id);
    $stmt_update->execute();
    $stmt_update->close();

    if (!$is_subscribed) {
        json_response(['success' => false, 'message' => 'Active subscription required.'], 403);
    }
}

function base_url()
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function get_profane_words()
{
    static $words = null;
    if ($words !== null) {
        return $words;
    }
    $file = __DIR__ . '/../N-gage/Ngage/data/profane.php';
    if (file_exists($file)) {
        $words = [];
        include $file;
        if (isset($profane) && is_array($profane)) {
            $words = $profane;
        }
        return $words;
    }
    $words = [];
    return $words;
}
