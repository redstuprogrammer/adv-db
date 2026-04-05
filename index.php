<?php
define('ROOT_PATH', __DIR__ . '/');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}
require_once ROOT_PATH . 'includes/security_headers.php';

// Routing logic
if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
    redirect('superadmin_dash.php');
}

// Check for tenant slug in URL (assuming query parameter or path)
if (isset($_GET['tenant']) || strpos($_SERVER['REQUEST_URI'], '/tenant/') !== false) {
    redirect('tenant_login.php');
}

// Default to superadmin login
redirect('superadmin_login.php');
?>
