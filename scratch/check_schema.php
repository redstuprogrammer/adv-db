<?php
require_once __DIR__ . '/../includes/connect.php';

echo "--- BILLING TABLE ---\n";
$res = $conn->query('DESCRIBE billing');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\n--- PAYMENT TABLE ---\n";
$res = $conn->query('DESCRIBE payment');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
