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

// Fetch salvation decisions
$salvationDecisions = [];
$stmt = $conn->prepare("
    SELECT
        psc.id,
        psc.crusade_id,
        psc.user_id,
        psc.ip_address,
        psc.user_agent,
        psc.created_at,
        pu.username AS user_username,
        pc.title AS crusade_title
    FROM praise_salvation_clicks psc
    LEFT JOIN praise_users pu ON psc.user_id = pu.id
    LEFT JOIN praise_crusades pc ON psc.crusade_id = pc.id
    ORDER BY psc.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $salvationDecisions[] = $row;
}
$stmt->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Salvation Decisions</title>
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
                        <h2 class="card-title text-center">Salvation Decisions</h2>
                        <table class="table table-striped mt-4">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Crusade</th>
                                    <th>IP Address</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($salvationDecisions)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No salvation decisions recorded yet.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($salvationDecisions as $decision): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($decision['id']); ?></td>
                                        <td><?php echo htmlspecialchars($decision['user_username'] ?: 'Guest/Unknown'); ?> (ID: <?php echo htmlspecialchars($decision['user_id'] ?: 'N/A'); ?>)</td>
                                        <td><?php echo htmlspecialchars($decision['crusade_title'] ?: 'N/A'); ?> (ID: <?php echo htmlspecialchars($decision['crusade_id'] ?: 'N/A'); ?>)</td>
                                        <td><?php echo htmlspecialchars($decision['ip_address']); ?></td>
                                        <td><?php echo htmlspecialchars($decision['created_at']); ?></td>
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
