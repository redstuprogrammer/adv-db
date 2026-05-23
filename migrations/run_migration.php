<?php
/**
 * Run the SQL migration file in this folder.
 *
 * Usage:
 *   php run_migration.php
 *
 * Or open in browser if running on the local webserver.
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/connect.php';

$sqlFile = __DIR__ . '/20260523_create_subscriptions_and_payment_methods.sql';
if (!file_exists($sqlFile)) {
    http_response_code(500);
    echo "Migration file not found: {$sqlFile}";
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    http_response_code(500);
    echo "Failed to read migration file: {$sqlFile}";
    exit(1);
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo "Database connection is not available.";
    exit(1);
}

$multiQuery = mysqli_multi_query($conn, $sql);
if (!$multiQuery) {
    $message = mysqli_error($conn);
    http_response_code(500);
    echo "Migration failed: {$message}";
    exit(1);
}

$results = [];
$counter = 0;

do {
    $result = mysqli_store_result($conn);
    if ($result instanceof mysqli_result) {
        mysqli_free_result($result);
    }
    $counter++;
    if (!mysqli_more_results($conn)) {
        break;
    }
} while (mysqli_next_result($conn));

if (mysqli_error($conn)) {
    http_response_code(500);
    echo "Migration completed with errors on statement {$counter}: " . mysqli_error($conn);
    exit(1);
}

$output = "Migration executed successfully. Statements processed: {$counter}.";

if (php_sapi_name() === 'cli') {
    echo $output . PHP_EOL;
} else {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Migration Runner</title></head><body style="font-family:sans-serif;padding:24px;">';
    echo '<h1>Migration Runner</h1>';
    echo '<p>' . htmlspecialchars($output, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</body></html>';
}
