<?php
require 'api/config.php';
require 'api/vendor/autoload.php';

$aws_key = getenv('AWS_ACCESS_KEY_ID');
$aws_secret = getenv('AWS_SECRET_ACCESS_KEY');

$ivsClient = new Aws\Ivs\IvsClient([
    'version' => 'latest',
    'region'  => 'us-east-1',
    'credentials' => [
        'key'    => $aws_key,
        'secret' => $aws_secret,
    ]
]);

try {
    $result = $ivsClient->createChannel([
        'name' => 'vpr_user_test',
        'type' => 'STANDARD',
        'latencyMode' => 'LOW', 
        'authorized' => false
    ]);
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
