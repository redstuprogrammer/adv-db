<?php
include "db.php";

echo "<h1>Checking Database Contents</h1>";

// Check dentists
$result = $conn->query("SELECT * FROM dentist");
echo "<h2>Dentists:</h2>";
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['dentist_id']} - {$row['first_name']} {$row['last_name']}<br>";
}

// Check users
$result = $conn->query("SELECT * FROM users");
echo "<h2>Users:</h2>";
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['user_id']} - {$row['username']} ({$row['role']})<br>";
}

$conn->close();
?>