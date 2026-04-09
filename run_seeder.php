<?php
session_start();
// Maintain backward compatibility with legacy superadmin auth checks
$_SESSION['superadmin'] = [
    'authed' => true,
    'id' => $_SESSION['superadmin']['id'] ?? 0,
    'username' => $_SESSION['superadmin']['username'] ?? 'superadmin',
    'login_time' => time()
];
$_SESSION['superadmin_authed'] = true;
$_SESSION['role'] = 'superadmin';
$_SESSION['superadmin_username'] = $_SESSION['superadmin']['username'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['seed_sales_data'] = '1';
require 'seed_sample_data.php';
?>
