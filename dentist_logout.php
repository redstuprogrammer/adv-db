<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

$sessionManager = SessionManager::getInstance();
$tenantSlug = $sessionManager->getCurrentTenantSlug() ?: trim((string)($_GET['tenant'] ?? ''));

// Log logout activity if session exists
$tenantId = $sessionManager->getTenantId();
$username = $sessionManager->getUsername();
if ($tenantId && $username) {
    require_once __DIR__ . '/includes/connect.php';
    require_once __DIR__ . '/includes/tenant_utils.php';
    logActivity($conn, $tenantId, 'Logout', 'Dentist logged out', $username, 'dentist', 'Dentist');
}

$sessionManager->logoutTenant($tenantSlug);

// Prevent back button access after logout
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

header('Location: tenant_login.php?tenant=' . rawurlencode($tenantSlug));
exit();
?>

