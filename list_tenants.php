<?php
require_once 'includes/connect.php';
echo 'All tenants:' . PHP_EOL;
$result = mysqli_query($conn, 'SELECT tenant_id, company_name, subscription_tier, created_at FROM tenants ORDER BY created_at DESC LIMIT 10');
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['tenant_id'] . ': ' . $row['company_name'] . ' (' . $row['subscription_tier'] . ') - ' . $row['created_at'] . PHP_EOL;
}
?>