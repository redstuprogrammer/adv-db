<?php
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

if (isset($_SESSION['superadmin_authed']) && $_SESSION['superadmin_authed']) {
    $username = $_SESSION['superadmin_username'] ?? 'Unknown';
    logActivity($conn, 1, 'Logout', 'Superadmin logged out', $username, 'superadmin', 'Super Admin');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// Prevent back button access after logout
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

header('Location: superadmin_login.php');
exit;
