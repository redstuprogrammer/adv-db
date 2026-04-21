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
function getRevenueTrendData($conn, $months = 12) {
    $trend = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-{$i} months"));
        $trend[$month] = getMonthlyRevenue($conn, $month);
    }
    return array_values($trend); // Chart.js needs numeric array
}

