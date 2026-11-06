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
        header('Location: gift_subscription.php');
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $gifted_user_id = isset($_POST['gifted_user_id']) ? (int)$_POST['gifted_user_id'] : 0;
    $subscription_type = $_POST['subscription_type'] ?? '';
    $full_name = !empty($_POST['full_name']) ? trim($_POST['full_name']) : null;
    $kc_handle = !empty($_POST['kc_handle']) ? trim($_POST['kc_handle']) : null;
    $amount = $_POST['amount'] ?? '';
    $gift_message = !empty($_POST['gift_message']) ? trim($_POST['gift_message']) : null;

    if ($gifted_user_id <= 0 || empty($subscription_type)) {
        $_SESSION['error_message'] = "Please select a recipient and a plan.";
        header('Location: gift_subscription.php');
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM praise_users WHERE id = ?");
    $stmt->bind_param("i", $gifted_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipient = $result->fetch_assoc();
    $stmt->close();

    if (!$recipient) {
        $_SESSION['error_message'] = "Selected recipient not found.";
        header('Location: gift_subscription.php');
        exit;
    }

    // Handle file upload
    $target_dir = "uploads/proofs/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $target_file = $target_dir . basename($_FILES["proof"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    if (empty($_FILES["proof"]["tmp_name"])) {
        $_SESSION['error_message'] = "Please upload a proof of transaction file.";
        $uploadOk = 0;
    } else {
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
        && $imageFileType != "gif" && $imageFileType != "pdf" ) {
            $_SESSION['error_message'] = "Sorry, only JPG, JPEG, PNG, GIF & PDF files are allowed for proof of transaction.";
            $uploadOk = 0;
        }

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

        if ($_FILES["proof"]["size"] > 5000000) {
            $_SESSION['error_message'] = "Sorry, your file is too large. Maximum file size is 5MB.";
            $uploadOk = 0;
        }
    }

    if ($uploadOk == 0) {
        header('Location: gift_subscription.php');
        exit;
    } else {
        $new_filename = $target_dir . uniqid('proof_', true) . '.' . $imageFileType;
        if (move_uploaded_file($_FILES["proof"]["tmp_name"], $new_filename)) {
            $sanitized_path = filter_var($new_filename, FILTER_SANITIZE_STRING);

            $stmt = $conn->prepare("INSERT INTO transactions (user_id, gifted_user_id, gift_message, full_name, kc_handle, amount, proof_of_transaction, subscription_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("iissssss", $user_id, $gifted_user_id, $gift_message, $full_name, $kc_handle, $amount, $sanitized_path, $subscription_type);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Gift submitted successfully. Pending admin approval.";
                header("Location: gift_subscription.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Database error: " . $stmt->error;
                header('Location: gift_subscription.php');
                exit;
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Sorry, there was an error uploading your file. Please try again.";
            header('Location: gift_subscription.php');
            exit;
        }
    }
} else {
    header('Location: gift_subscription.php');
    exit;
}

$conn->close();
?>
