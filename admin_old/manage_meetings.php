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

// Fetch all meetings
$meetings = [];
$stmt = $conn->prepare("
    SELECT m.*, u.username 
    FROM praise_meetings m 
    JOIN praise_users u ON m.user_id = u.id 
    ORDER BY m.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $meetings[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Manage Meetings</title>
    <link rel="icon" href="../logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="admin-container container py-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                        <h4 class="mb-0">Manage Meetings</h4>
                        <a href="../create_meeting.php" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Create New Meeting
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Creator</th>
                                        <th>Platform</th>
                                        <th>Code</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($meetings)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No meetings found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($meetings as $meeting): ?>
                                            <?php $is_daily = !empty($meeting['daily_room_url']); ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($meeting['title']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($meeting['username']); ?></td>
                                                <td>
                                                    <?php if ($is_daily): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Daily.co</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">KingsConference</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><code><?php echo htmlspecialchars($meeting['meeting_code']); ?></code></td>
                                                <td><?php echo (new DateTime($meeting['created_at']))->format('M j, Y g:i A'); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="<?php echo $is_daily ? '../meeting-room-daily.php' : '../meeting-room.php'; ?>?code=<?php echo $meeting['meeting_code']; ?>" class="btn btn-sm btn-outline-secondary" title="View Room" target="_blank">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-primary copy-link" data-code="<?php echo $meeting['meeting_code']; ?>" title="Copy Join Link">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        document.querySelectorAll('.copy-link').forEach(btn => {
            btn.addEventListener('click', function() {
                const code = this.getAttribute('data-code');
                const url = `${window.location.origin}/join.php?code=${code}`;
                navigator.clipboard.writeText(url).then(() => {
                    Toastify({
                        text: "Join link copied to clipboard!",
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
                    }).showToast();
                });
            });
        });
    </script>
</body>
</html>
