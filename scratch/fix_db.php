<?php
require_once dirname(__DIR__) . '/includes/connect.php';

header('Content-Type: text/plain');

function checkAndFix($conn) {
    echo "--- Database Check & Repair ---\n";
    
    // 1. Fix Payment Table Name
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'payment'");
    $payment_exists = mysqli_num_rows($result) > 0;
    
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'subscription_payment'");
    $sub_payment_exists = mysqli_num_rows($result) > 0;
    
    if (!$payment_exists && $sub_payment_exists) {
        echo "[PAYMENT] Renaming subscription_payment to payment...\n";
        if (mysqli_query($conn, "RENAME TABLE subscription_payment TO payment")) {
            echo "[PAYMENT] SUCCESS: Table renamed.\n";
        } else {
            echo "[PAYMENT] ERROR: " . mysqli_error($conn) . "\n";
        }
    } elseif ($payment_exists) {
        echo "[PAYMENT] OK: payment table exists.\n";
    } else {
        echo "[PAYMENT] ERROR: payment table missing and subscription_payment not found.\n";
    }
    
    // 2. Fix Tenants Table Columns
    $result = mysqli_query($conn, "SHOW COLUMNS FROM tenants LIKE 'homepage_url'");
    if (mysqli_num_rows($result) == 0) {
        echo "[TENANTS] Adding homepage_url column...\n";
        if (mysqli_query($conn, "ALTER TABLE tenants ADD COLUMN homepage_url VARCHAR(255) DEFAULT NULL AFTER subdomain_slug")) {
            echo "[TENANTS] SUCCESS: homepage_url added.\n";
        } else {
            echo "[TENANTS] ERROR: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "[TENANTS] OK: homepage_url exists.\n";
    }
    
    // 3. Fix tenant_configs table (if needed)
    // The dump shows tenant_configs has many columns, let's check one
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'tenant_configs'");
    if (mysqli_num_rows($result) > 0) {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM tenant_configs LIKE 'config_key'");
        if (mysqli_num_rows($result) == 0) {
            // Wait, the dump shows a DIFFERENT structure for tenant_configs
            // Dump: config_id, tenant_id, brand_logo_path, ...
            // tenant_utils.php: tenant_id, config_key, config_value (Polymorphic)
            echo "[TENANT_CONFIGS] Structure mismatch detected.\n";
            // I should check what's actually there
        }
    }
}

checkAndFix($conn);
