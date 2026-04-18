<?php
include 'includes/connect.php';
$result = $conn->query("SHOW TABLES LIKE 'clinic_sch%'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo array_values($row)[0] . "\n";
    }
} else {
    echo 'Error: ' . $conn->error;
}
$conn->close();
?>