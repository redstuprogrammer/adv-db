<?php
/**
 * Run Payment / Billing migration
 *
 * This script ensures the payment table has the new billing/payment fields,
 * and it imports payment-able rows from the old billing table when present.
 */
require_once __DIR__ . '/includes/connect.php';

function tableExists($conn, string $tableName): bool {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $tableName) . "'");
    return $result && mysqli_num_rows($result) > 0;
}

function columnExists($conn, string $tableName, string $columnName): bool {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `" . mysqli_real_escape_string($conn, $tableName) . "` LIKE '" . mysqli_real_escape_string($conn, $columnName) . "'");
    return $result && mysqli_num_rows($result) > 0;
}

if (!tableExists($conn, 'payment')) {
    die("Error: payment table does not exist.\n");
}

$alterStatements = [];
if (!columnExists($conn, 'payment', 'procedures_json')) {
    $alterStatements[] = "ALTER TABLE payment ADD COLUMN procedures_json TEXT AFTER status";
}
if (!columnExists($conn, 'payment', 'source')) {
    $alterStatements[] = "ALTER TABLE payment ADD COLUMN source ENUM('web','mobile') DEFAULT 'web' AFTER procedures_json";
}
if (!columnExists($conn, 'payment', 'reference_number')) {
    $alterStatements[] = "ALTER TABLE payment ADD COLUMN reference_number VARCHAR(255) AFTER source";
}

foreach ($alterStatements as $stmt) {
    if (mysqli_query($conn, $stmt)) {
        echo "✓ Executed: $stmt\n";
    } else {
        echo "✗ Failed: $stmt\n";
        echo "  " . mysqli_error($conn) . "\n";
    }
}

if (!columnExists($conn, 'payment', 'source')) {
    echo "Warning: payment.source column is still missing after migration.\n";
}

if (!tableExists($conn, 'billing')) {
    echo "No billing table found; skipping billing import.\n";
    exit(0);
}

$importSql = "INSERT INTO payment (tenant_id, appointment_id, amount, mode, status, procedures_json, source, reference_number, payment_date)\n"
    . "SELECT tenant_id, appointment_id, COALESCE(amount_paid, total_amount) AS amount, 'Cash', payment_status, NULL, 'web', CONCAT('BILL-', billing_id), billing_date\n"
    . "FROM billing b\n"
    . "WHERE COALESCE(amount_paid, 0) > 0\n"
    . "  AND NOT EXISTS (SELECT 1 FROM payment p WHERE p.reference_number = CONCAT('BILL-', b.billing_id))";

if (mysqli_query($conn, $importSql)) {
    echo "✓ Imported billing rows into payment. Rows affected: " . mysqli_affected_rows($conn) . "\n";
} else {
    echo "✗ Billing import failed: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);
