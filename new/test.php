<?php
require_once dirname(__FILE__) . '/../includes/db.php';
$stmt = $conn->prepare("SELECT id, name FROM streaming_plans");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
