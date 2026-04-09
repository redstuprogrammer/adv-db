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
    logActivity($conn, $tenantId, 'Receptionist Logout', 'Receptionist logged out', $username, 'receptionist', 'Receptionist');
}

$sessionManager->logoutTenant($tenantSlug);

header('Location: tenant_login.php?tenant=' . rawurlencode($tenantSlug));
exit();
?>

