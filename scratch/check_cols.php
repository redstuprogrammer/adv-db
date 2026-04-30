<?php
require_once dirname(__DIR__) . '/includes/connect.php';
header('Content-Type: text/plain');

$result = mysqli_query($conn, "SHOW COLUMNS FROM tenant_configs");
echo "Columns in tenant_configs:\n";
while ($row = mysqli_fetch_assoc($result)) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
