<?php
/**
 * api/espees_proxy.php
 * Secure proxy to communicate with Espees API
 */
require_once '../includes/db.php';
require_once '../includes/config.php';

header("Content-Type: application/json");

$requestMethod = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($requestMethod !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Only POST requests are allowed."]);
    exit;
}

$body = file_get_contents("php://input");
$data = json_decode($body, true);
if ($data === null) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON body."]);
    exit;
}

if ($action === "initiate") {
    $url = "https://api.espees.org/v2/payment/product";
    
    // Clean up the payload: Espees API expects these specific fields in the body
    $payload = [
        "product_sku"     => $data['product_sku'] ?? 'sub_plan',
        "narration"       => $data['narration'] ?? 'Subscription',
        "price"           => (float)($data['price'] ?? 0),
        "merchant_wallet" => ESPEES_MERCHANT_WALLET,
        "success_url"     => $data['success_url'] ?? '',
        "fail_url"        => $data['fail_url'] ?? '',
        "user_data"       => $data['user_data'] ?? []
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "x-api-key: " . ESPEES_API_KEY
    ]);

    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    http_response_code($statusCode);
    echo $response;
    exit;
}

http_response_code(400);
echo json_encode(["error" => "Invalid action."]);
