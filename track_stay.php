<?php
require_once 'includes/db.php';

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    echo "Invalid session. Please refresh and try again.";
    exit;
}

if (isset($_POST['stat_id'])) {
    $stat_id = $_POST['stat_id'];

    // Get client IP address
    $ip_address = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }

    $country = null;
    $region = null;
    // Attempt to get country and region from IP address using ip-api.com
    $ip_data = @json_decode(file_get_contents("http://ip-api.com/json/" . $ip_address));
    if ($ip_data && $ip_data->status == 'success') {
        $country = $ip_data->country;
        $region = $ip_data->city; // Using city as region
    }

    // Update praise_crusade_stats table
    $stmt = $conn->prepare("UPDATE praise_crusade_stats SET stayed_for_a_minute = 1, country = ?, region = ? WHERE id = ?");
    $stmt->bind_param("ssi", $country, $region, $stat_id);
    $stmt->execute();
    $stmt->close();

    echo "Stay and location tracked successfully. IP: " . $ip_address . ", Country: " . ($country ?: "Unknown") . ", Region: " . ($region ?: "Unknown");
} else {
    echo "No stat ID provided.";
}
?>
