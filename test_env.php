<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$env_file = __DIR__ . '/api/.env.bak';
$lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
var_dump($lines);

$_ENV = $_ENV ?? [];

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
echo "Done";
