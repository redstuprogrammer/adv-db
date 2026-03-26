<?php
session_start();
require_once __DIR__ . '/security_headers.php';

// Check authentication
if (empty($_SESSION['superadmin_authed'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/connect.php';

header('Content-Type: application/json');

try {
    // Get monthly revenue for last 12 months
    $monthlyRevenue = [];
    
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-{$i} months"));
        
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM tenant_subscription_revenue 
            WHERE status = 'paid' 
            AND DATE_FORMAT(payment_date, '%Y-%m') = ?
        ");
        $stmt->execute([$month]);
        $result = $stmt->fetch();
        $monthlyRevenue[] = floatval($result['total'] ?? 0);
    }
    
    echo json_encode([
        'success' => true,
        'monthlyRevenue' => $monthlyRevenue
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
