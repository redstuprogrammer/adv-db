<?php
require_once 'includes/connect.php';
$result = $conn->query("DESCRIBE users");
while($row = $result->fetch_assoc()) {
    print_r($row);
}
