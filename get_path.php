<?php
if (php_sapi_name() !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        require_once 'includes/session.php';
        session_init();
    }
    if (empty($_SESSION['admin_logged_in'])) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
}
echo __DIR__;
?>
