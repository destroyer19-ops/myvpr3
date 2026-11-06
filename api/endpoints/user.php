<?php
// user.php - User endpoints

if ($method === 'GET' && $path === '/user/profile') {
    require_auth();

    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'User';

    // Check subscription
    $is_subscribed = false;
    $stmt_sub = $conn->prepare("SELECT end_date, status FROM subscriptions WHERE user_id = ? AND (status IS NULL OR status != 'cancelled') ORDER BY end_date DESC LIMIT 1");
    if ($stmt_sub) {
        $stmt_sub->bind_param("i", $user_id);
        $stmt_sub->execute();
        $result_sub = $stmt_sub->get_result();
        $subscription = $result_sub->fetch_assoc();
        $stmt_sub->close();

        if ($subscription && ($subscription['status'] === null || $subscription['status'] !== 'cancelled')) {
            $end_date = new DateTime($subscription['end_date']);
            $now = new DateTime();
            if ($end_date > $now) {
                $is_subscribed = true;
            }
        }
    }

    // Check if user is admin — look in praise_admins table (username match) OR is_admin column on praise_users
    $is_admin = false;
    // First check praise_users for is_admin column (auto-create if missing)
    $col_check = $conn->query("SHOW COLUMNS FROM praise_users LIKE 'is_admin'");
    if ($col_check && $col_check->num_rows === 0) {
        $conn->query("ALTER TABLE praise_users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0");
    }
    $stmt_admin = $conn->prepare("SELECT is_admin FROM praise_users WHERE id = ?");
    if ($stmt_admin) {
        $stmt_admin->bind_param("i", $user_id);
        $stmt_admin->execute();
        $result_admin = $stmt_admin->get_result();
        if ($row_admin = $result_admin->fetch_assoc()) {
            $is_admin = (bool)($row_admin['is_admin'] ?? false);
        }
        $stmt_admin->close();
    }
    // Fallback: check praise_admins table by username (old system bridge)
    if (!$is_admin) {
        $stmt_pa = $conn->prepare("SELECT id FROM praise_admins WHERE username = ? LIMIT 1");
        if ($stmt_pa) {
            $stmt_pa->bind_param("s", $username);
            $stmt_pa->execute();
            $stmt_pa->store_result();
            if ($stmt_pa->num_rows > 0) {
                $is_admin = true;
                // Sync the flag to praise_users so future checks are faster
                $conn->query("UPDATE praise_users SET is_admin = 1 WHERE id = {$user_id}");
            }
            $stmt_pa->close();
        }
    }
    // Keep admin session flag in sync for admin API endpoints
    if ($is_admin) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user_id']   = $user_id;
    }

    // Total crusade visits
    $total_views = 0;
    $stmt_total = $conn->prepare("
        SELECT COUNT(*) AS total_count
        FROM praise_crusade_stats s
        JOIN praise_crusades c ON c.id = s.crusade_id
        WHERE c.user_id = ?
    ");
    if ($stmt_total) {
        $stmt_total->bind_param("i", $user_id);
        $stmt_total->execute();
        $result_total = $stmt_total->get_result();
        if ($row_total = $result_total->fetch_assoc()) {
            $total_views = (int)$row_total['total_count'];
        }
        $stmt_total->close();
    }

    $current_crusade_code = $_SESSION['current_crusade_code'] ?? null;
    $requires_username_update = !preg_match('/^[a-zA-Z0-9_]+$/', $username);

    json_response([
        'success' => true,
        'data' => [
            'id'                       => $user_id,
            'name'                     => $username,
            'username'                 => $username,
            'is_admin'                 => $is_admin,
            'isSubscribed'             => $is_subscribed,
            'subscription_status'      => $is_subscribed ? 'active' : 'inactive',
            'subscription_end_date'    => isset($subscription['end_date']) ? $subscription['end_date'] : null,
            'requires_username_update' => $requires_username_update,
            'totalViews'               => $total_views,
            'currentCrusadeCode'       => $current_crusade_code,
        ]
    ]);
}

if ($method === 'POST' && $path === '/user/update-username') {
    require_auth();

    $data = get_request_data();
    $new_username = trim($data['username'] ?? '');

    if ($new_username === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
        json_response(['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores.'], 422);
    }

    $user_id = $_SESSION['user_id'];

    // Check if taken
    $stmt = $conn->prepare("SELECT id FROM praise_users WHERE username = ? AND id != ?");
    if ($stmt) {
        $stmt->bind_param("si", $new_username, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            json_response(['success' => false, 'message' => 'Username is already taken.'], 409);
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("UPDATE praise_users SET username = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $new_username, $user_id);
        if ($stmt->execute()) {
            $_SESSION['username'] = $new_username;
            json_response(['success' => true, 'message' => 'Username updated successfully.']);
        }
        $stmt->close();
    }
    json_response(['success' => false, 'message' => 'Failed to update username.'], 500);
}
