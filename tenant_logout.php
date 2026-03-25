<?php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/tenant_utils.php';

$tenantSlug = trim((string)($_GET['tenant'] ?? ($_SESSION['tenant_slug'] ?? '')));
$tenantId = (int)($_SESSION['tenant_id'] ?? 0);
$tenantEmail = $_SESSION['tenant_email'] ?? '';

if ($tenantId > 0) {
    logActivity($conn, $tenantId, 'Tenant Logout', 'Tenant logged out', $tenantEmail, 'tenant_owner', 'Tenant Owner');
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
header('Location: /tenant_login.php?tenant=' . rawurlencode($tenantSlug ?: 'unknown'));
exit;
?>
