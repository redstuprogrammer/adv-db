<?php
/**
 * Migration: Add status column to patient table
 * This migration adds a status column to track patient account status
 */
require_once __DIR__ . '/includes/connect.php';

$migrations = [
    "ALTER TABLE patient ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' NOT NULL"
];

echo "Running patient status migration...\n";
echo "=================================\n\n";

$errors = [];
$executed = 0;

foreach ($migrations as $sql) {
    echo "Executing: " . substr($sql, 0, 80) . "...\n";
    
    if (mysqli_query($conn, $sql)) {
        $executed++;
        echo "✓ Success\n\n";
    } else {
        $error = mysqli_error($conn);
        // Check if column already exists
        if (strpos($error, 'Duplicate column name') !== false) {
            echo "✓ Column already exists (skipped)\n\n";
            $executed++;
        } else {
            $errors[] = [
                'statement' => substr($sql, 0, 80),
                'error' => $error
            ];
            echo "✗ Error: " . $error . "\n\n";
        }
    }
}

echo "=== Migration Summary ===\n";
echo "Executed: " . $executed . " statements\n";
echo "Errors: " . count($errors) . "\n";

if ($errors) {
    echo "\nErrors encountered:\n";
    foreach ($errors as $err) {
        echo "- " . $err['error'] . "\n";
        echo "  " . $err['statement'] . "\n";
    }
} else {
    echo "\n✓ Migration completed successfully!\n";
}

mysqli_close($conn);
?>
