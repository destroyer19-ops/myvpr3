<?php
$conn = new mysqli('127.0.0.1', 'ghostdevmo_conference_Dev', 'onfire4God', 'ghostdevmo_virtual_praise_room');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$sql = "ALTER TABLE \`praise_crusades\` ADD COLUMN \`status\` ENUM('draft', 'scheduled', 'live', 'ended', 'cancelled') NOT NULL DEFAULT 'draft' AFTER \`is_active\`";
if ($conn->query($sql) === TRUE) {
    echo "Table updated successfully.\n";
} else {
    echo "Error updating table: " . $conn->error . "\n";
}
