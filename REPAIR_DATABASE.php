<?php
/**
 * REPAIR_DATABASE.php
 * Use this script to fix database schema inconsistencies.
 * 
 * Fixes:
 * 1. Renames 'subscription_payment' to 'payment' (Required for dashboard and billing).
 * 2. Adds 'homepage_url' to 'tenants' table (Required for clinic registration).
 * 3. Migrates 'tenant_configs' to polymorphic structure (Required for dynamic settings).
 */

require_once __DIR__ . '/includes/connect.php';

header('Content-Type: text/plain');

echo "Starting Database Repair...\n";

// --- 1. Fix Payment Table ---
$result = mysqli_query($conn, "SHOW TABLES LIKE 'payment'");
if (mysqli_num_rows($result) == 0) {
    $resultSub = mysqli_query($conn, "SHOW TABLES LIKE 'subscription_payment'");
    if (mysqli_num_rows($resultSub) > 0) {
        echo "Renaming 'subscription_payment' to 'payment'...\n";
        if (mysqli_query($conn, "RENAME TABLE subscription_payment TO payment")) {
            echo "SUCCESS: 'payment' table is now available.\n";
        } else {
            echo "ERROR: Failed to rename table: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "WARNING: Neither 'payment' nor 'subscription_payment' found. You may need to import the schema.\n";
    }
} else {
    echo "OK: 'payment' table already exists.\n";
}

// --- 2. Fix Tenants Table ---
$result = mysqli_query($conn, "SHOW COLUMNS FROM tenants LIKE 'homepage_url'");
if (mysqli_num_rows($result) == 0) {
    echo "Adding 'homepage_url' to 'tenants' table...\n";
    if (mysqli_query($conn, "ALTER TABLE tenants ADD COLUMN homepage_url VARCHAR(255) DEFAULT NULL AFTER subdomain_slug")) {
        echo "SUCCESS: 'homepage_url' column added.\n";
    } else {
        echo "ERROR: Failed to add column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "OK: 'homepage_url' column exists.\n";
}

// --- 3. Migrate Tenant Configs ---
$result = mysqli_query($conn, "SHOW COLUMNS FROM tenant_configs LIKE 'config_key'");
if (mysqli_num_rows($result) == 0) {
    echo "Migrating 'tenant_configs' to polymorphic structure...\n";
    
    // Create new table
    $createSql = "CREATE TABLE IF NOT EXISTS `tenant_configs_new` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` INT NOT NULL,
        `config_key` VARCHAR(100) NOT NULL,
        `config_value` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_tenant_key` (`tenant_id`, `config_key`)
    )";
    
    if (mysqli_query($conn, $createSql)) {
        // Migrate existing data
        $oldConfigs = mysqli_query($conn, "SELECT * FROM tenant_configs");
        $colsResult = mysqli_query($conn, "SHOW COLUMNS FROM tenant_configs");
        $columns = [];
        while($c = mysqli_fetch_assoc($colsResult)) {
            $columns[] = $c['Field'];
        }
        
        while ($row = mysqli_fetch_assoc($oldConfigs)) {
            $tid = $row['tenant_id'];
            foreach ($row as $key => $val) {
                if (in_array($key, ['config_id', 'tenant_id', 'created_at', 'updated_at'])) continue;
                if ($val !== null) {
                    $valSafe = mysqli_real_escape_string($conn, $val);
                    mysqli_query($conn, "INSERT IGNORE INTO tenant_configs_new (tenant_id, config_key, config_value) VALUES ($tid, '$key', '$valSafe')");
                }
            }
        }
        
        // Switch tables
        mysqli_query($conn, "RENAME TABLE tenant_configs TO tenant_configs_old, tenant_configs_new TO tenant_configs");
        echo "SUCCESS: 'tenant_configs' migrated to polymorphic structure.\n";
    } else {
        echo "ERROR: Failed to create migration table: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "OK: 'tenant_configs' is already polymorphic.\n";
}

echo "\nDatabase repair completed.\n";
