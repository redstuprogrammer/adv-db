<?php
/**
 * db_migrate_password_reset.php
 * One-off migration script to ensure database schema supports password resets.
 */
require_once __DIR__ . '/includes/connect.php';

echo "<h2>Database Migration: Password Reset Support</h2>";

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

// 1. Update tenants table
log_msg("Updating 'tenants' table...");
addColumnIfMissing($conn, 'tenants', 'password_reset_token', 'VARCHAR(255) NULL');
addColumnIfMissing($conn, 'tenants', 'password_reset_expires', 'DATETIME NULL');

// 2. Update users table
log_msg("Updating 'users' table...");
addColumnIfMissing($conn, 'users', 'password_reset_token', 'VARCHAR(255) NULL');
addColumnIfMissing($conn, 'users', 'password_reset_expires', 'DATETIME NULL');

// 3. Create password_resets table
log_msg("Ensuring 'password_resets' table exists...");
$createTableSql = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (email),
    INDEX (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $createTableSql)) {
    log_msg("✓ password_resets table ensured.");
} else {
    log_msg("✗ Error creating password_resets table: " . mysqli_error($conn));
}

log_msg("<strong>Migration complete.</strong>");
?>
<p><a href="forgot_password_tenant.php">Go to Forgot Password Page</a></p>
