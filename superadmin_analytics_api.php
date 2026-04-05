<?php
// superadmin_analytics_api.php - Returns analytics data for superadmin dashboard
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

// Check session for superadmin
session_start();
if (!isset($_SESSION['superadmin_authed']) || $_SESSION['superadmin_authed'] !== true) {
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
