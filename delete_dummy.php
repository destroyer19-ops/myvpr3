<?php
require_once __DIR__ . '/includes/db.php';
$conn->query("DELETE FROM streaming_channels WHERE aws_stream_key_arn LIKE '%dummy%' OR ingest_endpoint LIKE '%dummy%'");
echo "Deleted " . $conn->affected_rows . " dummy channels. You can now go back to the dashboard and provision a real one.";
