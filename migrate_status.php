<?php
require_once __DIR__ . '/includes/connect.php';

$sql = "ALTER TABLE appointment MODIFY COLUMN status ENUM('pending','pending_payment','completed','cancelled','approved','disapproved', 'In Progress') NOT NULL DEFAULT 'pending'";

if ($conn->query($sql)) {
    echo "Successfully updated appointment status ENUM to include 'In Progress'.<br>";
} else {
    echo "Error updating table: " . $conn->error . "<br>";
}

$conn->close();
?>
