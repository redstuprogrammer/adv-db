<?php
require_once 'includes/connect.php';
$result = $conn->query("SHOW COLUMNS FROM users");
$columns = [];
while($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}
echo "Columns in users table: " . implode(', ', $columns);
