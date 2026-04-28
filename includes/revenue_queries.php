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
    $data = [];
    
    for ($i = $months - 1; $i >= 0; $i--) {
        $year  = date('Y', strtotime("-{$i} months"));
        $month = date('m', strtotime("-{$i} months"));
        
        // Use billing_period_start to match the correct month
        $sql = "SELECT COALESCE(SUM(amount), 0) as total 
                FROM tenant_subscription_revenue 
                WHERE status = 'paid'
                AND YEAR(billing_period_start) = $year 
                AND MONTH(billing_period_start) = $month";
        
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($result);
        $data[] = (float)($row['total'] ?? 0);
    }
    
    return $data;
}

