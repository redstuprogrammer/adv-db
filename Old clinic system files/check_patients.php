<?php
include "db.php";

echo "Current Patients:\n";
$result = $conn->query("SELECT patient_id, first_name, last_name FROM patient");
while($row = $result->fetch_assoc()) {
    echo $row['patient_id'] . ": " . $row['first_name'] . " " . $row['last_name'] . "\n";
}
?>