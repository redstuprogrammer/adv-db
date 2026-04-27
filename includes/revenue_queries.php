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
 * Returns array of revenues for last N months (using mysqli)
 */
function getRevenueTrendData($conn, $months = 12) {
    $trend = [];
    $endDate = new DateTime();
    $startDate = clone $endDate;
    $startDate->modify('-' . ($months - 1) . ' months');
    $startStr = $startDate->format('Y-m-01');

    $stmt = mysqli_prepare($conn, "
        SELECT DATE_FORMAT(payment_date, '%Y-%m') as month_ym, 
               COALESCE(SUM(amount), 0) as revenue
        FROM tenant_subscription_revenue 
        WHERE payment_date >= ? 
        GROUP BY month_ym 
        ORDER BY month_ym ASC
    ");
    mysqli_stmt_bind_param($stmt, 's', $startStr);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $dbData = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $dbData[$row['month_ym']] = (float)$row['revenue'];
    }
    mysqli_stmt_close($stmt);

    $currentDate = clone $startDate;
    for ($i = 0; $i < $months; $i++) {
        $monthKey = $currentDate->format('Y-m');
        $trend[] = $dbData[$monthKey] ?? 0.0;
        $currentDate->modify('+1 month');
    }

    return $trend;
}

