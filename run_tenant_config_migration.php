<?php
/**
 * Migration: Tenant Configs Login Customization
 * Ensures tenant_configs table has all required columns for login customization
 * and creates upload directories for tenants
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__ . '/../../');
}

require_once ROOT_PATH . 'includes/connect.php';

error_log("Starting tenant_configs login customization migration...");

try {
    // Create or update tenant_configs table
    $migration_sql = [
        // Create table if not exists
        "CREATE TABLE IF NOT EXISTS `tenant_configs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tenant_id` INT NOT NULL UNIQUE,
            `brand_bg_color` VARCHAR(7) DEFAULT '#001f3f',
            `brand_text_color` VARCHAR(7) DEFAULT '#ffffff',
            `primary_btn_color` VARCHAR(7) DEFAULT '#22c55e',
            `link_color` VARCHAR(7) DEFAULT '#2563eb',
            `login_title` VARCHAR(255) DEFAULT 'Clinic Login',
            `login_description` TEXT,
            `brand_subtitle` VARCHAR(255) DEFAULT 'Powered by OralSync',
            `brand_logo_path` VARCHAR(500),
            `brand_bg_image_path` VARCHAR(500),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE,
            INDEX idx_tenant_id (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Add columns if they don't exist
        "ALTER TABLE `tenant_configs` ADD COLUMN IF NOT EXISTS `brand_text_color` VARCHAR(7) DEFAULT '#ffffff'",
        "ALTER TABLE `tenant_configs` ADD COLUMN IF NOT EXISTS `login_description` TEXT",
        "ALTER TABLE `tenant_configs` ADD COLUMN IF NOT EXISTS `brand_logo_path` VARCHAR(500)",
        "ALTER TABLE `tenant_configs` ADD COLUMN IF NOT EXISTS `brand_bg_image_path` VARCHAR(500)",
    ];

    foreach ($migration_sql as $sql) {
        if (!$conn->query($sql)) {
            error_log("Migration SQL error: " . $conn->error);
            echo "Error: " . $conn->error . "\n";
        } else {
            error_log("Migration step completed: " . substr($sql, 0, 60) . "...");
        }
    }

    // Create upload directories for existing tenants
    $upload_base = ROOT_PATH . 'assets/uploads/tenants/';
    if (!is_dir($upload_base)) {
        mkdir($upload_base, 0755, true);
        error_log("Created base upload directory: " . $upload_base);
    }

    // Fetch all active tenants and create their upload directories
    $result = $conn->query("SELECT tenant_id FROM tenants WHERE status = 'active' LIMIT 100");
    if ($result) {
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $tenant_id = (int)$row['tenant_id'];
            $tenant_upload_dir = $upload_base . $tenant_id . '/';
            if (!is_dir($tenant_upload_dir)) {
                mkdir($tenant_upload_dir, 0755, true);
                $count++;
            }
        }
        error_log("Created or verified upload directories for $count tenants");
    }

    error_log("Migration completed successfully!");
    echo "✓ Migration completed successfully\n";

} catch (Exception $e) {
    error_log("Migration failed: " . $e->getMessage());
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
}
?>
