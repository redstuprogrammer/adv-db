<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../includes/connect.php';

$sql = "ALTER TABLE users 
        ADD COLUMN password_reset_token VARCHAR(255) DEFAULT NULL, 
        ADD COLUMN password_reset_expires DATETIME DEFAULT NULL,
        ADD INDEX idx_users_reset_token (password_reset_token)";

if (mysqli_query($conn, $sql)) {
    echo "SUCCESS: Added reset columns to users table.\n";
} else {
    echo "ERROR: " . mysqli_error($conn) . "\n";
}
?>
