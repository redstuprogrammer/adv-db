<?php
include "db.php";
$patient_id = $_GET['patient_id'];

$query = "SELECT a.appointment_id, s.service_name, a.appointment_date 
          FROM appointment a 
          JOIN service s ON a.service_id = s.service_id 
          WHERE a.patient_id = '$patient_id' 
          ORDER BY a.appointment_date DESC";

$result = $conn->query($query);
$services = [];

while($row = $result->fetch_assoc()) {
    $services[] = $row;
}

echo json_encode($services);
?>