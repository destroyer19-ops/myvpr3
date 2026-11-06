<?php
/**
 * api/store_pending_plan.php
 * Temporarily stores the selected plan in session for the payment callback
 */
require_once '../includes/session.php';
session_init();

if (isset($_GET['plan'])) {
    $_SESSION['pending_espees_plan'] = $_GET['plan'];
}
echo json_encode(['status' => 'ok']);
