<?php
ini_set("display_errors", 1);
require_once __DIR__ . '/../includes/session.php';
session_init();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Page Deprecated</title>
    <link rel="icon" href="../logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6; }
        .navbar { background-color: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .admin-container { padding: 80px 0; }
        .card { border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: none; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="admin-container container">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 class="card-title">This Page Has Been Updated!</h2>
                        <p class="text-muted">The way "Official Live Crusades" are managed has been simplified. Instead of using this page, you now manage all streams from a single dashboard.</p>
                        <p>To set a stream as the main live event, simply go to the dashboard, edit the desired stream, and set its status to "Live".</p>
                        <hr>
                        <a href="index.php" class="btn btn-primary btn-lg">Go to Live Stream Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
