<?php
/**
 * kingschat_callback.php
 * Handles the OAuth2 callback from KingsChat
 */
require_once 'includes/session.php';
session_init();
require_once 'includes/db.php';
require_once 'includes/config.php';

// 1. Check for the authorization code
$code = $_GET['code'] ?? null;

if (!$code) {
    die("Error: No authorization code received from KingsChat.");
}

// 2. Exchange code for Access Token
// Documentation says: POST https://connect.kingsch.at/developer/oauth2/token
$token_url = 'https://connect.kingsch.at/developer/oauth2/token';
$post_fields = [
    'grant_type' => 'code',
    'client_id'  => KINGSCHAT_CLIENT_ID,
    'code'       => $code
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    die("Error: Failed to retrieve access token from KingsChat. Status: $http_code. Response: $response");
}

$token_data = json_decode($response, true);
$access_token = $token_data['access_token'] ?? null;

if (!$access_token) {
    die("Error: Access token not found in KingsChat response.");
}

// 3. Fetch user profile from KingsChat
// Documentation says: GET https://connect.kingsch.at/developer/api/profile
$profile_url = 'https://connect.kingsch.at/developer/api/profile';

$ch = curl_init($profile_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    die("Error: Failed to fetch KingsChat profile. Status: $http_code. Response: $response");
}

$profile_data = json_decode($response, true);
$kings_user = $profile_data['profile'] ?? null;

if (!$kings_user || !isset($kings_user['id'])) {
    die("Error: Invalid profile data received from KingsChat.");
}

$kingschat_id = $kings_user['id'];
$name = $kings_user['name'] ?? 'KingsChat User';
$username = $kings_user['username'] ?? 'kc_' . substr($kingschat_id, 0, 8);
$email = $kings_user['email'] ?? ($username . '@kingsch.at'); // Fallback if email is hidden

// 4. Check if user exists in our database
$sql = "SELECT id, username, account_type FROM praise_users WHERE kingschat_id = ? OR email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $kingschat_id, $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    // User exists, update kingschat_id if found by email
    $update_sql = "UPDATE praise_users SET kingschat_id = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $kingschat_id, $user['id']);
    $update_stmt->execute();
    
    // Log them in
    $_SESSION["loggedin"] = true;
    $_SESSION["user_id"] = $user['id'];
    $_SESSION["username"] = $user['username'];
    $_SESSION["account_type"] = $user['account_type'];
} else {
    // Create new user
    $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $insert_sql = "INSERT INTO praise_users (username, email, password, kingschat_id) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ssss", $username, $email, $random_password, $kingschat_id);
    
    if ($insert_stmt->execute()) {
        $_SESSION["loggedin"] = true;
        $_SESSION["user_id"] = $conn->insert_id;
        $_SESSION["username"] = $username;
        $_SESSION["account_type"] = 'individual';
    } else {
        die("Error: Failed to create local account for KingsChat user.");
    }
}

// 5. Redirect to dashboard
header("Location: dashboard.php");
exit;
