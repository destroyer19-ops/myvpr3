<?php
$file = '/Users/home/Documents/myvprNew/api/endpoints/crusades.php';
$content = file_get_contents($file);

// 1. Update GET /crusades/:code to handle status and redact video_url
$get_search = <<<'EOD'
        if ($crusade) {
            $actual_url = $crusade['video_url'];
            
            if (strpos($actual_url, 'LIVE_STREAM_OFFICIAL_') === 0) {
EOD;

$get_replace = <<<'EOD'
        if ($crusade) {
            // Determine Status
            $status = 'live'; // Default
            $now = new DateTime();
            if (!empty($crusade['startTime'])) {
                $start = new DateTime($crusade['startTime']);
                if ($now < $start) {
                    $status = 'scheduled';
                }
            }
            if ($crusade['is_active'] == 0 && empty($crusade['startTime'])) {
                // Not active, no start time = ended
                $status = 'ended';
            } elseif ($crusade['is_active'] == 0 && !empty($crusade['startTime']) && $now > new DateTime($crusade['startTime'])) {
                $status = 'ended'; // manually ended
            }

            $crusade['status'] = $status;

            if ($status === 'scheduled') {
                $crusade['video_url'] = null;
                $crusade['resolved_video_url'] = null;
            } else {
                $actual_url = $crusade['video_url'];
                
                if (strpos($actual_url, 'LIVE_STREAM_OFFICIAL_') === 0) {
EOD;
$content = str_replace($get_search, $get_replace, $content);

// Close out the else block correctly in GET /crusades/:code
$get_close_search = <<<'EOD'
            }
            
            json_response(['success' => true, 'data' => $crusade]);
EOD;
$get_close_replace = <<<'EOD'
                }
            }
            
            json_response(['success' => true, 'data' => $crusade]);
EOD;
$content = str_replace($get_close_search, $get_close_replace, $content);

// 2. Update POST /crusades/:code/track to support visitor_session_id and GeoIP
$track_search = <<<'EOD'
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $stmt_check = $conn->prepare("SELECT id FROM praise_crusade_stats WHERE crusade_id = ? AND ip_address = ? AND created_at > NOW() - INTERVAL 4 HOUR");
            $stmt_check->bind_param("is", $crusade_id, $ip);
EOD;

$track_replace = <<<'EOD'
            $data = get_request_data();
            $visitor_session_id = $data['visitor_session_id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            // Allow tracking uniqueness by session id or ip
            if ($visitor_session_id) {
                $stmt_check = $conn->prepare("SELECT id FROM praise_crusade_stats WHERE crusade_id = ? AND visitor_session_id = ?");
                $stmt_check->bind_param("is", $crusade_id, $visitor_session_id);
            } else {
                $stmt_check = $conn->prepare("SELECT id FROM praise_crusade_stats WHERE crusade_id = ? AND ip_address = ? AND created_at > NOW() - INTERVAL 4 HOUR");
                $stmt_check->bind_param("is", $crusade_id, $ip);
            }
EOD;
$content = str_replace($track_search, $track_replace, $content);

$track_insert_search = <<<'EOD'
            if (!$exists) {
                $insert_stmt = $conn->prepare("INSERT INTO praise_crusade_stats (crusade_id, ip_address, user_agent) VALUES (?, ?, ?)");
                if ($insert_stmt) {
                    $insert_stmt->bind_param("iss", $crusade_id, $ip, $ua);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                }
EOD;
$track_insert_replace = <<<'EOD'
            if (!$exists) {
                // Fetch GeoIP roughly
                $country = null;
                $city = null;
                if ($ip !== '127.0.0.1' && $ip !== '::1') {
                    // Try simple IP lookup
                    $geo = @json_decode(file_get_contents("http://ip-api.com/json/{$ip}?fields=country,city"), true);
                    if ($geo && isset($geo['country'])) {
                        $country = $geo['country'];
                        $city = $geo['city'] ?? null;
                    }
                }
                
                $insert_stmt = $conn->prepare("INSERT INTO praise_crusade_stats (crusade_id, ip_address, user_agent, visitor_session_id, country, city) VALUES (?, ?, ?, ?, ?, ?)");
                if ($insert_stmt) {
                    $insert_stmt->bind_param("isssss", $crusade_id, $ip, $ua, $visitor_session_id, $country, $city);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                }
EOD;
$content = str_replace($track_insert_search, $track_insert_replace, $content);

file_put_contents($file, $content);
echo "Patched API File\n";
