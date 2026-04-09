<?php
include "db.php";

if (isset($_POST['dentist_id'], $_POST['date'], $_POST['time'])) {
    $dentist_id = mysqli_real_escape_string($conn, $_POST['dentist_id']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $time = mysqli_real_escape_string($conn, $_POST['time']);

    // 1. Check if dentist is on duty (dentist_schedule table)
    $dayOfWeek = date('l', strtotime($date));
    $schedQuery = $conn->query("SELECT * FROM dentist_schedule 
        WHERE dentist_id = '$dentist_id' 
        AND day_of_week = '$dayOfWeek' 
        AND '$time' BETWEEN start_time AND end_time");

    if ($schedQuery->num_rows == 0) {
        echo json_encode(['status' => 'error', 'message' => '❌ Dentist is not on duty or is on break at this time.']);
        exit;
    }

    // 2. Check for double-booking (appointment table)
    $conflict = $conn->query("SELECT * FROM appointment 
        WHERE dentist_id = '$dentist_id' 
        AND appointment_date = '$date' 
        AND appointment_time = '$time' 
        AND status != 'Cancelled'");

    if ($conflict->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => '❌ This time slot is already taken for this dentist.']);
    } else {
        echo json_encode(['status' => 'success', 'message' => '✅ Dentist is available!']);
    }
}
?>