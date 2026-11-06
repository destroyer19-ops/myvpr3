<?php
/**
 * kingschat_auth.php
 * Backend handler for KingsChat Web SDK login
 */
require_once 'includes/session.php';
session_init();
require_once 'includes/db.php';
require_once 'includes/config.php';

header('Content-Type: application/json');

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);
$access_token = $data['access_token'] ?? '';

if (empty($access_token)) {
    echo json_encode(['success' => false, 'message' => 'No access token provided.']);
    exit;
}

// 1. Fetch user profile from KingsChat
$ch = curl_init('https://connect.kingsch.at/developer/api/profile');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    echo json_encode(['success' => false, 'message' => 'Failed to verify KingsChat profile.']);
    exit;
}

$profile_data = json_decode($response, true);
$kings_user = $profile_data['profile'] ?? null;

if (!$kings_user || !isset($kings_user['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid profile data received.']);
    exit;
}

$kingschat_id = $kings_user['id'];
$name = $kings_user['name'] ?? 'KingsChat User';
$username = $kings_user['username'] ?? 'kc_' . substr($kingschat_id, 0, 8);
$email = $kings_user['email'] ?? ($username . '@kingsch.at'); // Fallback if email is hidden

// 2. Check if user exists in our database
$sql = "SELECT id, username, account_type FROM praise_users WHERE kingschat_id = ? OR email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $kingschat_id, $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    // User exists, update kingschat_id if it was found by email
    $update_sql = "UPDATE praise_users SET kingschat_id = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $kingschat_id, $user['id']);
    $update_stmt->execute();
    
    // Log them in
    $_SESSION["loggedin"] = true;
    $_SESSION["user_id"] = $user['id'];
    $_SESSION["username"] = $user['username'];
    $_SESSION["account_type"] = $user['account_type'];
    
    echo json_encode(['success' => true]);
} else {
    // 3. Create new user
    $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $insert_sql = "INSERT INTO praise_users (username, email, password, kingschat_id) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ssss", $username, $email, $random_password, $kingschat_id);
    
    if ($insert_stmt->execute()) {
        $_SESSION["loggedin"] = true;
        $_SESSION["user_id"] = $conn->insert_id;
        $_SESSION["username"] = $username;
        $_SESSION["account_type"] = 'individual';
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create local account.']);
    }
}
$conn->close();
