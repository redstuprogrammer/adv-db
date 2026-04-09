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
    // Get monthly revenue for last 12 months
    $monthlyRevenue = [];

    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-{$i} months"));

        $stmt = mysqli_prepare($conn, "
            SELECT COALESCE(SUM(amount), 0) as total
            FROM tenant_subscription_revenue
            WHERE status = 'paid'
            AND DATE_FORMAT(payment_date, '%Y-%m') = ?
        ");
        mysqli_stmt_bind_param($stmt, 's', $month);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $result = $res ? mysqli_fetch_assoc($res) : null;
        $monthlyRevenue[] = floatval($result['total'] ?? 0);
        mysqli_stmt_close($stmt);
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
