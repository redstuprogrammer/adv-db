<?php
include "db.php";

echo "Existing Dentists:\n";
$result = $conn->query("SELECT dentist_id, first_name, last_name FROM dentist");
while($row = $result->fetch_assoc()) {
    echo $row['dentist_id'] . ": " . $row['first_name'] . " " . $row['last_name'] . "\n";
}

echo "\nExisting Users:\n";
$result = $conn->query("SELECT user_id, username, role FROM users");
while($row = $result->fetch_assoc()) {
    echo $row['user_id'] . ": " . $row['username'] . " (" . $row['role'] . ")\n";
}
?>