<?php
/**
 * Migration Script: Add region column to praise_crusade_stats
 * This script updates the database to support geographic region tracking.
 */

require_once __DIR__ . '/includes/db.php';

echo "<h2>Database Migration: Add Region Tracking</h2>";

// 1. Check if the column already exists
$tableName = 'praise_crusade_stats';
$columnName = 'region';

$checkSql = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'";
$result = $conn->query($checkSql);

if ($result && $result->num_rows > 0) {
    echo "<p style='color: orange;'>[SKIPPED] Column '<b>$columnName</b>' already exists in table '<b>$tableName</b>'.</p>";
} else {
    // 2. Execute the migration
    $migrationSql = "ALTER TABLE `$tableName` ADD COLUMN `$columnName` VARCHAR(100) DEFAULT NULL AFTER `country`";
    
    if ($conn->query($migrationSql) === TRUE) {
        echo "<p style='color: green;'>[SUCCESS] Column '<b>$columnName</b>' successfully added to table '<b>$tableName</b>'.</p>";
    } else {
        echo "<p style='color: red;'>[ERROR] Failed to add column: " . $conn->error . "</p>";
    }
}

echo "<p><a href='index.php'>Return to Home</a></p>";

$conn->close();
?>
