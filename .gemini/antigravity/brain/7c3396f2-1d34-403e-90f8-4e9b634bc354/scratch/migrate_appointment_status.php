<?php
// Manual connection for local migration
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "oral";
$port = 3306;

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "ALTER TABLE appointment MODIFY COLUMN status ENUM('pending','pending_payment','completed','cancelled','approved','disapproved', 'In Progress') NOT NULL DEFAULT 'pending'";

if ($conn->query($sql)) {
    echo "Successfully updated appointment status ENUM to include 'In Progress'.\n";
} else {
    echo "Error updating table: " . $conn->error . "\n";
}

$conn->close();
?>
