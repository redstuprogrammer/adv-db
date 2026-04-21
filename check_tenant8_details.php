<?php
require_once 'includes/connect.php';
$result = mysqli_query($conn, 'SELECT * FROM tenant_subscription_revenue WHERE tenant_id = 8');
while ($row = mysqli_fetch_assoc($result)) {
    echo 'ID: ' . $row['id'] . ', Amount: ' . $row['amount'] . ', Status: ' . $row['status'] . ', Date: ' . $row['payment_date'] . PHP_EOL;
}
?>