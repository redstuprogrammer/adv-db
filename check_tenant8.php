<?php
require_once 'includes/connect.php';
$result = mysqli_query($conn, 'SELECT COUNT(*) as count FROM tenant_subscription_revenue WHERE tenant_id = 8');
$row = mysqli_fetch_assoc($result);
echo 'Revenue records for tenant 8: ' . $row['count'] . PHP_EOL;
?>