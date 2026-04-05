<?php
require_once 'includes/connect.php';
$sql = "ALTER TABLE dentist
ADD COLUMN primary_specialization VARCHAR(100) DEFAULT 'General Practitioner',
ADD COLUMN license_number VARCHAR(50) DEFAULT NULL,
ADD COLUMN joined_system DATE DEFAULT NULL,
ADD COLUMN professional_biography TEXT DEFAULT NULL,
ADD COLUMN contact_phone VARCHAR(20) DEFAULT NULL";
if (mysqli_query($conn, $sql)) {
    echo 'Dentist table updated successfully';
} else {
    echo 'Error: ' . mysqli_error($conn);
}
?>