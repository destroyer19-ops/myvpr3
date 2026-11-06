<?php
// testimonies.php — Public and admin testimony endpoints
// ─────────────────────────────────────────────────────────────────────────────

// ── GET /testimonies  (public — only published) ───────────────────────────────
if ($method === 'GET' && $path === '/testimonies') {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 12;
    $offset = ($page - 1) * $limit;

    $stmt = $conn->prepare("
        SELECT id, full_name, country, testimony_text, created_at
        FROM praise_testimonies
        WHERE status = 'published'
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    if (!$stmt) json_response(['success' => false, 'message' => 'DB error'], 500);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Total count for pagination
    $count_result = $conn->query("SELECT COUNT(*) AS total FROM praise_testimonies WHERE status = 'published'");
    $total = $count_result ? (int)$count_result->fetch_assoc()['total'] : 0;

    json_response([
        'success' => true,
        'data'    => $rows,
        'total'   => $total,
        'page'    => $page,
        'pages'   => ceil($total / $limit),
    ]);
}

// ── POST /testimonies  (public — submit a new testimony) ─────────────────────
if ($method === 'POST' && $path === '/testimonies') {
    $data = get_request_data();
    $name = trim($data['full_name']      ?? '');
    $email = trim($data['email']         ?? '');
    $country = trim($data['country']     ?? '');
    $text = trim($data['testimony_text'] ?? '');

    if (empty($name) || empty($text)) {
        json_response(['success' => false, 'message' => 'Name and testimony are required.'], 400);
    }
    if (strlen($text) < 20) {
        json_response(['success' => false, 'message' => 'Please write at least a few sentences for your testimony.'], 400);
    }

    $stmt = $conn->prepare("
        INSERT INTO praise_testimonies (full_name, email, country, testimony_text, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    if (!$stmt) json_response(['success' => false, 'message' => 'DB error'], 500);
    $stmt->bind_param("ssss", $name, $email, $country, $text);

    if ($stmt->execute()) {
        json_response(['success' => true, 'message' => 'Thank you! Your testimony has been received and will be reviewed before being published.']);
    } else {
        json_response(['success' => false, 'message' => 'Failed to submit testimony. Please try again.'], 500);
    }
}

// ── GET /admin/testimonies  (admin only) ──────────────────────────────────────
if ($method === 'GET' && $path === '/admin/testimonies') {
    require_auth();
    require_admin();

    $status_filter = $_GET['status'] ?? null;
    $allowed_statuses = ['pending', 'approved', 'published', 'rejected'];

    if ($status_filter && in_array($status_filter, $allowed_statuses)) {
        $stmt = $conn->prepare("
            SELECT id, full_name, email, country, testimony_text, status, created_at, reviewed_at
            FROM praise_testimonies WHERE status = ? ORDER BY created_at DESC
        ");
        $stmt->bind_param("s", $status_filter);
    } else {
        $stmt = $conn->prepare("
            SELECT id, full_name, email, country, testimony_text, status, created_at, reviewed_at
            FROM praise_testimonies ORDER BY created_at DESC
        ");
    }
    if (!$stmt) json_response(['success' => false, 'message' => 'DB error'], 500);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    json_response(['success' => true, 'data' => $rows]);
}

// ── PATCH /admin/testimonies/:id  (admin — update status) ────────────────────
if ($method === 'PATCH' && preg_match('#^/admin/testimonies/([0-9]+)$#', $path, $matches)) {
    require_auth();
    require_admin();

    $id     = (int) $matches[1];
    $data   = get_request_data();
    $status = $data['status'] ?? null;

    if (!in_array($status, ['pending', 'approved', 'published', 'rejected'])) {
        json_response(['success' => false, 'message' => 'Invalid status. Must be: pending, approved, published, or rejected.'], 400);
    }

    $stmt = $conn->prepare("UPDATE praise_testimonies SET status = ?, reviewed_at = NOW() WHERE id = ?");
    if (!$stmt) json_response(['success' => false, 'message' => 'DB error'], 500);
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        json_response(['success' => true]);
    } else {
        json_response(['success' => false, 'message' => 'Testimony not found.'], 404);
    }
}

// ── DELETE /admin/testimonies/:id  (admin — permanently delete) ───────────────
if ($method === 'DELETE' && preg_match('#^/admin/testimonies/([0-9]+)$#', $path, $matches)) {
    require_auth();
    require_admin();

    $id = (int) $matches[1];
    $stmt = $conn->prepare("DELETE FROM praise_testimonies WHERE id = ?");
    if (!$stmt) json_response(['success' => false, 'message' => 'DB error'], 500);
    $stmt->bind_param("i", $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        json_response(['success' => true]);
    } else {
        json_response(['success' => false, 'message' => 'Testimony not found.'], 404);
    }
}
