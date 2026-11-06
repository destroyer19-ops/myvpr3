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

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($id <= 0) {
    die("Invalid stream ID.");
}

$title = $description = $stream_url = $thumbnail_url = "";
$title_err = $description_err = $stream_url_err = $thumbnail_err = "";
$is_live = 0;

// Fetch current stream data for the form
$stmt_fetch = $conn->prepare("SELECT * FROM praise_live_tv WHERE id = ?");
$stmt_fetch->bind_param("i", $id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
if ($result->num_rows == 1) {
    $stream = $result->fetch_assoc();
    $title = $stream['title'];
    $description = $stream['description'];
    $stream_url = $stream['stream_url'];
    $thumbnail_url = $stream['thumbnail_url'];
    $is_live = $stream['is_live'];
} else {
    die("Stream not found.");
}
$stmt_fetch->close();


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $title_err = "Invalid session. Please refresh and try again.";
    }

    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $stream_url = trim($_POST["stream_url"]);
    $is_live = isset($_POST['is_live']) ? 1 : 0;
    $current_thumbnail = trim($_POST['current_thumbnail']);
    
    $new_thumbnail_path = $current_thumbnail; // Default to old path

    // Handle new thumbnail upload
    if (isset($_FILES["thumbnail_file"]) && $_FILES["thumbnail_file"]["error"] == 0) {
        $target_dir = __DIR__ . "/../uploads/thumbnails/";
        $filename = "stream_" . uniqid() . "_" . basename($_FILES["thumbnail_file"]["name"]);
        $target_file = $target_dir . $filename;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["thumbnail_file"]["tmp_name"]);
        if($check === false) { $thumbnail_err = "File is not an image."; }
        if ($_FILES["thumbnail_file"]["size"] > 5000000) { $thumbnail_err = "Sorry, your file is too large."; }
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) { $thumbnail_err = "Sorry, only JPG, JPEG, PNG & GIF files are allowed."; }

        if (empty($thumbnail_err)) {
            if (move_uploaded_file($_FILES["thumbnail_file"]["tmp_name"], $target_file)) {
                $new_thumbnail_path = "uploads/thumbnails/" . $filename;
                // Delete old thumbnail if it exists
                if (!empty($current_thumbnail) && file_exists(__DIR__ . '/../' . $current_thumbnail)) {
                    unlink(__DIR__ . '/../' . $current_thumbnail);
                }
            } else {
                $thumbnail_err = "Sorry, there was an error uploading your file.";
            }
        }
    }

    if (empty($title_err) && empty($description_err) && empty($stream_url_err) && empty($thumbnail_err)) {
        $sql = "UPDATE praise_live_tv SET title = ?, description = ?, stream_url = ?, thumbnail_url = ?, is_live = ? WHERE id = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssssii", $title, $description, $stream_url, $new_thumbnail_path, $is_live, $id);

            if ($stmt->execute()) {
                header("location: index.php");
                exit;
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Live Stream</title>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="admin-container container">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center">Edit Live Stream</h2>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?id=<?php echo $id; ?>" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <input type="hidden" name="current_thumbnail" value="<?php echo htmlspecialchars($thumbnail_url); ?>">

                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" name="title" class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($title); ?>">
                                <div class="invalid-feedback"><?php echo $title_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" class="form-control <?php echo (!empty($description_err)) ? 'is-invalid' : ''; ?>"><?php echo htmlspecialchars($description); ?></textarea>
                                <div class="invalid-feedback"><?php echo $description_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="stream_url" class="form-label">Stream URL</label>
                                <input type="text" name="stream_url" class="form-control <?php echo (!empty($stream_url_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($stream_url); ?>">
                                <div class="invalid-feedback"><?php echo $stream_url_err; ?></div>
                            </div>
                            
                            <?php if (!empty($thumbnail_url)): ?>
                            <div class="mb-3">
                                <label class="form-label">Current Thumbnail</label>
                                <img src="<?php echo '../' . htmlspecialchars($thumbnail_url); ?>" alt="Current Thumbnail" style="max-width: 200px; display: block; border-radius: 5px;">
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="thumbnail_file" class="form-label">Upload New Thumbnail</label>
                                <input type="file" name="thumbnail_file" class="form-control <?php echo (!empty($thumbnail_err)) ? 'is-invalid' : ''; ?>">
                                <div class="invalid-feedback"><?php echo $thumbnail_err; ?></div>
                                <div class="form-text">Optional. Overwrites the current thumbnail.</div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" name="is_live" class="form-check-input" id="is_live" <?php echo $is_live ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_live">Is Live</label>
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary">Update Stream</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
