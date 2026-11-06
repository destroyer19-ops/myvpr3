<?php
require_once 'includes/db.php';
// wait, we can also check api/endpoints/db.php or similar

$tables = [];
$result = $conn->query("SHOW TABLES");
if ($result) {
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        echo "\n=== Table: $table ===\n";
        $result = $conn->query("SHOW CREATE TABLE $table");
        if ($row = $result->fetch_assoc()) {
            echo $row['Create Table'] . ";\n";
        }
    }
}
