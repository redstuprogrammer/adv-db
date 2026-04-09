<?php
session_start();
require_once __DIR__ . '/includes/session_utils.php';
require_once __DIR__ . '/includes/tenant_utils.php';

$sessionManager = SessionManager::getInstance();
$tenantSlug = $sessionManager->getCurrentTenantSlug() ?: 'unknown';
$tenantId = $sessionManager->getTenantId();
$username = $sessionManager->getUsername();

if ($tenantId) {
    require_once __DIR__ . '/includes/connect.php';
    logActivity($conn, $tenantId, 'Tenant Logout', 'Tenant logged out', $username, strtolower($sessionManager->getRole()), ucfirst($sessionManager->getRole()));
}

$sessionManager->logoutTenant($tenantSlug);

// Build redirect URL — avoid double-slash when base is empty
$base = rtrim(getAppBasePath(), '/');
if ($tenantSlug && $tenantSlug !== 'unknown') {
    $redirect = $base . '/tenant_login.php?tenant=' . rawurlencode($tenantSlug);
} else {
    $redirect = $base . '/tenant_login.php';
}
header('Location: ' . $redirect);
exit;
?>
