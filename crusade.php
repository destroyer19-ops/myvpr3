<?php
ini_set('session.use_only_cookies', 1);
ini_set("display_errors", 1);
require_once 'includes/session.php';
session_init();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

require_once 'includes/db.php';

$user_id = $_SESSION['user_id'];

// Fetch crusades for the current user
$crusades = [];
$stmt = $conn->prepare("SELECT * FROM praise_crusades WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $crusades[] = $row;
}

?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="crusade-container container">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center">My Crusades</h2>
                        <div class="text-center mb-4">
                            <a href="create_crusade.php" class="btn btn-primary">Create New Crusade</a>
                        </div>
                        <ul class="list-group">
                            <?php foreach ($crusades as $crusade): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5><?php echo htmlspecialchars($crusade['title']); ?></h5>
                                        <p><?php echo htmlspecialchars($crusade['description']); ?></p>
                                        <small>Created on: <?php echo date("F j, Y, g:i a", strtotime($crusade['created_at'])); ?></small>
                                    </div>
                                    <a href="crusade_room.php?code=<?php echo $crusade['crusade_code']; ?>" class="btn btn-secondary">View</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/bottom_navbar.php'; ?>
<?php include 'includes/footer.php'; ?>
