<?php
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

try {
    error_log("=== tenant_login.php START ===");
    error_log("Tenant slug: " . ($_GET['tenant'] ?? 'NONE'));
    
    require_once __DIR__ . '/security_headers.php';
    error_log("security_headers loaded");
    
    require_once 'connect.php';
    error_log("connect loaded");
    
    require_once 'tenant_utils.php';
    error_log("tenant_utils loaded");
} catch (Throwable $e) {
    error_log("FATAL ERROR: " . $e->getMessage());
    error_log($e->getTraceAsString());
    http_response_code(500);
    die("Error loading dependencies");
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}