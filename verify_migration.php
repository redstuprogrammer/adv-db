<?php
/**
 * Verify subscription migration tables and row counts.
 * Usage:
 *   php verify_migration.php
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$connectPaths = [
    __DIR__ . '/includes/connect.php',
    __DIR__ . '/../includes/connect.php',
    __DIR__ . '/adv db/includes/connect.php',
];

$connected = false;
foreach ($connectPaths as $connectPath) {
    if (file_exists($connectPath)) {
        require_once $connectPath;
        $connected = true;
        break;
    }
}

if (!$connected || !isset($conn) || !$conn) {
    fwrite(STDERR, "Database connection is unavailable.\n");
    exit(1);
}

function runSingleRowQuery(mysqli $conn, string $sql, array $params = []): array {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : [];
    $stmt->close();
    return $row ?: [];
}

$tables = ['subscriptions', 'payment_methods'];
$report = [];

foreach ($tables as $table) {
    $existsRow = runSingleRowQuery(
        $conn,
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$table]
    );
    $exists = (int)($existsRow['c'] ?? 0) > 0;

    $count = null;
    if ($exists) {
        $countRow = runSingleRowQuery($conn, "SELECT COUNT(*) AS c FROM `$table`");
        $count = (int)($countRow['c'] ?? 0);
    }

    $report[$table] = [
        'exists' => $exists,
        'row_count' => $count,
    ];
}

$output = [
    'database' => $conn->server_info . ' / ' . mysqli_get_host_info($conn),
    'migration_verification' => $report,
];

if (php_sapi_name() === 'cli') {
    echo "Migration verification results:\n";
    foreach ($report as $table => $data) {
        echo "- $table: " . ($data['exists'] ? 'exists' : 'missing');
        if ($data['exists']) {
            echo " (rows: {$data['row_count']})";
        }
        echo "\n";
    }
} else {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Migration Verify</title></head><body style="font-family:sans-serif;padding:24px;">';
    echo '<h1>Migration Verify</h1>';
    echo '<ul>';
    foreach ($report as $table => $data) {
        echo '<li><strong>' . htmlspecialchars($table, ENT_QUOTES, 'UTF-8') . ':</strong> ';
        echo $data['exists'] ? 'exists' : 'missing';
        if ($data['exists']) {
            echo ' (rows: ' . htmlspecialchars((string)$data['row_count'], ENT_QUOTES, 'UTF-8') . ')';
        }
        echo '</li>';
    }
    echo '</ul>';
    echo '</body></html>';
}