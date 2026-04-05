<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));

// Log logout activity if session exists
if (isset($_SESSION['tenant_id']) && isset($_SESSION['username'])) {
    logActivity($conn, (int)$_SESSION['tenant_id'], 'Dentist Logout', 'Dentist logged out', (string)$_SESSION['username'], 'dentist', 'Dentist');
}

// Clear session
session_unset();
session_destroy();

header('Location: tenant_login.php?tenant=' . rawurlencode($tenantSlug));
exit();
?>

