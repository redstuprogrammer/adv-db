<?php
/**
 * Migration: Add 'archived' to tenants.status ENUM
 * Fixes: mysqli_sql_exception "Data truncated for column 'status' at row 1"
 *        in superadmin_review_documents.php line 154
 *
 * Run once via CLI:  php migrate_tenants_status_add_archived.php
 * Or load in browser (superadmin only, then delete the file).
 *
 * Safe to run multiple times — checks if 'archived' already exists first.
 */

define('ROOT_PATH', __DIR__ . '/');
require_once ROOT_PATH . 'includes/connect.php';  // provides $conn (mysqli)
/** @var mysqli $conn */

// ─── 1. Inspect current ENUM definition ──────────────────────────────────────
$result = $conn->query("SHOW COLUMNS FROM `tenants` LIKE 'status'");
if (!$result) {
    exit("ERROR: Could not query tenants table — " . $conn->error . "\n");
}

$column = $result->fetch_assoc();
if (!$column) {
    exit("ERROR: Column 'status' not found in table 'tenants'.\n");
}

$columnType = $column['Type']; // e.g. enum('active','inactive','suspended')
echo "Current column type: $columnType\n";

// ─── 2. Parse existing ENUM values ───────────────────────────────────────────
preg_match("/^enum\((.+)\)$/i", $columnType, $matches);
if (empty($matches[1])) {
    exit("ERROR: Could not parse ENUM values from: $columnType\n");
}

$rawValues   = $matches[1]; // 'active','inactive','suspended'
$enumValues  = array_map(
    fn($v) => trim($v, "'"),
    explode(',', $rawValues)
);

echo "Existing ENUM values: " . implode(', ', $enumValues) . "\n";

// ─── 3. Check if 'archived' already exists ───────────────────────────────────
if (in_array('archived', $enumValues, true)) {
    echo "✔ 'archived' already present in ENUM — no migration needed.\n";
    exit(0);
}

// ─── 4. Build new ENUM definition including 'archived' ───────────────────────
$enumValues[] = 'archived';
$newEnumList  = implode(
    ',',
    array_map(fn($v) => "'" . $conn->real_escape_string($v) . "'", $enumValues)
);

$defaultValue = $column['Default'] ?? 'active';
$nullClause   = ($column['Null'] === 'YES') ? 'NULL' : 'NOT NULL';

$sql = "ALTER TABLE `tenants`
        MODIFY COLUMN `status` ENUM($newEnumList) $nullClause DEFAULT '$defaultValue'";

echo "Running: $sql\n";

// ─── 5. Execute ──────────────────────────────────────────────────────────────
if ($conn->query($sql) === true) {
    echo "✔ Migration successful — 'archived' added to tenants.status ENUM.\n";
} else {
    exit("ERROR: ALTER TABLE failed — " . $conn->error . "\n");
}
