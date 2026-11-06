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

// Fetch all live TV streams
$streams = [];
$stmt = $conn->prepare("SELECT * FROM praise_live_tv ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $streams[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Manage Live TV Streams</title>
    <link rel="icon" href="../logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="admin-container container">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center">Live TV Stream Management</h2>
                        <p class="text-center text-muted">Use this section to manage all available video streams. To make a stream live on the main page, simply edit a stream and set its status to "Live".</p>
                        <div class="text-center mb-4">
                            <a href="add_stream.php" class="btn btn-primary">Add New Stream</a>
                        </div>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Live Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($streams)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No streams have been added yet.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($streams as $stream): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($stream['title']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($stream['stream_url']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($stream['is_live']): ?>
                                                <span class="badge bg-success">Live</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Offline</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="edit_stream.php?id=<?php echo $stream['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit me-1"></i> Edit
                                            </a>
                                            <a href="delete_stream.php?id=<?php echo $stream['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this stream?');">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
