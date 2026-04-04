<?php
session_start();
require_once __DIR__ . '/security_headers.php';
require_once 'connect.php';
require_once 'tenant_utils.php';

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));

// Log logout activity if session exists
if (isset($_SESSION['tenant_id']) && isset($_SESSION['username'])) {
    logActivity($conn, (int)$_SESSION['tenant_id'], 'Receptionist Logout', 'Receptionist logged out', (string)$_SESSION['username'], 'receptionist', 'Receptionist');
}

// Clear session
session_unset();
session_destroy();

header('Location: tenant_login.php?tenant=' . rawurlencode($tenantSlug));
exit();
?>