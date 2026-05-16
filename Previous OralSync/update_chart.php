<?php
include "db.php";
if (isset($_POST['tooth_num'])) {
    $p_id = $_POST['patient_id'];
    $t_num = $_POST['tooth_num'];

    // Check if tooth record exists
    $check = $conn->query("SELECT * FROM dental_chart WHERE patient_id = $p_id AND tooth_number = $t_num");
    
    if ($check->num_rows > 0) {
        $conn->query("DELETE FROM dental_chart WHERE patient_id = $p_id AND tooth_number = $t_num");
    } else {
        $conn->query("INSERT INTO dental_chart (patient_id, tooth_number, condition_type) VALUES ($p_id, $t_num, 'Selected')");
    }
}
?>
