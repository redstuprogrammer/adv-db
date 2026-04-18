<?php
include 'includes/connect.php';
$result = $conn->query("DESCRIBE tenants");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo 'Error: ' . $conn->error;
}
$conn->close();
?>