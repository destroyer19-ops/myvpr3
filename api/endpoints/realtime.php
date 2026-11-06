<?php
// realtime.php - Real-Time WebRTC Streaming endpoints

// 1. Provision / Join an IVS Real-Time Stage
if ($method === 'POST' && $path === '/realtime/provision') {
    $data = get_request_data();
    
    // Broadcaster vs Viewer role
    $role = isset($data['role']) ? $data['role'] : 'viewer';
    $target_username = isset($data['broadcaster_username']) ? $data['broadcaster_username'] : (isset($data['username']) ? $data['username'] : null);

    if ($role === 'broadcaster') {
        require_auth();
        $user_id = $_SESSION['user_id'];
        // Broadcaster provisioning
        $session_id = 'rt_sess_' . $user_id . '_' . time();
        
        // Make sure AWS credentials exist
        $aws_key = $_ENV['AWS_ACCESS_KEY_ID'] ?? getenv('AWS_ACCESS_KEY_ID');
        $aws_secret = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? getenv('AWS_SECRET_ACCESS_KEY');
        
        if (!$aws_key || !$aws_secret) {
            json_response(['success' => false, 'message' => 'AWS credentials missing.'], 500);
        }

        try {
            if (!class_exists('Aws\IVSRealTime\IVSRealTimeClient')) {
                require_once __DIR__ . '/../vendor/autoload.php';
            }

            $ivsRealTime = new Aws\IVSRealTime\IVSRealTimeClient([
                'version' => 'latest',
                'region'  => 'us-east-1',
                'suppress_php_deprecation_warning' => true,
                'credentials' => [
                    'key'    => $aws_key,
                    'secret' => $aws_secret,
                ]
            ]);

            // Check if there is an active stage for this user
            $stmt = $conn->prepare("SELECT stage_arn, session_id FROM realtime_stream_sessions WHERE user_id = ? AND status = 'live'");
            if (!$stmt) {
                throw new Exception("DB error preparing select: " . $conn->error);
            }
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $stage_arn = null;
            if ($row = $result->fetch_assoc()) {
                $stage_arn = $row['stage_arn'];
                $session_id = $row['session_id'];
            }
            $stmt->close();

            if (!$stage_arn) {
                // Create new Stage
                $createStageResult = $ivsRealTime->createStage([
                    'name' => 'vpr_rt_' . $user_id . '_' . time()
                ]);
                $stage_arn = $createStageResult['stage']['arn'];

                $insert_stmt = $conn->prepare("INSERT INTO realtime_stream_sessions (user_id, stage_arn, session_id, status) VALUES (?, ?, ?, 'live')");
                $insert_stmt->bind_param("iss", $user_id, $stage_arn, $session_id);
                $insert_stmt->execute();
                $insert_stmt->close();
            }

            // Create participant token for PUBLISH/SUBSCRIBE
            $tokenResult = $ivsRealTime->createParticipantToken([
                'stageArn' => $stage_arn,
                'duration' => 20160,
                'userId' => (string)$user_id,
                'capabilities' => ['PUBLISH', 'SUBSCRIBE'],
            ]);

            $participantToken = $tokenResult['participantToken']['token'];

            json_response([
                'success' => true,
                'stage_arn' => $stage_arn,
                'session_id' => $session_id,
                'token' => $participantToken
            ]);

        } catch (\Throwable $e) {
            error_log('AWS IVS RealTime Error: ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Failed to provision Real-Time Stage: ' . $e->getMessage()], 500);
        }
    } else {
        // Viewer provisioning
        if (!$target_username) {
            json_response(['success' => false, 'message' => 'Missing username to watch.'], 400);
        }

        // Find the user ID of the target broadcaster
        $stmt = $conn->prepare("SELECT id FROM praise_users WHERE username = ?");
        $stmt->bind_param("s", $target_username);
        $stmt->execute();
        $user_res = $stmt->get_result();
        if ($user_row = $user_res->fetch_assoc()) {
            $broadcaster_id = $user_row['id'];
        } else {
            json_response(['success' => false, 'message' => 'Broadcaster not found.'], 404);
        }
        $stmt->close();

        // Find the active stage for the broadcaster
        $stmt = $conn->prepare("SELECT stage_arn FROM realtime_stream_sessions WHERE user_id = ? AND status = 'live' ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $broadcaster_id);
        $stmt->execute();
        $stage_res = $stmt->get_result();
        if ($stage_row = $stage_res->fetch_assoc()) {
            $stage_arn = $stage_row['stage_arn'];
        } else {
            json_response(['success' => false, 'message' => 'Broadcaster is not currently live in Real-Time mode.'], 404);
        }
        $stmt->close();

        // Make sure AWS credentials exist
        $aws_key = $_ENV['AWS_ACCESS_KEY_ID'] ?? getenv('AWS_ACCESS_KEY_ID');
        $aws_secret = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? getenv('AWS_SECRET_ACCESS_KEY');

        try {
            if (!class_exists('Aws\IVSRealTime\IVSRealTimeClient')) {
                require_once __DIR__ . '/../vendor/autoload.php';
            }

            $ivsRealTime = new Aws\IVSRealTime\IVSRealTimeClient([
                'version' => 'latest',
                'region'  => 'us-east-1',
                'suppress_php_deprecation_warning' => true,
                'credentials' => [
                    'key'    => $aws_key,
                    'secret' => $aws_secret,
                ]
            ]);

            $viewer_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : uniqid('guest_');
            // Create participant token for SUBSCRIBE ONLY
            $tokenResult = $ivsRealTime->createParticipantToken([
                'stageArn' => $stage_arn,
                'duration' => 20160,
                'userId' => 'viewer_' . $viewer_id,
                'capabilities' => ['SUBSCRIBE'],
            ]);

            $participantToken = $tokenResult['participantToken']['token'];

            json_response([
                'success' => true,
                'stage_arn' => $stage_arn,
                'token' => $participantToken
            ]);

        } catch (\Throwable $e) {
            error_log('AWS IVS RealTime Viewer Error: ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Failed to join Real-Time Stage: ' . $e->getMessage()], 500);
        }
    }
}

// 2. End Real-Time Broadcast
if ($method === 'POST' && $path === '/realtime/end') {
    require_auth();
    $user_id = $_SESSION['user_id'];

    // Retrieve active stage to delete
    $stmt = $conn->prepare("SELECT stage_arn FROM realtime_stream_sessions WHERE user_id = ? AND status = 'live'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stage_arn = null;
    if ($row = $result->fetch_assoc()) {
        $stage_arn = $row['stage_arn'];
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE realtime_stream_sessions SET status = 'ended', ended_at = NOW() WHERE user_id = ? AND status = 'live'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete the stage in AWS
    if ($stage_arn) {
        $aws_key = $_ENV['AWS_ACCESS_KEY_ID'] ?? getenv('AWS_ACCESS_KEY_ID');
        $aws_secret = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? getenv('AWS_SECRET_ACCESS_KEY');

        if ($aws_key && $aws_secret) {
            try {
                if (!class_exists('Aws\IVSRealTime\IVSRealTimeClient')) {
                    require_once __DIR__ . '/../vendor/autoload.php';
                }

                $ivsRealTime = new Aws\IVSRealTime\IVSRealTimeClient([
                    'version' => 'latest',
                    'region'  => 'us-east-1',
                    'suppress_php_deprecation_warning' => true,
                    'credentials' => [
                        'key'    => $aws_key,
                        'secret' => $aws_secret,
                    ]
                ]);

                $ivsRealTime->deleteStage([
                    'arn' => $stage_arn
                ]);
            } catch (\Throwable $e) {
                error_log('AWS IVS DeleteStage Error: ' . $e->getMessage());
            }
        }
    }

    json_response(['success' => true, 'message' => 'Real-Time Broadcast ended and Stage deleted.']);
}

// 3. Cron Cleanup for Stale Stages
if ($method === 'POST' && $path === '/realtime/cleanup') {
    // Ideally protect this with a secret cron key in production
    $cron_secret = $_ENV['CRON_SECRET'] ?? getenv('CRON_SECRET');
    $req_secret = $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
    
    if ($cron_secret && $req_secret !== $cron_secret) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $aws_key = $_ENV['AWS_ACCESS_KEY_ID'] ?? getenv('AWS_ACCESS_KEY_ID');
    $aws_secret = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? getenv('AWS_SECRET_ACCESS_KEY');

    if (!$aws_key || !$aws_secret) {
        json_response(['success' => false, 'message' => 'AWS credentials missing.'], 500);
    }

    // Find live sessions older than 4 hours
    $stmt = $conn->prepare("SELECT id, stage_arn FROM realtime_stream_sessions WHERE status = 'live' AND created_at < DATE_SUB(NOW(), INTERVAL 4 HOUR)");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $deleted_count = 0;
    
    if ($result->num_rows > 0) {
        if (!class_exists('Aws\IVSRealTime\IVSRealTimeClient')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }

        $ivsRealTime = new Aws\IVSRealTime\IVSRealTimeClient([
            'version' => 'latest',
            'region'  => 'us-east-1',
            'suppress_php_deprecation_warning' => true,
            'credentials' => [
                'key'    => $aws_key,
                'secret' => $aws_secret,
            ]
        ]);

        while ($row = $result->fetch_assoc()) {
            try {
                $ivsRealTime->deleteStage([
                    'arn' => $row['stage_arn']
                ]);
            } catch (\Throwable $e) {
                error_log('AWS IVS DeleteStage Error (Cron): ' . $e->getMessage());
            }

            $update_stmt = $conn->prepare("UPDATE realtime_stream_sessions SET status = 'ended', ended_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $row['id']);
            $update_stmt->execute();
            $update_stmt->close();
            $deleted_count++;
        }
    }
    $stmt->close();

    json_response(['success' => true, 'message' => "Cleanup complete. Deleted $deleted_count stale stages."]);
}
