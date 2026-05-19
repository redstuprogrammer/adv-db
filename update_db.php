<?php
/**
 * OralSync Database Update Script
 * Run this script to update the tenants table with barangay and zip_code columns.
 */

header('Content-Type: text/plain');

require_once __DIR__ . '/includes/connect.php';

if (!$conn) {
    die("Database connection failed.\n");
}

echo "Starting database updates...\n";

// Helper to check if a column exists
function columnExists($conn, $table, $column) {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return mysqli_num_rows($result) > 0;
}

// 1. Check and add 'barangay' column
if (!columnExists($conn, 'tenants', 'barangay')) {
    echo "Adding 'barangay' column to 'tenants' table...\n";
    $sql = "ALTER TABLE tenants ADD COLUMN barangay VARCHAR(100) NULL AFTER city";
    if (mysqli_query($conn, $sql)) {
        echo "Successfully added 'barangay' column.\n";
    } else {
        echo "Error adding 'barangay' column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "'barangay' column already exists in 'tenants' table.\n";
}

// 2. Check and add 'zip_code' column
if (!columnExists($conn, 'tenants', 'zip_code')) {
    echo "Adding 'zip_code' column to 'tenants' table...\n";
    $sql = "ALTER TABLE tenants ADD COLUMN zip_code VARCHAR(10) NULL AFTER barangay";
    if (mysqli_query($conn, $sql)) {
        echo "Successfully added 'zip_code' column.\n";
    } else {
        echo "Error adding 'zip_code' column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "'zip_code' column already exists in 'tenants' table.\n";
}

echo "Database update process completed.\n";
?>
