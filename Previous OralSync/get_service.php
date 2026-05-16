<?php
include "db.php";
if(isset($_GET['patient_id'])) {
    $pid = $conn->real_escape_string($_GET['patient_id']);
    // Get latest service for the patient
    $sql = "SELECT service FROM appointment WHERE patient_id = '$pid' ORDER BY appointment_id DESC LIMIT 1";
    $result = $conn->query($sql);
    if($row = $result->fetch_assoc()) {
        echo $row['service'];
    } else {
        echo "No Appointment Found";
    }
}
exit; // Prevents any other HTML from being sent
?>