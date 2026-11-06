<?php
require_once __DIR__ . '/db.php'; 

$current_page = basename($_SERVER['PHP_SELF']);
$has_active_crusade = isset($_SESSION['current_crusade_code']);
$has_active_meeting = isset($_SESSION['current_meeting_code']);

// Check subscription status for the logged-in user
$is_subscribed = false;
if (isset($_SESSION['user_id'])) {
    global $conn; // Declare $conn as global
    // It's better to include db.php only when needed
    // The previous line 'require_once __DIR__ . '/db.php';' was moved to the top.
    // Removed redundant check for $conn instanceof mysqli
    $user_id = $_SESSION['user_id'];
    if ($conn instanceof mysqli) { // Add this check
        $stmt = $conn->prepare("SELECT end_date FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $subscription = $result->fetch_assoc();
            $stmt->close();

            if ($subscription) {
                $end_date = new DateTime($subscription['end_date']);
                $now = new DateTime();
                if ($end_date > $now) {
                    $is_subscribed = true;
                }
            }
        }
    }
}
?>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg fixed-top <?php echo ($current_page != 'index.php') ? 'navbar-scrolled' : ''; ?>">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="logo.png" alt="Virtual Praise Room Logo" style="height: 30px;">
        </a>

        <?php if ($has_active_crusade) : ?>
            <a class="btn btn-warning btn-sm navbar-resume d-lg-inline-flex" href="crusade_room.php?code=<?php echo htmlspecialchars($_SESSION['current_crusade_code']); ?>">
                Resume Crusade
            </a>
        <?php endif; ?>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if ($has_active_crusade) : ?>
                    <li class="nav-item d-lg-none">
                        <a class="btn btn-warning w-100 mb-2" href="crusade_room.php?code=<?php echo htmlspecialchars($_SESSION['current_crusade_code']); ?>">
                            Resume Crusade
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($has_active_meeting) : ?>
                    <li class="nav-item d-lg-none">
                        <a class="btn btn-outline-warning w-100 mb-2" href="meeting-room.php?code=<?php echo htmlspecialchars($_SESSION['current_meeting_code']); ?>">
                            Resume Meeting
                        </a>
                    </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo ($current_page == 'about.php' || $current_page == 'contact.php') ? 'active' : ''; ?>" href="#" id="infoDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Info
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="infoDropdown">
                        <li><a class="dropdown-item <?php echo ($current_page == 'about.php') ? 'active' : ''; ?>" href="about.php">About Us</a></li>
                        <li><a class="dropdown-item <?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>" href="contact.php">Contact</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#giveLifeModal">
                        Give Your Life to Christ
                    </button>
                </li>
                <li class="nav-item ms-3">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#givingModal">
                        Give
                    </button>
                </li>
                <li class="nav-item ms-3 nav-item-language">
                    <div id="google_translate_element" class="google-translate"></div>
                </li>

                <?php if (isset($_SESSION['user_id'])) : ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="watchDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Watch
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="watchDropdown">
                            <li><a class="dropdown-item" href="live_tv.php">Live TV</a></li>
                            <li><a class="dropdown-item" href="videos.php">Videos</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="createDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Create
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="createDropdown">
                            <li><a class="dropdown-item" href="create_crusade.php">Create Crusade</a></li>
                            <li><a class="dropdown-item" href="create_meeting.php">Create Worship Cloud</a></li>
                            <li><a class="dropdown-item" href="create_praise_session.php">Create Meeting</a></li>
                            <li><a class="dropdown-item" href="share_testimony.php">Share Testimony</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="accountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="accountDropdown">
                            <li><a class="dropdown-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php"><i class="fas fa-th-large me-2"></i>Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if (isset($_SESSION['current_meeting_code'])) : ?>
                                <li><a class="dropdown-item" href="meeting-room.php?code=<?php echo htmlspecialchars($_SESSION['current_meeting_code']); ?>">My Current Meeting</a></li>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['current_crusade_code'])) : ?>
                                <li><a class="dropdown-item" href="crusade_room.php?code=<?php echo htmlspecialchars($_SESSION['current_crusade_code']); ?>">My Crusade</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="quarterly_wrap.php">Quarterly Wrap</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>

                    <?php if (!$is_subscribed): ?>
                        <li class="nav-item ms-3">
                            <a class="btn btn-warning" href="subscribe.php" role="button">Subscribe</a>
                        </li>
                    <?php endif; ?>

                <?php else : ?>
                    <li class="nav-item">
                        <a class="btn btn-primary-custom" href="register.php">Sign Up</a>
                    </li>
                <?php endif; ?>

                <li class="nav-item" style="display: none;">
                    <a class="nav-link" href="#" id="install-button">Install App</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
