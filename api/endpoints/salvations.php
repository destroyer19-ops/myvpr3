<?php
// ── POST /salvations ─────────────────────────────────────────────────────────
if ($method === 'POST' && $path === '/salvations') {
    $data = get_request_data();
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    
    if (empty($name) || empty($email)) {
        json_response(['success' => false, 'message' => 'Name and email are required.'], 400);
    }
    
    $ip_address = get_client_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO praise_salvation_gifts (full_name, email, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssss", $name, $email, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
    
    json_response(['success' => true]);
}

