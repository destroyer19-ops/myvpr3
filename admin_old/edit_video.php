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
    die("Invalid video ID.");
}

$title = $video_url = $age_category = $type_category = $thumbnail_url = "";
$title_err = $video_url_err = $age_category_err = $type_category_err = $thumbnail_err = "";

$age_categories = ["Children", "Teens", "Youth", "Adults"];
$type_categories = ["Movies", "Animation", "Ministration", "Languages"];

// Fetch current video data for pre-filling the form
$stmt_fetch = $conn->prepare("SELECT * FROM praise_videos WHERE id = ?");
$stmt_fetch->bind_param("i", $id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
if ($result->num_rows == 1) {
    $video = $result->fetch_assoc();
    $title = $video['title'];
    $video_url = $video['video_url'];
    $thumbnail_url = $video['thumbnail_url']; // Fetch existing thumbnail URL
    $age_category = $video['age_category'];
    $type_category = $video['type_category'];
} else {
    die("Video not found.");
}
$stmt_fetch->close();


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $title_err = "Invalid session. Please refresh and try again.";
    }

    // Collect updated form data
    $title = trim($_POST["title"]);
    $video_url = trim($_POST["video_url"]);
    $age_category = trim($_POST["age_category"]);
    $type_category = trim($_POST["type_category"]);
    $current_thumbnail_path_from_db = trim($_POST['current_thumbnail_hidden'] ?? ''); // This holds the path from before potential new upload

    $new_thumbnail_path_for_db = $current_thumbnail_path_from_db; // Start with current thumbnail path


    // --- Handle new thumbnail upload ---
    if (isset($_FILES["thumbnail_file"]) && $_FILES["thumbnail_file"]["error"] == 0) {
        $target_dir = __DIR__ . "/../uploads/thumbnails/";
        // Generate a unique filename to prevent conflicts
        $filename = "video_" . uniqid() . "_" . basename($_FILES["thumbnail_file"]["name"]);
        $target_file = $target_dir . $filename;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Validate image properties
        $check = getimagesize($_FILES["thumbnail_file"]["tmp_name"]);
        if($check === false) { $thumbnail_err = "File is not an image."; }
        if ($_FILES["thumbnail_file"]["size"] > 5000000) { $thumbnail_err = "Sorry, your file is too large (max 5MB)."; }
        if(!in_array($imageFileType, ["jpg", "png", "jpeg", "gif"])) { $thumbnail_err = "Sorry, only JPG, JPEG, PNG & GIF files are allowed."; }

        if (empty($thumbnail_err)) {
            if (move_uploaded_file($_FILES["thumbnail_file"]["tmp_name"], $target_file)) {
                $new_thumbnail_path_for_db = "uploads/thumbnails/" . $filename; // Update path for DB

                // Delete old thumbnail file if a new one was uploaded and an old one existed
                if (!empty($current_thumbnail_path_from_db) && file_exists(__DIR__ . '/../' . $current_thumbnail_path_from_db)) {
                    unlink(__DIR__ . '/../' . $current_thumbnail_path_from_db);
                }
            } else {
                $thumbnail_err = "Sorry, there was an error uploading your file.";
            }
        }
    }
    // --- End thumbnail upload handling ---


    // Validate other fields
    if (empty($title_err) && empty(trim($title))) { $title_err = "Please enter a title."; }
    if (empty($video_url_err) && empty(trim($video_url))) { $video_url_err = "Please enter a video URL."; }
    if (empty($age_category_err) && empty(trim($age_category))) { $age_category_err = "Please select an age category."; }
    if (empty($type_category_err) && empty(trim($type_category))) { $type_category_err = "Please select a type category."; }


    if (empty($title_err) && empty($video_url_err) && empty($age_category_err) && empty($type_category_err) && empty($thumbnail_err)) {
        $sql = "UPDATE praise_videos SET title = ?, video_url = ?, thumbnail_url = ?, age_category = ?, type_category = ? WHERE id = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssssi", $title, $video_url, $new_thumbnail_path_for_db, $age_category, $type_category, $id);

            if ($stmt->execute()) {
                $_SESSION['admin_message'] = "Video updated successfully!";
                $_SESSION['admin_message_type'] = "success";
                header("location: manage_videos.php");
                exit;
            } else {
                $_SESSION['admin_message'] = "Error updating video: " . $conn->error;
                $_SESSION['admin_message_type'] = "danger";
            }
        } else {
            $_SESSION['admin_message'] = "Error preparing statement: " . $conn->error;
            $_SESSION['admin_message_type'] = "danger";
        }
        // Redirect on error to show message
        header("location: edit_video.php?id=" . $id);
        exit;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Video</title>
    <link rel="icon" href="../logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #1de9b6;
            --dark-color: #263238;
            --light-color: #f4f7f6;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-color);
        }

        .navbar {
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .admin-container {
            padding: 80px 0;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="admin-container container">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center">Edit Video</h2>
                        <?php
                        if (isset($_SESSION['admin_message'])) {
                            echo '<div class="alert alert-' . $_SESSION['admin_message_type'] . ' text-center">' . $_SESSION['admin_message'] . '</div>';
                            unset($_SESSION['admin_message']);
                            unset($_SESSION['admin_message_type']);
                        }
                        ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?id=<?php echo $id; ?>" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <input type="hidden" name="current_thumbnail_hidden" value="<?php echo htmlspecialchars($thumbnail_url); ?>">

                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" name="title" class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($title); ?>">
                                <div class="invalid-feedback"><?php echo $title_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="video_url" class="form-label">Video URL</label>
                                <input type="text" name="video_url" class="form-control <?php echo (!empty($video_url_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($video_url); ?>">
                                <div class="invalid-feedback"><?php echo $video_url_err; ?></div>
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
                                <div class="form-text">Optional. Uploads a new image and replaces the current one.</div>
                            </div>

                            <div class="mb-3">
                                <label for="age_category" class="form-label">Age Category</label>
                                <select name="age_category" class="form-select <?php echo (!empty($age_category_err)) ? 'is-invalid' : ''; ?>">
                                    <option value="">Select Age Category</option>
                                    <?php foreach ($age_categories as $category): ?>
                                        <option value="<?php echo $category; ?>" <?php echo ($age_category == $category) ? 'selected' : ''; ?>><?php echo $category; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"><?php echo $age_category_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="type_category" class="form-label">Type Category</label>
                                <select name="type_category" class="form-select <?php echo (!empty($type_category_err)) ? 'is-invalid' : ''; ?>">
                                    <option value="">Select Type Category</option>
                                    <?php foreach ($type_categories as $category): ?>
                                        <option value="<?php echo $category; ?>" <?php echo ($type_category == $category) ? 'selected' : ''; ?>><?php echo $category; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"><?php echo $type_category_err; ?></div>
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary">Update Video</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

