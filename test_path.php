<?php
$_SERVER['REQUEST_URI'] = '/api/index.php/realtime/provision';
$_SERVER['SCRIPT_NAME'] = '/api/index.php';
$_SERVER['PATH_INFO'] = '/realtime/provision';
require 'api/helpers.php';
echo "Path: " . get_request_path() . "\n";
