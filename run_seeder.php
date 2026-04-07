<?php
session_start();
$_SESSION['superadmin_authed'] = true;
$_SESSION['role'] = 'superadmin';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['seed_sales_data'] = '1';
require 'seed_sample_data.php';
?>
