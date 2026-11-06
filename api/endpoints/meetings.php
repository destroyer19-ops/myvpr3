<?php
// meetings.php - Meetings endpoints

if ($method === 'GET' && $path === '/meetings') {
    require_auth();
    $user_id = $_SESSION['user_id'];
    
    $meetings = [];
    $stmt = $conn->prepare("SELECT * FROM praise_meetings WHERE user_id = ? ORDER BY created_at DESC");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // For now just pass 0 participants since we don't have a stats table for meetings
            $row['participants'] = 0; 
            $meetings[] = $row;
        }
        $stmt->close();
    }

    json_response([
        'success' => true,
        'data' => $meetings
    ]);
}

if (!function_exists('createDailyMeetingRoom')) {
    function createDailyMeetingRoom($title = 'Virtual Meeting') {
        $apiKey = defined('DAILY_API_KEY') ? DAILY_API_KEY : (getenv('DAILY_API_KEY') ?: '86cb27d1f7604cd987d363250baf0c3e052c66056716b1fea3b3e34788130a66');
        $duration = defined('DAILY_ROOM_DURATION') ? DAILY_ROOM_DURATION : 86400;

        $url = 'https://api.daily.co/v1/rooms';
        $postData = [
            'properties' => [
                'exp' => time() + $duration,
                'enable_chat' => true,
                'enable_mesh_sfu' => true,
                'max_participants' => 200,
                'experimental_optimize_large_calls' => true
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false || $httpCode >= 400) {
            error_log("Daily.co API error ($httpCode): " . $result);
            return null;
        }

        return json_decode($result, true);
    }
}

// Endpoint: GET /meetings/:code
if ($method === 'GET' && preg_match('#^/meetings/([A-Za-z0-9_]+)$#', $path, $matches)) {
    require_auth();
    $code = $matches[1];
    
    $stmt = $conn->prepare("SELECT * FROM praise_meetings WHERE meeting_code = ?");
    if ($stmt) {
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $meeting = $result->fetch_assoc();
        $stmt->close();
        
        if ($meeting) {
            // Guarantee Daily.co room URL exists (migrates legacy KingsConference meetings)
            if (empty($meeting['daily_room_url'])) {
                $daily = createDailyMeetingRoom($meeting['title']);
                if ($daily && !empty($daily['url'])) {
                    $meeting['daily_room_url'] = $daily['url'];
                    $update = $conn->prepare("UPDATE praise_meetings SET daily_room_url = ? WHERE id = ?");
                    if ($update) {
                        $update->bind_param("si", $daily['url'], $meeting['id']);
                        $update->execute();
                        $update->close();
                    }
                } else {
                    $meeting['daily_room_url'] = 'https://myvpr.daily.co/' . $meeting['meeting_code'];
                }
            }
            json_response(['success' => true, 'data' => $meeting]);
        }
    }
    json_response(['success' => false, 'message' => 'Meeting not found.'], 404);
}

// Endpoint: POST /meetings
if ($method === 'POST' && $path === '/meetings') {
    require_auth();
    $user_id = $_SESSION['user_id'];
    
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $title = isset($data['title']) ? trim($data['title']) : '';
    
    if (empty($title)) {
        json_response(['success' => false, 'message' => 'Meeting title is required.'], 400);
    }
    
    // Create Daily.co meeting room
    $daily = createDailyMeetingRoom($title);
    $daily_room_url = $daily['url'] ?? null;
    $meeting_code = $daily['name'] ?? substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 8);
    
    $stmt = $conn->prepare("INSERT INTO praise_meetings (user_id, title, meeting_code, daily_room_url, status, created_at) VALUES (?, ?, ?, ?, 'in-progress', NOW())");
    if ($stmt) {
        $stmt->bind_param("isss", $user_id, $title, $meeting_code, $daily_room_url);
        if ($stmt->execute()) {
            json_response([
                'success' => true, 
                'meeting_code' => $meeting_code,
                'daily_room_url' => $daily_room_url
            ]);
        }
        $stmt->close();
    }
    
    json_response(['success' => false, 'message' => 'Failed to create meeting.'], 500);
}
