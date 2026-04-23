<?php
/**
 * Revenue Query Helper - SUPER ADMIN Revenue Trends Fix
 * Handles monthly revenue including active subscription projection
 */


function getMonthlyRevenue($conn, $target_month_ym = null) {
    // Simple: active tenants × ₱250 monthly subscription
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as active_count FROM tenants WHERE status = 'active'");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return (floatval($row['active_count'] ?? 0)) * 250;
}


function getTotalRevenue($conn) {
    $stmt = mysqli_prepare($conn, "
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM tenant_subscription_revenue 
        WHERE status = 'paid'
    ");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return floatval($row['total'] ?? 0);
}

/**
 * Returns array of [month_ym => revenue] for last N months
 */
function getRevenueTrendData($pdo, $months = 12) {
    $trend = [];
    $endDate = new DateTime();
    $startDate = clone $endDate;
    $startDate->modify('-' . $months . ' months');
    
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(date_paid, '%Y-%m') as month_ym, 
               COALESCE(SUM(amount), 0) as revenue
        FROM tenant_subscription_revenue 
        WHERE date_paid >= ? 
        GROUP BY month_ym 
        ORDER BY month_ym ASC
    ");
    $stmt->execute([$startDate->format('Y-m-01')]);
    
    $currentDate = clone $startDate;
    while ($currentDate <= $endDate) {
        $monthKey = $currentDate->format('Y-m');
        $row = $stmt->fetch();
        $trend[] = $row && $row['month_ym'] === $monthKey ? (float)$row['revenue'] : 0.0;
        $currentDate->modify('+1 month');
    }
    
    return $trend;
}

