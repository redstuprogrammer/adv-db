<?php
require_once __DIR__ . '/../includes/connect.php';

echo "--- USERS TABLE ---\n";
$res = $conn->query('DESCRIBE users');
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}

echo "\n--- DENTIST TABLE ---\n";
$res = $conn->query('DESCRIBE dentist');
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "dentist table does not exist\n";
}

echo "\n--- DENTIST_SCHEDULE TABLE ---\n";
$res = $conn->query('DESCRIBE dentist_schedule');
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}
?>
