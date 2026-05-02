<?php
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/revenue_queries.php';

$total_revenue = getTotalRevenue($conn);
$stmt = $pdo->query("SELECT COUNT(DISTINCT tenant_id) as count FROM tenants WHERE status = 'active'");
$active_subscriptions = $stmt->fetch()['count'] ?? 0;
$avg_revenue = $active_subscriptions > 0 ? $total_revenue / $active_subscriptions : 0;

echo "Total Revenue: " . $total_revenue . "\n";
echo "Active Subscriptions: " . $active_subscriptions . "\n";
echo "Average Sales per Tenant: " . $avg_revenue . "\n";
