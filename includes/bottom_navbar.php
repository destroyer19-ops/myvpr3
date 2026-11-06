<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['user_id']);
$home_link = $is_logged_in ? 'dashboard.php' : 'index.php';
$home_active = $is_logged_in ? 'dashboard.php' : 'index.php';
?>
<!-- Bottom Navigation Bar -->
<div class="bottom-nav">
    <a href="<?php echo $home_link; ?>" class="bottom-nav-item <?php echo ($current_page == $home_active) ? 'active' : ''; ?>" aria-label="Home">
        <i class="fas fa-home"></i>
        <span><?php echo $is_logged_in ? 'Dashboard' : 'Home'; ?></span>
    </a>
    <a href="create_crusade.php" class="bottom-nav-item primary <?php echo ($current_page == 'create_crusade.php') ? 'active' : ''; ?>" aria-label="Create Crusade">
        <i class="fas fa-plus"></i>
        <span>New</span>
    </a>
    <a href="live_tv.php" class="bottom-nav-item <?php echo ($current_page == 'live_tv.php') ? 'active' : ''; ?>" aria-label="Live TV">
        <i class="fas fa-tv"></i>
        <span>Live</span>
    </a>
    <a href="videos.php" class="bottom-nav-item <?php echo ($current_page == 'videos.php') ? 'active' : ''; ?>" aria-label="Videos">
        <i class="fas fa-video"></i>
        <span>Videos</span>
    </a>
</div>
