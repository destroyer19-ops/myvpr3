<?php
require_once __DIR__ . '/includes/db.php';

$now = date('Y-m-d H:i:s');

// Find crusades that should end
$stmt = $conn->prepare("SELECT id FROM praise_crusades WHERE is_active = 1 AND end_time IS NOT NULL AND end_time <= ?");
$stmt->bind_param("s", $now);
$stmt->execute();
$result = $stmt->get_result();

$crusades_to_end = [];
while ($row = $result->fetch_assoc()) {
    $crusades_to_end[] = $row['id'];
}
$stmt->close();

// End the crusades
if (!empty($crusades_to_end)) {
    $ids = implode(',', array_map('intval', $crusades_to_end));
    $conn->query("UPDATE praise_crusades SET is_active = 0 WHERE id IN ($ids)");
    echo "Ended " . count($crusades_to_end) . " crusade(s).\n";
} else {
    echo "No crusades to end.\n";
}

$conn->close();
?>
