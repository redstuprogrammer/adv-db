<?php
/**
 * Run Payment Columns Migration
 */
require_once __DIR__ . '/includes/connect.php';

$migrationFile = __DIR__ . '/add_payment_columns_migration.sql';

if (!file_exists($migrationFile)) {
    die("Migration file not found: " . $migrationFile);
}

$sql = file_get_contents($migrationFile);
$statements = array_filter(array_map('trim', preg_split('/;+/', $sql)));

$errors = [];
$executed = 0;

foreach ($statements as $statement) {
    if (empty($statement)) {
        continue;
    }

    if (mysqli_query($conn, $statement)) {
        $executed++;
        echo "✓ Executed: " . substr($statement, 0, 60) . "...\n";
    } else {
        $error = mysqli_error($conn);
        $errors[] = [
            'statement' => substr($statement, 0, 80),
            'error' => $error
        ];
        echo "✗ Error: " . $error . "\n";
        echo "  Statement: " . substr($statement, 0, 80) . "...\n";
    }
}

echo "\n=== Migration Summary ===\n";
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