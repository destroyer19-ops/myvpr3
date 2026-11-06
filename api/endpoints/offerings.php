<?php
// ── POST /offerings ─────────────────────────────────────────────────────────
if ($method === 'POST' && $path === '/offerings') {
    // Determine user_id if logged in
    $user_id = null;
    _ensureSession();
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }

    $name = trim($_POST['name'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    
    if (empty($name) || empty($amount)) {
        json_response(['success' => false, 'message' => 'Name and amount are required.'], 400);
    }
    
    if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'Proof of payment file is required.'], 400);
    }

    $file = $_FILES['proof'];
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($file['type'], $allowed_types)) {
        json_response(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF are allowed.'], 400);
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        json_response(['success' => false, 'message' => 'File size exceeds 5MB limit.'], 400);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('proof_') . '_' . time() . '.' . $ext;
    $upload_dir = __DIR__ . '/../../uploads/offerings/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $destination = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $proof_url = '/uploads/offerings/' . $filename;
        
        $stmt = $conn->prepare("INSERT INTO praise_offerings (user_id, name, amount, proof_url, status) VALUES (?, ?, ?, ?, 'pending')");
        if ($stmt) {
            $stmt->bind_param("isds", $user_id, $name, $amount, $proof_url);
            $stmt->execute();
            $stmt->close();
            json_response(['success' => true]);
        }
        json_response(['success' => false, 'message' => 'Database error.'], 500);
    } else {
        json_response(['success' => false, 'message' => 'Failed to save file.'], 500);
    }
}
