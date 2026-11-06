<?php
require 'api/config.php';
$conn = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$result = $conn->query("DESCRIBE streaming_channels");
while($row = $result->fetch_assoc()) {
    print_r($row);
}
