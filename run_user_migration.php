<?php
// Run migration for users table
require_once 'connect.php';

$migrations = [
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `first_name` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_name` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
];

$success = true;
$output = [];

foreach ($migrations as $migration) {
    if (mysqli_query($conn, $migration)) {
        $output[] = "✓ Migration executed: " . substr($migration, 0, 50) . "...";
    } else {
        $output[] = "✗ Migration failed: " . mysqli_error($conn);
        $success = false;
    }
}

if ($success) {
    $output[] = "\n✓ All migrations completed successfully!";
} else {
    $output[] = "\n✗ Some migrations failed.";
}

// Log the results
error_log("User table migration: " . implode("\n", $output));

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['success' => $success, 'messages' => $output]);
?>
