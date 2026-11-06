<?php
// crusades.php — Crusades endpoints
// ─────────────────────────────────────────────────────────────────────────────

// ── Helper: resolve canonical event status ────────────────────────────────────
function resolve_crusade_status(array $c): string {
    // If the DB status is already definitive, trust it first
    $db_status = $c['status'] ?? null;
    if (in_array($db_status, ['ended', 'cancelled', 'draft'])) {
        return $db_status;
    }

    $now = new DateTime('now', new DateTimeZone('UTC'));

    // If a start_time is set and it's in the future → scheduled
    if (!empty($c['startTime'])) {
        try {
            $start = new DateTime($c['startTime'], new DateTimeZone('UTC'));
            if ($now < $start) {
                return 'scheduled';
            }
        } catch (Exception $e) {}
    }

    // is_active = 0 with a past/null start = ended
    if ((int)($c['is_active'] ?? 0) === 0) {
        return 'ended';
    }

    return 'live';
}

// ── GET /crusades  (authenticated — list own crusades) ────────────────────────
if ($method === 'GET' && $path === '/crusades') {
    require_auth();
    $user_id = $_SESSION['user_id'];

    $rows = [];
    $stmt = $conn->prepare("
        SELECT
            c.id, c.title, c.crusade_code AS code,
            c.start_time AS startTime, c.end_time AS endTime,
            c.timezone, c.status, c.is_active,
            c.thumbnail_url,
            c.created_at,
            COUNT(DISTINCT s.id)                                               AS total_views,
            COUNT(DISTINCT s.visitor_session_id)                               AS unique_visitors,
            SUM(CASE WHEN s.visitor_name IS NOT NULL THEN 1 ELSE 0 END)        AS identified
        FROM praise_crusades c
        LEFT JOIN praise_crusade_stats s ON c.id = s.crusade_id
        WHERE c.user_id = ?
        GROUP BY c.id
        ORDER BY COALESCE(c.start_time, c.created_at) DESC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['status'] = resolve_crusade_status($row);
            $rows[] = $row;
        }
        $stmt->close();
    }

    // bucket into sections for backwards compat
    $scheduled = array_values(array_filter($rows, fn($r) => $r['status'] === 'scheduled'));
    $active     = array_values(array_filter($rows, fn($r) => $r['status'] === 'live'));
    $past       = array_values(array_filter($rows, fn($r) => in_array($r['status'], ['ended', 'cancelled', 'draft'])));

    json_response(['success' => true, 'data' => compact('scheduled', 'active', 'past')]);
}

// ── GET /crusades/:code  (public) ─────────────────────────────────────────────
if ($method === 'GET' && preg_match('#^/crusades/([A-Za-z0-9_-]+)$#', $path, $m)) {
    $code = $m[1];
    $stmt = $conn->prepare("
        SELECT id, user_id, title, description, video_url,
               is_active, status, crusade_code AS code,
               start_time AS startTime, end_time AS endTime,
               timezone, thumbnail_url,
               created_at
        FROM praise_crusades WHERE crusade_code = ?
    ");
    if (!$stmt) json_response(['success' => false, 'message' => 'DB error'], 500);
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $crusade = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$crusade) json_response(['success' => false, 'message' => 'Event not found.'], 404);

    // Resolve canonical status (server-side authority)
    $status = resolve_crusade_status($crusade);
    $crusade['status'] = $status;

    // ── Security: never expose video_url before the event starts ─────────────
    if ($status === 'scheduled' || $status === 'draft' || $status === 'cancelled') {
        $crusade['video_url']          = null;
        $crusade['resolved_video_url'] = null;
    } else {
        // Resolve special VPR video source tokens
        $url = $crusade['video_url'] ?? '';
        if (strpos($url, 'LIVE_STREAM_OFFICIAL_') === 0) {
            $tv_id = (int) str_replace('LIVE_STREAM_OFFICIAL_', '', $url);
            $s2 = $conn->prepare("SELECT stream_url FROM praise_live_tv WHERE id = ?");
            if ($s2) {
                $s2->bind_param("i", $tv_id);
                $s2->execute();
                if ($row2 = $s2->get_result()->fetch_assoc()) {
                    $crusade['resolved_video_url'] = $row2['stream_url'];
                }
                $s2->close();
            }
        } elseif (strpos($url, 'VPR_VOD_') === 0) {
            $vod_id = (int) str_replace('VPR_VOD_', '', $url);
            $s3 = $conn->prepare("SELECT video_url FROM praise_videos WHERE id = ?");
            if ($s3) {
                $s3->bind_param("i", $vod_id);
                $s3->execute();
                if ($row3 = $s3->get_result()->fetch_assoc()) {
                    $crusade['resolved_video_url'] = $row3['video_url'];
                }
                $s3->close();
            }
        } else {
            $crusade['resolved_video_url'] = $url;
        }
    }

    // Fetch host display name (optional, non-sensitive)
    $stmt_host = $conn->prepare("SELECT username FROM praise_users WHERE id = ?");
    if ($stmt_host) {
        $stmt_host->bind_param("i", $crusade['user_id']);
        $stmt_host->execute();
        if ($host_row = $stmt_host->get_result()->fetch_assoc()) {
            $crusade['host_username'] = $host_row['username'];
        }
        $stmt_host->close();
    }

    json_response(['success' => true, 'data' => $crusade]);
}

// ── POST /crusades/:code/track  (public — view tracking) ─────────────────────
if ($method === 'POST' && preg_match('#^/crusades/([A-Za-z0-9_-]+)/track$#', $path, $m)) {
    $code = $m[1];

    $stmt = $conn->prepare("SELECT id, status, start_time FROM praise_crusades WHERE crusade_code = ?");
    if (!$stmt) json_response(['success' => false, 'message' => 'DB error'], 500);
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $crusade = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$crusade) json_response(['success' => false, 'message' => 'Event not found.'], 404);

    // Don't track views for draft / cancelled events
    $resolved = resolve_crusade_status([
        'status'    => $crusade['status'],
        'is_active' => 1,
        'startTime' => $crusade['start_time'],
    ]);
    if (in_array($resolved, ['draft', 'cancelled'])) {
        json_response(['success' => true, 'tracked' => false]);
    }

    $crusade_id        = (int) $crusade['id'];
    $data              = get_request_data();
    $visitor_session_id = trim($data['visitor_session_id'] ?? '');
    $stayed            = !empty($data['stayed_for_a_minute']);

    $ip = get_client_ip();
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 512);

    // Deduplicate: prefer session ID, fallback to IP within 4 hours
    if ($visitor_session_id) {
        $stmt_chk = $conn->prepare("SELECT id FROM praise_crusade_stats WHERE crusade_id = ? AND visitor_session_id = ?");
        $stmt_chk->bind_param("is", $crusade_id, $visitor_session_id);
    } else {
        $stmt_chk = $conn->prepare("SELECT id FROM praise_crusade_stats WHERE crusade_id = ? AND ip_address = ? AND created_at > NOW() - INTERVAL 4 HOUR");
        $stmt_chk->bind_param("is", $crusade_id, $ip);
    }
    $stmt_chk->execute();
    $existing = $stmt_chk->get_result()->fetch_assoc();
    $stmt_chk->close();

    if ($existing) {
        // Update last_seen and stayed_for_a_minute if reported
        $upd = $conn->prepare("UPDATE praise_crusade_stats SET last_seen_at = NOW()" . ($stayed ? ", stayed_for_a_minute = 1" : "") . " WHERE id = ?");
        $upd->bind_param("i", $existing['id']);
        $upd->execute();
        $upd->close();
        json_response(['success' => true, 'new_visit' => false]);
    }

    // New visitor — resolve geo
    $country = null;
    $city    = null;
    if ($ip && !in_array($ip, ['127.0.0.1', '::1', ''])) {
        $geo = @json_decode(@file_get_contents("http://ip-api.com/json/{$ip}?fields=country,city,status"), true);
        if ($geo && ($geo['status'] ?? '') === 'success') {
            $country = $geo['country'] ?? null;
            $city    = $geo['city']    ?? null;
        }
    }

    $ins = $conn->prepare("
        INSERT INTO praise_crusade_stats
            (crusade_id, ip_address, user_agent, visitor_session_id, country, city, stayed_for_a_minute, last_seen_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    if ($ins) {
        $stayed_int = $stayed ? 1 : 0;
        $ins->bind_param("isssssi", $crusade_id, $ip, $ua, $visitor_session_id, $country, $city, $stayed_int);
        $ins->execute();
        $ins->close();
    }

    json_response(['success' => true, 'new_visit' => true]);
}

// ── POST /crusades  (auth — create new event) ─────────────────────────────────
if ($method === 'POST' && $path === '/crusades') {
    require_auth();
    $user_id = $_SESSION['user_id'];

    // Check active subscription
    $sub = null;
    $stmt_sub = $conn->prepare("SELECT end_date FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
    if ($stmt_sub) {
        $stmt_sub->bind_param("i", $user_id);
        $stmt_sub->execute();
        $sub = $stmt_sub->get_result()->fetch_assoc();
        $stmt_sub->close();
    }
    $is_subscribed = $sub && new DateTime($sub['end_date']) > new DateTime();

    if (!$is_subscribed) {
        json_response(['success' => false, 'message' => 'An active subscription is required to host an event.', 'needs_subscription' => true], 403);
    }

    $data        = get_request_data();
    $title       = trim($data['title']       ?? '');
    $description = trim($data['description'] ?? '');
    $video_url   = trim($data['video_url']   ?? '');
    $is_draft    = !empty($data['draft']);
    $schedule    = !empty($data['schedule_crusade']) && !$is_draft;
    $start_time  = ($schedule && !empty($data['start_time']))  ? $data['start_time']  : null;
    $end_time    = ($schedule && !empty($data['end_time']))    ? $data['end_time']    : null;
    $timezone    = !empty($data['timezone']) ? $data['timezone'] : 'UTC';

    if (empty($title) || empty($description) || empty($video_url)) {
        json_response(['success' => false, 'message' => 'Title, description, and video source are required.'], 400);
    }

    // Convert local start/end times to UTC if a timezone is provided
    if ($start_time && $timezone && $timezone !== 'UTC') {
        try {
            $dt = new DateTime($start_time, new DateTimeZone($timezone));
            $dt->setTimezone(new DateTimeZone('UTC'));
            $start_time = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {}
    }
    if ($end_time && $timezone && $timezone !== 'UTC') {
        try {
            $dt = new DateTime($end_time, new DateTimeZone($timezone));
            $dt->setTimezone(new DateTimeZone('UTC'));
            $end_time = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {}
    }

    if ($is_draft) {
        $explicit_status = 'draft';
        $is_active = 0;
    } elseif ($schedule) {
        $explicit_status = 'scheduled';
        $is_active = 0;
    } else {
        $explicit_status = 'live';
        $is_active = 1;
    }

    $thumbnail_url = null;
    if (isset($_FILES['ecard']) && $_FILES['ecard']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../uploads/crusades/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
        $ext = pathinfo($_FILES['ecard']['name'], PATHINFO_EXTENSION);
        $safe_filename = 'ecard_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['ecard']['tmp_name'], $upload_dir . $safe_filename)) {
            $thumbnail_url = '/uploads/crusades/' . $safe_filename;
        }
    }

    // Generate unique code
    $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

    $stmt = $conn->prepare("
        INSERT INTO praise_crusades
            (user_id, title, description, video_url, crusade_code, is_active, status, start_time, end_time, timezone, thumbnail_url, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) json_response(['success' => false, 'message' => 'DB prepare failed.'], 500);

    $stmt->bind_param("issssisssss",
        $user_id, $title, $description, $video_url,
        $code, $is_active, $explicit_status, $start_time, $end_time, $timezone, $thumbnail_url
    );

    if ($stmt->execute()) {
        json_response([
            'success'      => true,
            'crusade_code' => $code,
            'status'       => $explicit_status,
            'watch_url'    => rtrim(REACT_APP_URL, '/') . '/crusade/' . $code,
        ]);
    } else {
        json_response(['success' => false, 'message' => 'Failed to create event: ' . $stmt->error], 500);
    }
}

// ── PATCH /crusades/:code  (auth — edit event) ───────────────────────────────
if ($method === 'PATCH' && preg_match('#^/crusades/([A-Za-z0-9_-]+)$#', $path, $m)) {
    require_auth();
    $code    = $m[1];
    $user_id = $_SESSION['user_id'];

    // Verify ownership
    $stmt_own = $conn->prepare("SELECT id, status FROM praise_crusades WHERE crusade_code = ? AND user_id = ?");
    $stmt_own->bind_param("si", $code, $user_id);
    $stmt_own->execute();
    $own = $stmt_own->get_result()->fetch_assoc();
    $stmt_own->close();
    if (!$own) json_response(['success' => false, 'message' => 'Not found or not authorized.'], 404);

    $data        = get_request_data();
    $title       = trim($data['title']       ?? '');
    $description = trim($data['description'] ?? '');
    $video_url   = trim($data['video_url']   ?? '');
    $start_time  = $data['start_time'] ?? null;
    $end_time    = $data['end_time']   ?? null;
    $timezone    = $data['timezone']   ?? 'UTC';
    $new_status  = $data['status']     ?? null;

    // Allowed status transitions the host can manually trigger
    $allowed_transitions = ['live', 'ended', 'cancelled', 'scheduled', 'draft'];
    if ($new_status && !in_array($new_status, $allowed_transitions)) {
        json_response(['success' => false, 'message' => 'Invalid status.'], 400);
    }

    $set_parts = [];
    $types     = '';
    $vals      = [];

    if ($title)       { $set_parts[] = 'title = ?';       $types .= 's'; $vals[] = $title; }
    if ($description) { $set_parts[] = 'description = ?'; $types .= 's'; $vals[] = $description; }
    if ($video_url)   { $set_parts[] = 'video_url = ?';   $types .= 's'; $vals[] = $video_url; }
    if ($start_time !== null) {
        $set_parts[] = 'start_time = ?';
        $types .= 's';
        if ($start_time && $timezone && $timezone !== 'UTC') {
            try {
                $dt = new DateTime($start_time, new DateTimeZone($timezone));
                $dt->setTimezone(new DateTimeZone('UTC'));
                $start_time = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {}
        }
        $vals[] = $start_time ?: null;
    }
    if ($end_time !== null) {
        $set_parts[] = 'end_time = ?';
        $types .= 's';
        $vals[] = $end_time ?: null;
    }
    if ($timezone)  { $set_parts[] = 'timezone = ?'; $types .= 's'; $vals[] = $timezone; }
    if ($new_status) {
        $set_parts[] = 'status = ?';
        $types .= 's';
        $vals[] = $new_status;
        // Sync is_active
        $is_active = ($new_status === 'live') ? 1 : 0;
        $set_parts[] = 'is_active = ?';
        $types .= 'i';
        $vals[] = $is_active;
        if ($new_status === 'cancelled') {
            $set_parts[] = 'cancelled_at = NOW()';
        }
    }

    if (empty($set_parts)) json_response(['success' => false, 'message' => 'Nothing to update.'], 400);

    $types .= 'si';
    $vals[] = $code;
    $vals[] = $user_id;

    $sql  = "UPDATE praise_crusades SET " . implode(', ', $set_parts) . " WHERE crusade_code = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) json_response(['success' => false, 'message' => 'DB error.'], 500);
    $stmt->bind_param($types, ...$vals);

    if ($stmt->execute()) {
        json_response(['success' => true]);
    } else {
        json_response(['success' => false, 'message' => $stmt->error], 500);
    }
}

// ── POST /crusades/:code/end  (auth) ─────────────────────────────────────────
if ($method === 'POST' && preg_match('#^/crusades/([A-Za-z0-9_-]+)/end$#', $path, $m)) {
    require_auth();
    $code    = $m[1];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE praise_crusades SET is_active = 0, status = 'ended' WHERE crusade_code = ? AND user_id = ?");
    if (!$stmt) json_response(['success' => false, 'message' => 'DB error'], 500);
    $stmt->bind_param("si", $code, $user_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        json_response(['success' => true]);
    } else {
        json_response(['success' => false, 'message' => 'Not found or not authorized.'], 404);
    }
}

// ── DELETE /crusades/:code  (auth) ────────────────────────────────────────────
if ($method === 'DELETE' && preg_match('#^/crusades/([A-Za-z0-9_-]+)$#', $path, $m)) {
    require_auth();
    $code    = $m[1];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("DELETE FROM praise_crusades WHERE crusade_code = ? AND user_id = ?");
    if (!$stmt) json_response(['success' => false, 'message' => 'DB error'], 500);
    $stmt->bind_param("si", $code, $user_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        json_response(['success' => true]);
    } else {
        json_response(['success' => false, 'message' => 'Not found or not authorized.'], 404);
    }
}

// ── PATCH /crusades/:code/track/identify  (public — viewer self-identification) ──
if ($method === 'PATCH' && preg_match('#^/crusades/([A-Za-z0-9_-]+)/track/identify$#', $path, $m)) {
    $code = $m[1];
    $data = get_request_data();

    $visitor_session_id = trim($data['visitor_session_id'] ?? '');
    $name               = trim($data['name']               ?? '');
    $email              = trim($data['email']              ?? '');
    $country            = trim($data['country']            ?? '');

    if (!$visitor_session_id || !$name) {
        json_response(['success' => false, 'message' => 'Name and session ID are required.'], 400);
    }

    $stmt = $conn->prepare("SELECT id FROM praise_crusades WHERE crusade_code = ?");
    if (!$stmt) json_response(['success' => false], 500);
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $crusade = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$crusade) json_response(['success' => false, 'message' => 'Event not found.'], 404);

    $upd = $conn->prepare("
        UPDATE praise_crusade_stats
           SET visitor_name  = ?,
               visitor_email = ?,
               country       = COALESCE(NULLIF(?, ''), country)
         WHERE crusade_id = ? AND visitor_session_id = ?
    ");
    if (!$upd) json_response(['success' => false], 500);
    $upd->bind_param("sssis", $name, $email, $country, $crusade['id'], $visitor_session_id);
    $upd->execute();
    $upd->close();

    json_response(['success' => true]);
}

// ── GET /crusades/:code/analytics  (auth — owner only) ───────────────────────
if ($method === 'GET' && preg_match('#^/crusades/([A-Za-z0-9_-]+)/analytics$#', $path, $m)) {
    require_auth();
    $code    = $m[1];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT id, title, start_time, status FROM praise_crusades WHERE crusade_code = ? AND user_id = ?");
    if (!$stmt) json_response(['success' => false, 'message' => 'DB error'], 500);
    $stmt->bind_param("si", $code, $user_id);
    $stmt->execute();
    $crusade = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$crusade) json_response(['success' => false, 'message' => 'Not found or not authorized.'], 404);

    $c_id = (int) $crusade['id'];

    // ── Overview aggregates ───────────────────────────────────────────────────
    $agg = $conn->query("
        SELECT
            COUNT(id)                                                    AS total_views,
            COUNT(DISTINCT visitor_session_id)                           AS unique_visitors,
            SUM(CASE WHEN visitor_name IS NOT NULL THEN 1 ELSE 0 END)   AS identified_attendees,
            SUM(stayed_for_a_minute)                                     AS stayed_1m
        FROM praise_crusade_stats WHERE crusade_id = {$c_id}
    ");
    $overview = $agg ? $agg->fetch_assoc() : [];

    // ── Countries ────────────────────────────────────────────────────────────
    $countries = [];
    $geo = $conn->query("
        SELECT country, city, COUNT(*) AS count
        FROM praise_crusade_stats
        WHERE crusade_id = {$c_id} AND country IS NOT NULL
        GROUP BY country, city
        ORDER BY count DESC
        LIMIT 30
    ");
    if ($geo) {
        while ($row = $geo->fetch_assoc()) $countries[] = $row;
    }

    // ── Attendees (identified) ────────────────────────────────────────────────
    $attendees = [];
    $att = $conn->query("
        SELECT
            visitor_name  AS name,
            visitor_email AS email,
            country, city,
            created_at    AS first_seen,
            last_seen_at,
            stayed_for_a_minute
        FROM praise_crusade_stats
        WHERE crusade_id = {$c_id} AND visitor_name IS NOT NULL
        ORDER BY created_at DESC
        LIMIT 200
    ");
    if ($att) {
        while ($row = $att->fetch_assoc()) $attendees[] = $row;
    }

    json_response([
        'success' => true,
        'data' => [
            'title'     => $crusade['title'],
            'status'    => $crusade['status'],
            'startTime' => $crusade['start_time'],
            'overview'  => $overview,
            'countries' => $countries,
            'attendees' => $attendees,
        ]
    ]);
}
