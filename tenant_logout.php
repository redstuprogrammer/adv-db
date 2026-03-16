<?php
session_start();

$tenantSlug = trim((string)($_GET['tenant'] ?? ($_SESSION['tenant_slug'] ?? '')));

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

header('Location: tenant_login.php?tenant=' . rawurlencode($tenantSlug ?: 'unknown'));
exit;

