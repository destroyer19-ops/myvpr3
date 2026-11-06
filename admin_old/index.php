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

// Fetch key dashboard metrics
$totalUsers = $conn->query("SELECT COUNT(*) FROM praise_users")->fetch_row()[0];
$totalCrusades = $conn->query("SELECT COUNT(*) FROM praise_crusades")->fetch_row()[0];
$totalMeetings = $conn->query("SELECT COUNT(*) FROM praise_meetings")->fetch_row()[0];
$totalSalvationDecisions = $conn->query("SELECT COUNT(*) FROM praise_salvation_clicks")->fetch_row()[0];

// Active subscriptions (end_date > NOW())
$activeSubscriptions = $conn->query("SELECT COUNT(*) FROM subscriptions WHERE end_date > NOW()")->fetch_row()[0];

// Pending subscriptions (status = 'pending')
$pendingSubscriptions = $conn->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetch_row()[0];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Live TV Management</title>
    <link rel="icon" href="../logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
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
                <h2 class="text-center mb-4">Admin Dashboard</h2>

                <!-- Metric Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card text-center p-3">
                            <div class="card-body">
                                <h5 class="card-title">Total Users</h5>
                                <p class="card-text display-4"><?php echo $totalUsers; ?></p>
                                <a href="manage_users.php" class="btn btn-primary btn-sm">Manage Users</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card text-center p-3">
                            <div class="card-body">
                                <h5 class="card-title">Active Subscriptions</h5>
                                <p class="card-text display-4"><?php echo $activeSubscriptions; ?></p>
                                <a href="manage_subscriptions.php" class="btn btn-primary btn-sm">Manage Subs</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card text-center p-3">
                            <div class="card-body">
                                <h5 class="card-title">Pending Subscriptions</h5>
                                <p class="card-text display-4"><?php echo $pendingSubscriptions; ?></p>
                                <a href="manage_subscriptions.php?status=pending" class="btn btn-warning btn-sm">Review Pending</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card text-center p-3">
                            <div class="card-body">
                                <h5 class="card-title">Total Crusades</h5>
                                <p class="card-text display-4"><?php echo $totalCrusades; ?></p>
                                <a href="manage_crusades.php" class="btn btn-primary btn-sm">Manage Crusades</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card text-center p-3">
                            <div class="card-body">
                                <h5 class="card-title">Total Meetings</h5>
                                <p class="card-text display-4"><?php echo $totalMeetings; ?></p>
                                <a href="manage_meetings.php" class="btn btn-primary btn-sm">Manage Meetings</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card text-center p-3">
                            <div class="card-body">
                                <h5 class="card-title">Salvation Decisions</h5>
                                <p class="card-text display-4"><?php echo $totalSalvationDecisions; ?></p>
                                <a href="manage_salvation_decisions.php" class="btn btn-primary btn-sm">View Decisions</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links/Management Sections -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Quick Management Links</h4>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="manage_live_streams.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-video fa-fw me-2"></i>Manage Live TV Streams
                        </a>
                        <a href="manage_official_crusade.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-cross fa-fw me-2"></i>Manage Official Crusades
                        </a>
                        <a href="reset_user_password.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-key fa-fw me-2"></i>Reset User Password
                        </a>
                        <a href="manage_videos.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-film fa-fw me-2"></i>Manage Video Library
                        </a>
                        <a href="stats.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-line fa-fw me-2"></i>View Detailed Stats
                        </a>
                        <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                            <i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
