<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

$sessionManager = SessionManager::getInstance();
if (!$sessionManager->isSuperAdmin()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/includes/connect.php';

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/includes/revenue_queries.php';
    
    echo json_encode([
        'success' => true,
        'monthlyRevenue' => getRevenueTrendData($conn, 12)
    ]);

} catch (Exception $e) {
    error_log('Error in get_sales_data.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch sales data'
    ]);
}
?>
