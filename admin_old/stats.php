<?php
if (session_status() == PHP_SESSION_NONE) {
    require_once __DIR__ . '/../includes/session.php';
    session_init();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

// Fetch all crusades with their view counts
$crusades_data = [];
$stmt_crusades = $conn->prepare("
    SELECT
        c.id,
        c.title,
        c.crusade_code,
        u.username AS creator_username,
        COUNT(ps.id) AS total_views
    FROM praise_crusades c
    LEFT JOIN praise_users u ON c.user_id = u.id
    LEFT JOIN praise_crusade_stats ps ON c.id = ps.crusade_id
    GROUP BY c.id
    ORDER BY total_views DESC, c.created_at DESC
");
$stmt_crusades->execute();
$result_crusades = $stmt_crusades->get_result();
while ($row = $result_crusades->fetch_assoc()) {
    $crusades_data[] = $row;
}
$stmt_crusades->close();


// Fetch all user-shared streams with details and view counts
$shared_streams_data = [];
$stmt_shared = $conn->prepare("
    SELECT
        pss.id,
        pss.stream_key,
        pss.created_at,
        u.username AS sharer_username,
        c.title AS crusade_title,
        COUNT(ps.id) AS total_views
    FROM praise_user_shared_streams pss
    LEFT JOIN praise_users u ON pss.user_id = u.id
    LEFT JOIN praise_crusades c ON pss.crusade_id = c.id
    LEFT JOIN praise_crusade_stats ps ON pss.crusade_id = ps.crusade_id
    GROUP BY pss.id
    ORDER BY total_views DESC, pss.created_at DESC
");
$stmt_shared->execute();
$result_shared = $stmt_shared->get_result();
while ($row = $result_shared->fetch_assoc()) {
    $shared_streams_data[] = $row;
}
$stmt_shared->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Views & Shares</title>
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
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title text-center">Crusade View Statistics</h2>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Code</th>
                                    <th>Creator</th>
                                    <th>Total Views</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($crusades_data)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No crusades found.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($crusades_data as $crusade): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($crusade['title']); ?></td>
                                        <td><?php echo htmlspecialchars($crusade['crusade_code']); ?></td>
                                        <td><?php echo htmlspecialchars($crusade['creator_username'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($crusade['total_views']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center">User Shared Stream Statistics</h2>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Shared Key</th>
                                    <th>Shared By</th>
                                    <th>Original Crusade</th>
                                    <th>Date Shared</th>
                                    <th>Total Views</th>
                                    <th>Link</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($shared_streams_data)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No shared streams found.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($shared_streams_data as $shared_stream): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($shared_stream['stream_key']); ?></td>
                                        <td><?php echo htmlspecialchars($shared_stream['sharer_username'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($shared_stream['crusade_title'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($shared_stream['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($shared_stream['total_views']); ?></td>
                                        <td><a href="../watch.php?stream_key=<?php echo htmlspecialchars($shared_stream['stream_key']); ?>" target="_blank">View</a></td>
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
