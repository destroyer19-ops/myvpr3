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

// Fetch all videos
$videos = [];
$stmt = $conn->prepare("SELECT * FROM praise_videos ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $videos[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Video Library Management</title>
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
            <div class="col-md-10 offset-md-1">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center">Video Library Management</h2>
                        <p class="text-center text-muted">Use this section to manage all available videos in the library.</p>
                        <div class="text-center mb-4">
                            <a href="add_video.php" class="btn btn-primary">Add New Video</a>
                        </div>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Age Category</th>
                                    <th>Type Category</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($videos)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No videos have been added yet.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($videos as $video): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($video['title']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($video['video_url']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($video['age_category']); ?></td>
                                        <td><?php echo htmlspecialchars($video['type_category']); ?></td>
                                        <td class="text-end">
                                            <a href="edit_video.php?id=<?php echo $video['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit me-1"></i> Edit
                                            </a>
                                            <a href="delete_video.php?id=<?php echo $video['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this video?');">
                                                <i class="fas fa-trash me-1"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
