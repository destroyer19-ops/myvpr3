<?php
ini_set("display_errors", 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once __DIR__ . '/../includes/session.php';
    session_init();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$title = $description = $stream_url = $thumbnail_url = "";
$title_err = $description_err = $stream_url_err = $thumbnail_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $title_err = "Invalid session. Please refresh and try again.";
    }
    if (empty($title_err) && empty(trim($_POST["title"]))) {
        $title_err = "Please enter a title.";
    } else {
        $title = trim($_POST["title"]);
    }

    if (empty($title_err) && empty(trim($_POST["description"]))) {
        $description_err = "Please enter a description.";
    } else {
        $description = trim($_POST["description"]);
    }

    if (empty($title_err) && empty(trim($_POST["stream_url"]))) {
        $stream_url_err = "Please enter a stream URL.";
    } else {
        $stream_url = trim($_POST["stream_url"]);
    }

    // Handle thumbnail upload
    if (isset($_FILES["thumbnail_file"]) && $_FILES["thumbnail_file"]["error"] == 0) {
        $target_dir = __DIR__ . "/../uploads/thumbnails/";
        $filename = "stream_" . uniqid() . "_" . basename($_FILES["thumbnail_file"]["name"]);
        $target_file = $target_dir . $filename;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if image file is a actual image or fake image
        $check = getimagesize($_FILES["thumbnail_file"]["tmp_name"]);
        if($check === false) {
            $thumbnail_err = "File is not an image.";
        }
        
        // Check file size (e.g., 5MB limit)
        if ($_FILES["thumbnail_file"]["size"] > 5000000) {
            $thumbnail_err = "Sorry, your file is too large.";
        }

        // Allow certain file formats
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
            $thumbnail_err = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        }

        if (empty($thumbnail_err)) {
            if (move_uploaded_file($_FILES["thumbnail_file"]["tmp_name"], $target_file)) {
                $thumbnail_url = "uploads/thumbnails/" . $filename;
            } else {
                $thumbnail_err = "Sorry, there was an error uploading your file.";
            }
        }
    }

    $is_live = isset($_POST['is_live']) ? 1 : 0;

    if (empty($title_err) && empty($description_err) && empty($stream_url_err) && empty($thumbnail_err)) {
        $sql = "INSERT INTO praise_live_tv (title, description, stream_url, thumbnail_url, is_live, created_at) VALUES (?, ?, ?, ?, ?, NOW())";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssssi", $title, $description, $stream_url, $thumbnail_url, $is_live);

            if ($stmt->execute()) {
                header("location: index.php");
                exit;
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Live Stream</title>
    <link rel="icon" href="../logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="admin-container container">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center">Add New Live Stream</h2>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" name="title" class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $title; ?>">
                                <div class="invalid-feedback"><?php echo $title_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" class="form-control <?php echo (!empty($description_err)) ? 'is-invalid' : ''; ?>"><?php echo $description; ?></textarea>
                                <div class="invalid-feedback"><?php echo $description_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="stream_url" class="form-label">Stream URL</label>
                                <input type="text" name="stream_url" class="form-control <?php echo (!empty($stream_url_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $stream_url; ?>">
                                <div class="invalid-feedback"><?php echo $stream_url_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="thumbnail_file" class="form-label">Thumbnail Image</label>
                                <input type="file" name="thumbnail_file" class="form-control <?php echo (!empty($thumbnail_err)) ? 'is-invalid' : ''; ?>">
                                <div class="invalid-feedback"><?php echo $thumbnail_err; ?></div>
                                <div class="form-text">Optional. Upload an image (JPG, PNG, GIF). Max 5MB.</div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="is_live" class="form-check-input" id="is_live">
                                <label class="form-check-label" for="is_live">Go Live Now</label>
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary">Add Stream</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
