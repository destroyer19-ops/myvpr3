<?php
require_once __DIR__ . '/includes/db.php';

$now = date('Y-m-d H:i:s');

// Find crusades that should start
$stmt = $conn->prepare("SELECT id FROM praise_crusades WHERE is_active = 0 AND start_time IS NOT NULL AND start_time <= ?");
$stmt->bind_param("s", $now);
$stmt->execute();
$result = $stmt->get_result();

$crusades_to_start = [];
while ($row = $result->fetch_assoc()) {
    $crusades_to_start[] = $row['id'];
}
$stmt->close();

// Start the crusades
if (!empty($crusades_to_start)) {
    $ids = implode(',', array_map('intval', $crusades_to_start));
    $conn->query("UPDATE praise_crusades SET is_active = 1 WHERE id IN ($ids)");
    echo "Started " . count($crusades_to_start) . " crusade(s).\n";
} else {
    echo "No crusades to start.\n";
}

$conn->close();
?>