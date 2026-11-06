<?php
/**
 * api/config.php — Environment configuration for the VPR API
 * 
 * Copy this file's companion .env.example to .env and fill in values.
 * Never commit .env to Git.
 */

// Load .env file if it exists (checks api/.env first, then root .env)
$env_files = [__DIR__ . '/.env', dirname(__DIR__) . '/.env'];
foreach ($env_files as $env_file) {
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || substr($line, 0, 1) === '#') continue;
            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $val;
                    putenv("$key=$val");
                }
            }
        }
    }
}

// ── App URLs ───────────────────────────────────────────────────────
if (!function_exists('vpr_detect_app_url')) {
    function vpr_detect_app_url() {
        if (!empty($_ENV['REACT_APP_URL'])) return rtrim($_ENV['REACT_APP_URL'], '/');
        if (!empty(getenv('REACT_APP_URL'))) return rtrim(getenv('REACT_APP_URL'), '/');
        if (!empty($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            return ($is_https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
        }
        return 'http://localhost:5173';
    }
}
define('REACT_APP_URL', vpr_detect_app_url());

// ── KingsChat OAuth ────────────────────────────────────────────────
define('KC_CLIENT_ID',     $_ENV['KINGSCHAT_CLIENT_ID']     ?? getenv('KINGSCHAT_CLIENT_ID') ?: '');
define('KC_CLIENT_SECRET', $_ENV['KINGSCHAT_CLIENT_SECRET'] ?? $_ENV['KINGSCHAT_API_KEY'] ?? getenv('KINGSCHAT_CLIENT_SECRET') ?: getenv('KINGSCHAT_API_KEY') ?: '');
$default_kc_redirect = rtrim(REACT_APP_URL, '/') . '/api/auth/kingschat/callback';
define('KC_REDIRECT_URI',  $_ENV['KINGSCHAT_REDIRECT_URI']  ?? getenv('KINGSCHAT_REDIRECT_URI') ?: $default_kc_redirect);
define('KC_AUTH_URL',      'https://accounts.kingschat.online/log-in');
define('KC_TOKEN_URL',     'https://connect.kingsch.at/developer/api/oauth2/token');
define('KC_USERINFO_URL',  'https://connect.kingsch.at/developer/api/profile');

// ── Squad Payment Gateway ──────────────────────────────────────────
define('SQUAD_SECRET_KEY',     $_ENV['SQUAD_SECRET_KEY']     ?? getenv('SQUAD_SECRET_KEY') ?: '');
define('SQUAD_PUBLIC_KEY',     $_ENV['SQUAD_PUBLIC_KEY']     ?? getenv('SQUAD_PUBLIC_KEY') ?: '');
define('SQUAD_ENV',            strtolower($_ENV['SQUAD_ENV'] ?? getenv('SQUAD_ENV') ?: 'sandbox'));
define('SQUAD_BASE_URL',       (SQUAD_ENV === 'production') ? 'https://api-d.squadco.com' : 'https://sandbox-api-d.squadco.com');
define('SQUAD_WEBHOOK_SECRET', $_ENV['SQUAD_WEBHOOK_SECRET'] ?? getenv('SQUAD_WEBHOOK_SECRET') ?: SQUAD_SECRET_KEY);

