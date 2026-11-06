<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Invalid session. Please refresh and try again.";
        header('Location: subscribe.php');
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $subscription_type = $_POST['subscription_type'];
    $full_name = !empty($_POST['full_name']) ? trim($_POST['full_name']) : null;
    $kc_handle = !empty($_POST['kc_handle']) ? trim($_POST['kc_handle']) : null;
    $amount = $_POST['amount'];

    // Handle file upload
    $target_dir = "uploads/proofs/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $target_file = $target_dir . basename($_FILES["proof"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if file is provided
    if (empty($_FILES["proof"]["tmp_name"])) {
        $_SESSION['error_message'] = "Please upload a proof of transaction file.";
        $uploadOk = 0;
    } else {
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Allow certain file formats (JPG, JPEG, PNG, GIF, PDF)
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
        && $imageFileType != "gif" && $imageFileType != "pdf" ) {
            $_SESSION['error_message'] = "Sorry, only JPG, JPEG, PNG, GIF & PDF files are allowed for proof of transaction.";
            $uploadOk = 0;
        }

        // Validate MIME type
        if ($uploadOk == 1) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES["proof"]["tmp_name"]);
            $allowed_mimes = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'application/pdf',
            ];
            if (!in_array($mime, $allowed_mimes, true)) {
                $_SESSION['error_message'] = "Invalid file type. Please upload an image or PDF.";
                $uploadOk = 0;
            }
        }

        // Check file size (still 5MB limit)
        if ($_FILES["proof"]["size"] > 5000000) { // 5MB limit
            $_SESSION['error_message'] = "Sorry, your file is too large. Maximum file size is 5MB.";
            $uploadOk = 0;
        }
    }

    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        header('Location: subscribe.php');
        exit;
    // if everything is ok, try to upload file
    } else {
        $new_filename = $target_dir . uniqid('proof_', true) . '.' . $imageFileType;
        if (move_uploaded_file($_FILES["proof"]["tmp_name"], $new_filename)) {
            // Sanitize file path before storing in database
            $sanitized_path = filter_var($new_filename, FILTER_SANITIZE_STRING);

            $stmt = $conn->prepare("INSERT INTO transactions (user_id, full_name, kc_handle, amount, proof_of_transaction, subscription_type, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("isssss", $user_id, $full_name, $kc_handle, $amount, $sanitized_path, $subscription_type);
            
            if ($stmt->execute()) {
                header("Location: subscription_pending.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Database error: " . $stmt->error;
                header('Location: subscribe.php');
                exit;
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Sorry, there was an error uploading your file. Please try again.";
            header('Location: subscribe.php');
            exit;
        }
    }
} else {
    header('Location: subscribe.php');
    exit;
}

$conn->close();
?>
