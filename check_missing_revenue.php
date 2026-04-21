<?php
require_once 'includes/connect.php';
echo 'Tenants without revenue data:' . PHP_EOL;
$result = mysqli_query($conn, 'SELECT t.tenant_id, t.company_name FROM tenants t LEFT JOIN tenant_subscription_revenue tsr ON t.tenant_id = tsr.tenant_id WHERE tsr.tenant_id IS NULL');
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['tenant_id'] . ': ' . $row['company_name'] . PHP_EOL;
}
?>