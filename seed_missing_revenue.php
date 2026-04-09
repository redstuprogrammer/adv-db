<?php
require_once 'includes/connect.php';
require_once 'includes/subscription_tiers.php';

echo 'Seeding revenue data for tenants without it...' . PHP_EOL;

// Get tenants without revenue data
$result = mysqli_query($conn, 'SELECT t.tenant_id, t.company_name, t.subscription_tier, t.created_at FROM tenants t LEFT JOIN tenant_subscription_revenue tsr ON t.tenant_id = tsr.tenant_id WHERE tsr.tenant_id IS NULL');

$records_added = 0;
$tier_prices = [
    'startup' => 500.00,
    'professional' => 1000.00,
    'enterprise' => 499.00
];

while ($tenant = mysqli_fetch_assoc($result)) {
    $tier = $tenant['subscription_tier'] ?? 'startup';
    $amount = $tier_prices[$tier] ?? 50.00;

    // Create 12 months of data
    for ($i = 0; $i < 12; $i++) {
        $month_ago = date('Y-m-d', strtotime("-" . (12 - $i) . " months", strtotime('first day of this month')));
        $period_start = $month_ago;
        $period_end = date('Y-m-d', strtotime('last day of month', strtotime($month_ago)));
        $payment_date = $period_end;

        $sql = "INSERT INTO tenant_subscription_revenue (tenant_id, subscription_tier, amount, billing_period_start, billing_period_end, status, payment_date, created_at) 
                VALUES (?, ?, ?, ?, ?, 'paid', ?, NOW())";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isdsss", $tenant['tenant_id'], $tier, $amount, $period_start, $period_end, $payment_date);

        if (mysqli_stmt_execute($stmt)) {
            $records_added++;
        }
        mysqli_stmt_close($stmt);
    }

    echo 'Added revenue data for: ' . $tenant['company_name'] . PHP_EOL;
}

echo 'Total records added: ' . $records_added . PHP_EOL;
?>