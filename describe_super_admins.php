<?php
include 'includes/connect.php';
$result = $conn->query("DESCRIBE super_admins");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo 'Error: ' . $conn->error;
}
$conn->close();
?>