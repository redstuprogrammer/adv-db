<?php
define('ROOT_PATH', __DIR__ . '/');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}
require_once ROOT_PATH . 'includes/security_headers.php';
require_once ROOT_PATH . 'includes/session_utils.php';
require_once ROOT_PATH . 'includes/tenant_utils.php';
require_once ROOT_PATH . 'settings.php';

$sessionManager = SessionManager::getInstance();

// Routing logic
if ($sessionManager->isSuperAdmin()) {
    header('Location: superadmin_dash.php');
    exit();
}

// Check if user is logged in as tenant user
if ($sessionManager->isTenantUser()) {
    $dashboardUrl = $sessionManager->getDashboardUrl();
    if ($dashboardUrl) {
        header('Location: ' . $dashboardUrl);
        exit();
    }
}

// Check for tenant slug in URL
$tenantParam = trim($_GET['tenant'] ?? '');
if (!empty($tenantParam)) {
    header('Location: tenant_login.php?tenant=' . rawurlencode($tenantParam));
    exit();
}

// Default to superadmin login
header('Location: superadmin_login.php');
exit();
?>
