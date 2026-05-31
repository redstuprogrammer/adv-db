<?php
// Run this script from the command line (php 2026_06_01_add_provider_token_to_payment_methods.php)
// It will add the provider_token column to payment_methods if it doesn't already exist.

require_once __DIR__ . '/../includes/connect.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    echo "Database connection failed: " . ($conn->connect_error ?? "unknown") . "\n";
    exit(1);
}

// Check if table exists
$res = $conn->query("SHOW TABLES LIKE 'payment_methods'");
if (!$res || $res->num_rows === 0) {
    echo "Table 'payment_methods' does not exist. Please create it or run your schema migrations first.\n";
    exit(1);
}

// Check if column exists
$col = $conn->query("SHOW COLUMNS FROM payment_methods LIKE 'provider_token'");
if ($col && $col->num_rows > 0) {
    echo "Column 'provider_token' already exists in 'payment_methods'. Nothing to do.\n";
    exit(0);
}

$sql = "ALTER TABLE payment_methods ADD COLUMN provider_token VARCHAR(255) NULL AFTER brand";
if ($conn->query($sql) === TRUE) {
    echo "Added column 'provider_token' to 'payment_methods'.\n";
    // Add an index for faster lookups
    $idxSql = "ALTER TABLE payment_methods ADD INDEX idx_provider_token (provider_token(50))";
    if ($conn->query($idxSql) === TRUE) {
        echo "Added index 'idx_provider_token'.\n";
    } else {
        echo "Warning: could not add index idx_provider_token: " . $conn->error . "\n";
    }
    exit(0);
} else {
    echo "Failed to add column provider_token: " . $conn->error . "\n";
    exit(1);
}
