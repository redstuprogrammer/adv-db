<?php
// superadmin_analytics_api.php - Returns analytics data for superadmin dashboard
session_start();
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/session_utils.php';

$sessionManager = SessionManager::getInstance();
if (!$sessionManager->isSuperAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!$conn) {
    echo json_encode(['error' => 'Connection failed']);
    exit;
}

$analytics = getSuperAdminAnalytics($conn);
echo json_encode($analytics);

$conn->close();
?>
