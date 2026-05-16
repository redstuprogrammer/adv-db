<?php
require_once 'includes/connect.php';
$result = mysqli_query($conn, "SHOW COLUMNS FROM tenants LIKE 'status'");
$row = mysqli_fetch_assoc($result);
echo "Column: " . $row['Field'] . "\n";
echo "Type: " . $row['Type'] . "\n";
?>
