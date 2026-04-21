<?php
require_once 'includes/connect.php';
$result = mysqli_query($conn, 'DESCRIBE tenant_subscription_revenue');
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . PHP_EOL;
}
?>