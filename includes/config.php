<?php
// includes/config.php - Platform integration configuration
// Configure real credentials via environment variables (.env)

// Brevo (formerly Sendinblue) Configuration
define('BREVO_API_KEY', $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY') ?: '');
define('BREVO_SENDER_EMAIL', $_ENV['BREVO_SENDER_EMAIL'] ?? getenv('BREVO_SENDER_EMAIL') ?: 'info@myvpr.org');
define('BREVO_SENDER_NAME', $_ENV['BREVO_SENDER_NAME'] ?? getenv('BREVO_SENDER_NAME') ?: 'Virtual Praise Room');

// Daily.co Configuration
define('DAILY_API_KEY', $_ENV['DAILY_API_KEY'] ?? getenv('DAILY_API_KEY') ?: '');
define('DAILY_ROOM_DURATION', (int)($_ENV['DAILY_ROOM_DURATION'] ?? getenv('DAILY_ROOM_DURATION') ?: 86400)); // 24 hours default

// KingsChat Configuration
define('KINGSCHAT_CLIENT_ID', $_ENV['KINGSCHAT_CLIENT_ID'] ?? getenv('KINGSCHAT_CLIENT_ID') ?: '');

// Espees Configuration
define('ESPEES_API_KEY', $_ENV['ESPEES_API_KEY'] ?? getenv('ESPEES_API_KEY') ?: '');
define('ESPEES_MERCHANT_WALLET', $_ENV['ESPEES_MERCHANT_WALLET'] ?? getenv('ESPEES_MERCHANT_WALLET') ?: 'LMM01');
define('ESPEES_MERCHANT_NAME', $_ENV['ESPEES_MERCHANT_NAME'] ?? getenv('ESPEES_MERCHANT_NAME') ?: 'Virtual Praise Room');
