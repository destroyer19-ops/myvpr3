<?php
// streaming.php - Streaming & Subscription endpoints

// 1. Get available streaming plans
if ($method === 'GET' && $path === '/streaming/plans') {
    $plans = [];
    $stmt = $conn->prepare("SELECT id, name, description, price, billing_period, max_duration_hours, max_viewers, resolution FROM streaming_plans WHERE is_active = 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $plans[] = $row;
        }
        $stmt->close();
    }
    json_response(['success' => true, 'data' => $plans]);
}

// 2. Provision AWS IVS Channel (Called after successful payment/subscription)
if ($method === 'POST' && $path === '/streaming/provision') {
    require_auth();
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    
    // In a real flow, you would verify the user just paid for a plan here.
    
    // Check if user already has an active channel
    $stmt = $conn->prepare("SELECT id FROM streaming_channels WHERE user_id = ? AND is_active = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        json_response(['success' => true, 'message' => 'Channel already provisioned.']);
    }
    $stmt->close();

    // Make sure AWS credentials exist
    $aws_key = $_ENV['AWS_ACCESS_KEY_ID'] ?? getenv('AWS_ACCESS_KEY_ID');
    $aws_secret = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? getenv('AWS_SECRET_ACCESS_KEY');
    
    if (!$aws_key || !$aws_secret) {
        json_response(['success' => false, 'message' => 'AWS credentials missing. Cannot provision channel.'], 500);
    }

    try {
        // Initialize AWS IVS Client
        // Note: Requires running `composer require aws/aws-sdk-php` in the api folder
        if (!class_exists('Aws\IVS\IVSClient')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }

        $ivsClient = new Aws\IVS\IVSClient([
            'version' => 'latest',
            'region'  => 'us-east-1', // IVS is globally distributed, but API calls go to a specific region
            'credentials' => [
                'key'    => $aws_key,
                'secret' => $aws_secret,
            ]
        ]);

        // Create the IVS Channel
        $result = $ivsClient->createChannel([
            'name' => 'vpr_user_' . $user_id,
            'type' => 'STANDARD', // or 'BASIC' depending on the plan
            'latencyMode' => 'LOW', 
            'authorized' => false // Set to true if you want private streams with tokens
        ]);

        $channel = $result['channel'];
        $streamKey = $result['streamKey'];

        // Save to Database
        $stmt = $conn->prepare("INSERT INTO streaming_channels (user_id, aws_channel_arn, aws_stream_key_arn, ingest_endpoint, playback_url) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("DB error preparing channel insert: " . $conn->error);
        }
        $stmt->bind_param("issss", $user_id, $channel['arn'], $streamKey['value'], $channel['ingestEndpoint'], $channel['playbackUrl']);
        $stmt->execute();
        $stmt->close();

        // Also create a basic active subscription record for them to test
        $plan_id = 1; // Assuming Plan ID 1 is the basic plan
        $end_date = date('Y-m-d H:i:s', strtotime('+30 days'));
        $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, subscription_type, streaming_plan_id, streaming_status, start_date, end_date) VALUES (?, 'streaming', ?, 'active', NOW(), ?)");
        if (!$stmt) {
            throw new Exception("DB error preparing subscription insert: " . $conn->error);
        }
        $stmt->bind_param("iis", $user_id, $plan_id, $end_date);
        $stmt->execute();
        $stmt->close();

        json_response(['success' => true, 'message' => 'AWS Channel successfully provisioned!', 'data' => ['channel' => $channel]]);
        
    } catch (\Throwable $e) {
        error_log('AWS IVS / DB Error: ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'Failed to provision streaming resources: ' . $e->getMessage()], 500);
    }
}

// 3. Get user's current streaming status/entitlement
if ($method === 'GET' && $path === '/streaming/status') {
    require_auth();
    $user_id = $_SESSION['user_id'];
    
    $status = [
        'has_active_plan' => false,
        'plan' => null,
        'channel' => null
    ];
    
    // Check subscription
    $stmt = $conn->prepare("
        SELECT s.streaming_status, s.end_date, 
               COALESCE(p.name, sp.name, 'Studio Broadcaster') AS name, 
               COALESCE(p.resolution, '1080p') AS resolution 
        FROM subscriptions s 
        LEFT JOIN streaming_plans p ON p.id = s.streaming_plan_id 
        LEFT JOIN subscription_plans sp ON (sp.id = s.plan_id OR sp.plan_code = s.plan_code)
        WHERE s.user_id = ? AND s.streaming_status = 'active' AND s.end_date > NOW()
        ORDER BY s.end_date DESC LIMIT 1
    ");
    
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $status['has_active_plan'] = true;
            $status['plan'] = $row;
        }
        $stmt->close();
    }
    
    // If they have a plan, check if they have a channel provisioned
    if ($status['has_active_plan']) {
        $stmt = $conn->prepare("SELECT ingest_endpoint, playback_url, aws_stream_key_arn FROM streaming_channels WHERE user_id = ? AND is_active = 1");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $status['channel'] = $row;
            }
            $stmt->close();
        }
    }
    
    json_response(['success' => true, 'data' => $status]);
}

// 4. Public viewing endpoint (No auth required)
if ($method === 'GET' && preg_match('#^/streaming/watch/([a-zA-Z0-9_]+)$#', $path, $matches)) {
    $target_username = $matches[1];
    
    // Find the user and their active channel
    $stmt = $conn->prepare("
        SELECT c.playback_url, u.id as broadcaster_id
        FROM streaming_channels c
        JOIN praise_users u ON u.id = c.user_id
        WHERE u.username = ? AND c.is_active = 1
    ");
    
    if ($stmt) {
        $stmt->bind_param("s", $target_username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            // Found the channel
            json_response([
                'success' => true, 
                'data' => [
                    'playback_url' => $row['playback_url'],
                    'chat_room_id' => 'stream_chat_' . $target_username
                ]
            ]);
        }
        $stmt->close();
    }
    
    json_response(['success' => false, 'message' => 'Stream not found or offline.'], 404);
}

// 5. Reset Stream Key
if ($method === 'POST' && $path === '/streaming/channel/reset-key') {
    require_auth();
    $user_id = $_SESSION['user_id'];

    // Get the user's current channel ARN
    $stmt = $conn->prepare("SELECT id, aws_channel_arn FROM streaming_channels WHERE user_id = ? AND is_active = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $channel_id = $row['id'];
        $channel_arn = $row['aws_channel_arn'];
    } else {
        $stmt->close();
        json_response(['success' => false, 'message' => 'No active channel found.'], 404);
    }
    $stmt->close();

    $aws_key = $_ENV['AWS_ACCESS_KEY_ID'] ?? getenv('AWS_ACCESS_KEY_ID');
    $aws_secret = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? getenv('AWS_SECRET_ACCESS_KEY');

    if (!$aws_key || !$aws_secret) {
        // Dummy reset
        $new_dummy_key = "sk_us-east-1_dummy" . rand(10000, 99999);
        
        $stmt = $conn->prepare("UPDATE streaming_channels SET aws_stream_key_arn = ? WHERE id = ?");
        $stmt->bind_param("si", $new_dummy_key, $channel_id);
        $stmt->execute();
        $stmt->close();
        
        json_response(['success' => true, 'message' => 'Stream key reset (Dummy mode).', 'new_key' => $new_dummy_key]);
    }

    try {
        if (!class_exists('Aws\IVS\IVSClient')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }

        $ivsClient = new Aws\IVS\IVSClient([
            'version' => 'latest',
            'region'  => 'us-east-1',
            'credentials' => [
                'key'    => $aws_key,
                'secret' => $aws_secret,
            ]
        ]);

        // 1. List existing stream keys for this channel
        $listResult = $ivsClient->listStreamKeys(['channelArn' => $channel_arn]);
        $existingKeys = $listResult['streamKeys'] ?? [];

        // 2. Delete all existing keys to invalidate them
        foreach ($existingKeys as $keySummary) {
            $ivsClient->deleteStreamKey(['arn' => $keySummary['arn']]);
        }

        // 3. Create a new stream key
        $createResult = $ivsClient->createStreamKey(['channelArn' => $channel_arn]);
        $newStreamKey = $createResult['streamKey']['value'];

        // 4. Update the database
        $stmt = $conn->prepare("UPDATE streaming_channels SET aws_stream_key_arn = ? WHERE id = ?");
        $stmt->bind_param("si", $newStreamKey, $channel_id);
        $stmt->execute();
        $stmt->close();

        json_response(['success' => true, 'message' => 'Stream key successfully reset.', 'new_key' => $newStreamKey]);

    } catch (Exception $e) {
        error_log('AWS IVS Error (Reset Key): ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'Failed to reset stream key on AWS.'], 500);
    }
}

// 6. Start Broadcast
if ($method === 'POST' && $path === '/streaming/broadcast/start') {
    require_auth();
    $user_id = $_SESSION['user_id'];
    
    // Get active channel
    $stmt = $conn->prepare("SELECT id FROM streaming_channels WHERE user_id = ? AND is_active = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $channel_id = $row['id'];
        $stmt->close();
        
        // Insert a new streaming session
        $insert_stmt = $conn->prepare("INSERT INTO streaming_sessions (channel_id, start_time, status) VALUES (?, NOW(), 'live')");
        $insert_stmt->bind_param("i", $channel_id);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        json_response(['success' => true, 'message' => 'Broadcast started.']);
    } else {
        $stmt->close();
        json_response(['success' => false, 'message' => 'No active channel found.'], 404);
    }
}

// 7. Stop Broadcast
if ($method === 'POST' && $path === '/streaming/broadcast/stop') {
    require_auth();
    $user_id = $_SESSION['user_id'];
    
    // Get active channel
    $stmt = $conn->prepare("SELECT id FROM streaming_channels WHERE user_id = ? AND is_active = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $channel_id = $row['id'];
        $stmt->close();
        
        // Update the active streaming session
        $update_stmt = $conn->prepare("UPDATE streaming_sessions SET status = 'ended', end_time = NOW() WHERE channel_id = ? AND status = 'live'");
        $update_stmt->bind_param("i", $channel_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        json_response(['success' => true, 'message' => 'Broadcast stopped.']);
    } else {
        $stmt->close();
        json_response(['success' => false, 'message' => 'No active channel found.'], 404);
    }
}

// 8. Temporary Payment Proof Upload
if ($method === 'POST' && $path === '/streaming/payment/upload') {
    require_auth();
    $user_id = $_SESSION['user_id'];
    $plan_id = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT);
    
    if (!$plan_id) {
        json_response(['success' => false, 'message' => 'Invalid plan ID.'], 400);
    }
    
    if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'No file uploaded or upload error.'], 400);
    }
    
    // SECURITY FIX: Strict file validation to prevent RCE
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $allowed_mime_types = ['image/jpeg', 'image/png', 'application/pdf'];
    
    $file_info = pathinfo($_FILES['proof']['name']);
    $extension = strtolower($file_info['extension'] ?? '');
    
    // Validate MIME type natively
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $_FILES['proof']['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($extension, $allowed_extensions) || !in_array($mime_type, $allowed_mime_types)) {
        json_response(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF are allowed.'], 400);
    }
    
    // Generate a secure random filename to prevent path traversal/execution
    $safe_filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $upload_dir = __DIR__ . '/../../uploads/proofs/';
    
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    
    $target_path = $upload_dir . $safe_filename;
    
    if (move_uploaded_file($_FILES['proof']['tmp_name'], $target_path)) {
        
        // Fetch plan details
        $stmt = $conn->prepare("SELECT name, price FROM streaming_plans WHERE id = ?");
        $stmt->bind_param("i", $plan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $plan = $result->fetch_assoc();
        $stmt->close();
        
        // Insert into transactions
        $sub_type = 'streaming_plan_' . $plan_id;
        $amount = $plan ? $plan['price'] : 0.00;
        
        $insert_stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, proof_of_transaction, subscription_type, status) VALUES (?, ?, ?, ?, 'pending')");
        $insert_stmt->bind_param("idss", $user_id, $amount, $safe_filename, $sub_type);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        json_response(['success' => true, 'message' => 'Proof uploaded. Pending admin approval.']);
    } else {
        json_response(['success' => false, 'message' => 'Failed to save uploaded file.'], 500);
    }
}
