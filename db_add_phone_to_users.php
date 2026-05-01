<?php
/**
 * db_add_phone_to_users.php
 * Migration script to add phone_number column to users table.
 */
require_once __DIR__ . '/includes/connect.php';

echo "<h2>Database Migration: Add Phone Number to Users</h2>";

function log_msg($msg) {
    echo $msg . "<br>";
}

function addColumnIfMissing($conn, $table, $column, $definition) {
    try {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        if (mysqli_num_rows($result) == 0) {
            log_msg("Adding column $column to $table...");
            $sql = "ALTER TABLE `$table` ADD `$column` $definition";
            if (mysqli_query($conn, $sql)) {
                log_msg("✓ Column $column added successfully.");
            } else {
                log_msg("✗ Error adding column $column: " . mysqli_error($conn));
            }
        } else {
            log_msg("✓ Column $column already exists in $table.");
        }
    } catch (Exception $e) {
        log_msg("✗ Error checking $table.$column: " . $e->getMessage());
    }
}

// Update users table
log_msg("Updating 'users' table...");
addColumnIfMissing($conn, 'users', 'phone', 'VARCHAR(20) NULL AFTER last_name');

log_msg("<strong>Migration complete.</strong>");
?>
<p><a href="users.php">Go to Users Management</a></p>
