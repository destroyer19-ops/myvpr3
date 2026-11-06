<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/index.php/streaming/provision';

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'testuser';

require 'api/index.php';
