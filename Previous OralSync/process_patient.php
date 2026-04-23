<?php
session_start();
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize form data
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name  = mysqli_real_escape_string($conn, $_POST['last_name']);
    $contact    = mysqli_real_escape_string($conn, $_POST['contact']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $dob        = mysqli_real_escape_string($conn, $_POST['dob']);
    $gender     = mysqli_real_escape_string($conn, $_POST['gender']);
    $address    = mysqli_real_escape_string($conn, $_POST['address']);
    $note       = mysqli_real_escape_string($conn, $_POST['note']);

    // SQL Query (Ensure these column names exist in your 'patient' table)
    $sql = "INSERT INTO patient (first_name, last_name, contact_number, email, dob, gender, address, medical_note) 
            VALUES ('$first_name', '$last_name', '$contact', '$email', '$dob', '$gender', '$address', '$note')";

    if ($conn->query($sql) === TRUE) {
        // Success: Redirect back to the patients directory
        header("Location: patients.php?msg=registered");
        exit();
    } else {
        // Error handling
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
} else {
    header("Location: patients.php");
    exit();
}
?>