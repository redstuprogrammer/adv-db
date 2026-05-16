<?php
include "db.php";
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $patient_id = $_POST['patient_id'];
    $appointment_id = $_POST['appointment_id'];
    $amount = $_POST['amount'];
    $mode = $_POST['mode'];
    $status = $_POST['status'];
    $payment_id = $_POST['payment_id']; // Only filled if editing

    if (!empty($payment_id)) {
        // UPDATE EXISTING INVOICE
        $sql = "UPDATE payment SET amount=?, mode=?, status=? WHERE payment_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dssi", $amount, $mode, $status, $payment_id);
    } else {
        // INSERT NEW INVOICE
        $sql = "INSERT INTO payment (patient_id, appointment_id, amount, mode, status, date_created) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iidss", $patient_id, $appointment_id, $amount, $mode, $status);
    }

    if ($stmt->execute()) {
        header("Location: receptionist_billing.php?msg=success");
    } else {
        echo "Error: " . $conn->error;
    }
    exit();
}
?>