<?php
// A temporary script to generate a secure password hash for the admin.
// Restricted to admin sessions or CLI.
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

$new_password = 'admin123';

// Hash the password using PHP's standard password hashing algorithm.
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

echo "<h3>Admin Password Hash Generator</h3>";
echo "<p><strong>Your new password is:</strong> " . htmlspecialchars($new_password) . "</p>";
echo "<p><strong>Copy the hash below and paste it into the 'password' field in your 'praise_admins' database table:</strong></p>";
echo "<textarea rows='3' cols='80' readonly>" . htmlspecialchars($hashed_password) . "</textarea>";
echo "<p><strong style='color:red;'>Important: Delete this file (generate_admin_hash.php) from your server immediately after you are done.</strong></p>";

?>
