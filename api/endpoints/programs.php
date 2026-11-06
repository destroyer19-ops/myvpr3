<?php
/**
 * api/endpoints/programs.php
 * Handles Programs, Program Recordings, Video CMS, and User Viewing Progress (Continue Watching).
 */

// ── GET /programs ─────────────────────────────────────────────────────────────
// Returns separated Live Now broadcast, published recordings, and upcoming programs
if ($method === 'GET' && $path === '/programs') {
    // 1. Fetch live broadcast info
    $live_stream = null;
    $res_stream = $conn->query("SELECT id, title, description, stream_url, thumbnail_url, is_live FROM praise_live_tv WHERE is_live = 1 ORDER BY created_at DESC LIMIT 1");
    if ($res_stream && $res_stream->num_rows > 0) {
        $live_stream = $res_stream->fetch_assoc();
    }

    // 2. Fetch current Live Now program (only 1 can be live at a time)
    $live_now = null;
    if ($live_stream) {
        $res_prog = $conn->query("SELECT id, title, description, thumbnail_url, category, schedule_time, is_live_now FROM praise_programs WHERE is_live_now = 1 LIMIT 1");
        if ($res_prog && $res_prog->num_rows > 0) {
            $prog = $res_prog->fetch_assoc();
            $live_now = array_merge($prog, [
                'stream_url' => $live_stream['stream_url'],
                'live_stream_id' => $live_stream['id'],
                'is_live' => true,
                'playback_type' => 'live_hls'
            ]);
        } else {
            // Fallback to the live stream channel metadata if no specific program is mapped
            $live_now = [
                'id' => $live_stream['id'],
                'title' => $live_stream['title'],
                'description' => $live_stream['description'],
                'thumbnail_url' => $live_stream['thumbnail_url'],
                'category' => 'Live Service',
                'stream_url' => $live_stream['stream_url'],
                'is_live' => true,
                'playback_type' => 'live_hls'
            ];
        }
    }

    // 3. Fetch published recordings (ordered by program_date DESC)
    $recordings = [];
    $res_rec = $conn->query("
        SELECT r.id, r.program_id, r.title, r.description, r.thumbnail_url, r.video_url,
               r.duration_seconds, r.program_date, r.category, r.status, r.created_at,
               p.title AS program_title
        FROM praise_program_recordings r
        LEFT JOIN praise_programs p ON p.id = r.program_id
        WHERE r.status = 'published'
        ORDER BY r.program_date DESC, r.created_at DESC
        LIMIT 20
    ");
    if ($res_rec) {
        while ($row = $res_rec->fetch_assoc()) {
            $recordings[] = $row;
        }
    }

    // 4. Fetch upcoming scheduled programs
    $upcoming = [];
    $res_up = $conn->query("
        SELECT id, title, description, thumbnail_url, category, schedule_time
        FROM praise_programs
        WHERE schedule_time > NOW() AND is_live_now = 0
        ORDER BY schedule_time ASC
        LIMIT 10
    ");
    if ($res_up) {
        while ($row = $res_up->fetch_assoc()) {
            $upcoming[] = $row;
        }
    }

    json_response([
        'success' => true,
        'data' => [
            'live_now' => $live_now,
            'recordings' => $recordings,
            'upcoming' => $upcoming
        ]
    ]);
}

// ── GET /recordings/:id ───────────────────────────────────────────────────────
if ($method === 'GET' && preg_match('#^/recordings/(\d+)$#', $path, $matches)) {
    $rec_id = (int)$matches[1];
    $stmt = $conn->prepare("
        SELECT r.*, p.title AS program_title
        FROM praise_program_recordings r
        LEFT JOIN praise_programs p ON p.id = r.program_id
        WHERE r.id = ?
    ");
    $stmt->bind_param("i", $rec_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $recording = $res->fetch_assoc();
    $stmt->close();

    if (!$recording) {
        json_response(['success' => false, 'message' => 'Recording not found.'], 404);
    }

    // Include user's previous progress if authenticated
    $saved_progress = 0;
    if (!empty($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $stmt_wh = $conn->prepare("SELECT progress_seconds FROM praise_watch_history WHERE user_id = ? AND recording_id = ?");
        $stmt_wh->bind_param("ii", $uid, $rec_id);
        $stmt_wh->execute();
        $res_wh = $stmt_wh->get_result();
        if ($row_wh = $res_wh->fetch_assoc()) {
            $saved_progress = (int)$row_wh['progress_seconds'];
        }
        $stmt_wh->close();
    }
    $recording['saved_progress'] = $saved_progress;

    json_response(['success' => true, 'data' => $recording]);
}

// ── GET /user/continue-watching ───────────────────────────────────────────────
if ($method === 'GET' && $path === '/user/continue-watching') {
    require_auth();
    $user_id = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT wh.recording_id, wh.progress_seconds, wh.duration_seconds, wh.last_watched_at,
               r.title, r.description, r.thumbnail_url, r.video_url, r.category, r.program_date,
               p.title AS program_title
        FROM praise_watch_history wh
        JOIN praise_program_recordings r ON r.id = wh.recording_id
        LEFT JOIN praise_programs p ON p.id = r.program_id
        WHERE wh.user_id = ? AND wh.completed = 0 AND wh.progress_seconds > 10
        ORDER BY wh.last_watched_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $dur = (int)$row['duration_seconds'];
        $prog = (int)$row['progress_seconds'];
        $pct = $dur > 0 ? min(99, max(1, round(($prog / $dur) * 100))) : 0;
        $remaining = max(0, $dur - $prog);
        $min_left = max(1, ceil($remaining / 60));

        $items[] = [
            'recording_id' => (int)$row['recording_id'],
            'title' => $row['title'],
            'category' => $row['category'] ?: 'Replay',
            'thumbnail_url' => $row['thumbnail_url'],
            'video_url' => $row['video_url'],
            'progress' => $pct,
            'progress_seconds' => $prog,
            'duration_seconds' => $dur,
            'timeLeft' => "{$min_left} min left",
            'last_watched_at' => $row['last_watched_at']
        ];
    }
    $stmt->close();

    json_response(['success' => true, 'data' => $items]);
}

// ── POST /watch-history ───────────────────────────────────────────────────────
if ($method === 'POST' && $path === '/watch-history') {
    require_auth();
    $user_id = (int)$_SESSION['user_id'];
    $data = get_request_data();

    $recording_id = (int)($data['recording_id'] ?? 0);
    $progress_seconds = (int)($data['progress_seconds'] ?? 0);
    $duration_seconds = (int)($data['duration_seconds'] ?? 0);
    $is_explicit_complete = !empty($data['completed']);

    if ($recording_id <= 0) {
        json_response(['success' => false, 'message' => 'Valid recording ID required.'], 400);
    }

    // If user watched >= 90% of video or explicit complete, mark completed = 1
    $completed = ($is_explicit_complete || ($duration_seconds > 0 && ($progress_seconds / $duration_seconds) >= 0.90)) ? 1 : 0;

    $stmt = $conn->prepare("
        INSERT INTO praise_watch_history (user_id, recording_id, progress_seconds, duration_seconds, completed, last_watched_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            progress_seconds = VALUES(progress_seconds),
            duration_seconds = VALUES(duration_seconds),
            completed = VALUES(completed),
            last_watched_at = NOW()
    ");
    $stmt->bind_param("iiiii", $user_id, $recording_id, $progress_seconds, $duration_seconds, $completed);
    $stmt->execute();
    $stmt->close();

    json_response(['success' => true, 'completed' => (bool)$completed]);
}

// ── Admin: GET /admin/programs ────────────────────────────────────────────────
if ($method === 'GET' && $path === '/admin/programs') {
    require_admin();
    $programs = [];
    $res = $conn->query("SELECT * FROM praise_programs ORDER BY created_at DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) $programs[] = $row;
    }
    json_response(['success' => true, 'data' => $programs]);
}

// ── Admin: POST /admin/programs ───────────────────────────────────────────────
if ($method === 'POST' && $path === '/admin/programs') {
    require_admin();
    $data = get_request_data();
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $category = trim($data['category'] ?? 'Worship');
    $thumbnail_url = trim($data['thumbnail_url'] ?? '');
    $schedule_time = !empty($data['schedule_time']) ? date('Y-m-d H:i:s', strtotime($data['schedule_time'])) : null;
    $is_live = !empty($data['is_live_now']) ? 1 : 0;

    if (empty($title)) {
        json_response(['success' => false, 'message' => 'Program title is required.'], 400);
    }

    if ($is_live) {
        $conn->query("UPDATE praise_programs SET is_live_now = 0");
    }

    $stmt = $conn->prepare("INSERT INTO praise_programs (title, description, category, thumbnail_url, schedule_time, is_live_now, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssi", $title, $description, $category, $thumbnail_url, $schedule_time, $is_live);
    if ($stmt->execute()) {
        $insert_id = $conn->insert_id;
        $stmt->close();
        json_response(['success' => true, 'message' => 'Program created successfully.', 'id' => $insert_id]);
    } else {
        json_response(['success' => false, 'message' => 'Failed to create program.'], 500);
    }
}

// ── Admin: POST /admin/programs/:id/set-live ──────────────────────────────────
if ($method === 'POST' && preg_match('#^/admin/programs/(\d+)/set-live$#', $path, $matches)) {
    require_admin();
    $prog_id = (int)$matches[1];
    $data = get_request_data();
    $is_live = isset($data['is_live']) ? (int)$data['is_live'] : 1;

    $conn->query("UPDATE praise_programs SET is_live_now = 0");
    if ($is_live === 1) {
        $stmt = $conn->prepare("UPDATE praise_programs SET is_live_now = 1 WHERE id = ?");
        $stmt->bind_param("i", $prog_id);
        $stmt->execute();
        $stmt->close();
    }
    json_response(['success' => true, 'message' => 'Live broadcast status updated.']);
}

// ── Admin: GET /admin/recordings ──────────────────────────────────────────────
if ($method === 'GET' && $path === '/admin/recordings') {
    require_admin();
    $recordings = [];
    $res = $conn->query("
        SELECT r.*, p.title AS program_title
        FROM praise_program_recordings r
        LEFT JOIN praise_programs p ON p.id = r.program_id
        ORDER BY r.created_at DESC
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) $recordings[] = $row;
    }
    json_response(['success' => true, 'data' => $recordings]);
}

// ── Admin: GET /admin/upload/s3-config ─────────────────────────────────────────
if ($method === 'GET' && $path === '/admin/upload/s3-config') {
    require_admin();
    $bucket = getenv('AWS_S3_BUCKET') ?: ($_ENV['AWS_S3_BUCKET'] ?? '');
    $aws_key = getenv('AWS_ACCESS_KEY_ID') ?: ($_ENV['AWS_ACCESS_KEY_ID'] ?? '');
    $aws_secret = getenv('AWS_SECRET_ACCESS_KEY') ?: ($_ENV['AWS_SECRET_ACCESS_KEY'] ?? '');
    $region = getenv('AWS_DEFAULT_REGION') ?: (getenv('AWS_REGION') ?: ($_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1'));
    $cloudfront = getenv('AWS_CLOUDFRONT_URL') ?: ($_ENV['AWS_CLOUDFRONT_URL'] ?? '');

    $configured = !empty($bucket) && !empty($aws_key) && !empty($aws_secret);

    json_response([
        'success' => true,
        'configured' => $configured,
        'bucket' => $bucket ? substr($bucket, 0, 3) . '***' : null,
        'region' => $region,
        'has_cloudfront' => !empty($cloudfront)
    ]);
}

// ── Admin: GET /admin/upload/s3-presigned ──────────────────────────────────────
if ($method === 'GET' && $path === '/admin/upload/s3-presigned') {
    require_admin();

    $bucket = getenv('AWS_S3_BUCKET') ?: ($_ENV['AWS_S3_BUCKET'] ?? '');
    $aws_key = getenv('AWS_ACCESS_KEY_ID') ?: ($_ENV['AWS_ACCESS_KEY_ID'] ?? '');
    $aws_secret = getenv('AWS_SECRET_ACCESS_KEY') ?: ($_ENV['AWS_SECRET_ACCESS_KEY'] ?? '');
    $region = getenv('AWS_DEFAULT_REGION') ?: (getenv('AWS_REGION') ?: ($_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1'));
    $cloudfront = getenv('AWS_CLOUDFRONT_URL') ?: ($_ENV['AWS_CLOUDFRONT_URL'] ?? '');

    if (empty($bucket) || empty($aws_key) || empty($aws_secret)) {
        json_response([
            'success' => false,
            'configured' => false,
            'message' => 'AWS S3 is not configured in .env. Please set AWS_S3_BUCKET, AWS_ACCESS_KEY_ID, and AWS_SECRET_ACCESS_KEY.'
        ], 400);
    }

    $filename = trim($_GET['filename'] ?? '');
    $file_type = trim($_GET['file_type'] ?? 'video/mp4');
    $folder = trim($_GET['folder'] ?? 'recordings');

    if (empty($filename)) {
        json_response(['success' => false, 'message' => 'Filename is required.'], 400);
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed_exts = ['mp4', 'webm', 'mov', 'm4v', 'mkv', 'jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed_exts)) {
        json_response(['success' => false, 'message' => "Unsupported file format: .{$ext}"], 400);
    }

    $sanitized_folder = ($folder === 'thumbnails') ? 'thumbnails' : 'recordings';
    $key = "vpr/{$sanitized_folder}/" . date('Y/m/') . 'rec_' . bin2hex(random_bytes(8)) . '.' . $ext;

    try {
        $autoload_path = dirname(__DIR__) . '/vendor/autoload.php';
        if (!file_exists($autoload_path)) {
            json_response(['success' => false, 'message' => 'AWS SDK vendor autoload not found.'], 500);
        }
        require_once $autoload_path;

        $s3 = new Aws\S3\S3Client([
            'version' => 'latest',
            'region'  => $region,
            'credentials' => [
                'key'    => $aws_key,
                'secret' => $aws_secret,
            ]
        ]);

        $cmd = $s3->getCommand('PutObject', [
            'Bucket'      => $bucket,
            'Key'         => $key,
            'ContentType' => $file_type,
        ]);

        $request = $s3->createPresignedRequest($cmd, '+30 minutes');
        $presigned_url = (string)$request->getUri();

        if (!empty($cloudfront)) {
            $file_url = rtrim($cloudfront, '/') . '/' . $key;
        } else {
            $file_url = "https://{$bucket}.s3.{$region}.amazonaws.com/{$key}";
        }

        json_response([
            'success' => true,
            'configured' => true,
            'data' => [
                'presigned_url' => $presigned_url,
                'file_url'      => $file_url,
                'key'           => $key,
                'bucket'        => $bucket,
                'region'        => $region,
                'expires_in'    => 1800
            ]
        ]);
    } catch (Exception $e) {
        json_response([
            'success' => false,
            'message' => 'Failed to generate AWS S3 presigned URL: ' . $e->getMessage()
        ], 500);
    }
}

// ── Admin: POST /admin/upload/video ───────────────────────────────────────────
if ($method === 'POST' && $path === '/admin/upload/video') {
    require_admin();

    if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['video']['error'] ?? 'No file provided';
        json_response(['success' => false, 'message' => "Upload error code: {$err}"], 400);
    }

    $file = $_FILES['video'];
    $allowed_exts = ['mp4', 'webm', 'mov', 'm4v'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_exts)) {
        json_response(['success' => false, 'message' => 'Invalid video format. Allowed formats: MP4, WebM, MOV.'], 400);
    }

    // Size limit: 500MB
    $max_bytes = 500 * 1024 * 1024;
    if ($file['size'] > $max_bytes) {
        json_response(['success' => false, 'message' => 'Video exceeds max limit of 500MB.'], 400);
    }

    $upload_dir = dirname(dirname(__DIR__)) . '/uploads/recordings/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }

    $safe_name = 'rec_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $target = $upload_dir . $safe_name;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        $public_url = '/uploads/recordings/' . $safe_name;
        json_response([
            'success' => true,
            'message' => 'Video uploaded successfully.',
            'video_url' => $public_url,
            'filename' => $safe_name,
            'size_bytes' => $file['size']
        ]);
    } else {
        json_response(['success' => false, 'message' => 'Failed to save uploaded video to storage.'], 500);
    }
}

// ── Admin: POST /admin/recordings ─────────────────────────────────────────────
if ($method === 'POST' && $path === '/admin/recordings') {
    require_admin();
    $data = get_request_data();

    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $video_url = trim($data['video_url'] ?? '');
    $program_id = !empty($data['program_id']) ? (int)$data['program_id'] : null;
    $category = trim($data['category'] ?? 'Worship');
    $program_date = !empty($data['program_date']) ? date('Y-m-d', strtotime($data['program_date'])) : date('Y-m-d');
    $status = in_array($data['status'] ?? '', ['draft', 'published', 'processing']) ? $data['status'] : 'draft';
    $duration_seconds = (int)($data['duration_seconds'] ?? 0);
    $thumbnail_url = trim($data['thumbnail_url'] ?? '');
    $admin_id = $_SESSION['user_id'] ?? null;

    // Handle thumbnail image upload if present
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $img_dir = dirname(dirname(__DIR__)) . '/uploads/recordings/';
        if (!is_dir($img_dir)) @mkdir($img_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $img_name = 'thumb_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $img_dir . $img_name)) {
                $thumbnail_url = '/uploads/recordings/' . $img_name;
            }
        }
    }

    // Handle video file upload if provided directly in multipart
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $vid_dir = dirname(dirname(__DIR__)) . '/uploads/recordings/';
        if (!is_dir($vid_dir)) @mkdir($vid_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['mp4', 'webm', 'mov', 'm4v'])) {
            $vid_name = 'rec_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (move_uploaded_file($_FILES['video']['tmp_name'], $vid_dir . $vid_name)) {
                $video_url = '/uploads/recordings/' . $vid_name;
            }
        }
    }

    if (empty($title) || empty($video_url)) {
        json_response(['success' => false, 'message' => 'Recording title and video URL or uploaded file are required.'], 400);
    }

    $stmt = $conn->prepare("
        INSERT INTO praise_program_recordings (program_id, title, description, thumbnail_url, video_url, duration_seconds, program_date, category, status, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("issssisssi", $program_id, $title, $description, $thumbnail_url, $video_url, $duration_seconds, $program_date, $category, $status, $admin_id);
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $stmt->close();
        json_response([
            'success' => true,
            'message' => 'Recording saved successfully.',
            'id' => $new_id,
            'status' => $status
        ]);
    } else {
        json_response(['success' => false, 'message' => 'Failed to save recording to database.'], 500);
    }
}

// ── Admin: PUT /admin/recordings/:id ──────────────────────────────────────────
if (($method === 'PUT' || $method === 'POST') && preg_match('#^/admin/recordings/(\d+)$#', $path, $matches)) {
    require_admin();
    $rec_id = (int)$matches[1];
    $data = get_request_data();

    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $video_url = trim($data['video_url'] ?? '');
    $program_id = !empty($data['program_id']) ? (int)$data['program_id'] : null;
    $category = trim($data['category'] ?? '');
    $program_date = !empty($data['program_date']) ? date('Y-m-d', strtotime($data['program_date'])) : null;
    $status = in_array($data['status'] ?? '', ['draft', 'published', 'processing']) ? $data['status'] : 'draft';
    $duration_seconds = isset($data['duration_seconds']) ? (int)$data['duration_seconds'] : 0;
    $thumbnail_url = trim($data['thumbnail_url'] ?? '');

    // Handle thumbnail replacement if provided
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $img_dir = dirname(dirname(__DIR__)) . '/uploads/recordings/';
        if (!is_dir($img_dir)) @mkdir($img_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $img_name = 'thumb_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $img_dir . $img_name)) {
                $thumbnail_url = '/uploads/recordings/' . $img_name;
            }
        }
    }

    if (empty($title) || empty($video_url)) {
        json_response(['success' => false, 'message' => 'Title and Video URL cannot be empty.'], 400);
    }

    $stmt = $conn->prepare("
        UPDATE praise_program_recordings
        SET program_id = ?, title = ?, description = ?, thumbnail_url = ?, video_url = ?, duration_seconds = ?, program_date = ?, category = ?, status = ?
        WHERE id = ?
    ");
    $stmt->bind_param("issssisssi", $program_id, $title, $description, $thumbnail_url, $video_url, $duration_seconds, $program_date, $category, $status, $rec_id);
    if ($stmt->execute()) {
        $stmt->close();
        json_response(['success' => true, 'message' => 'Recording updated successfully.']);
    } else {
        json_response(['success' => false, 'message' => 'Failed to update recording.'], 500);
    }
}

// ── Admin: DELETE /admin/recordings/:id ───────────────────────────────────────
if ($method === 'DELETE' && preg_match('#^/admin/recordings/(\d+)$#', $path, $matches)) {
    require_admin();
    require_csrf();
    $rec_id = (int)$matches[1];

    $stmt = $conn->prepare("DELETE FROM praise_program_recordings WHERE id = ?");
    $stmt->bind_param("i", $rec_id);
    $stmt->execute();
    $stmt->close();

    json_response(['success' => true, 'message' => 'Recording deleted successfully.']);
}
