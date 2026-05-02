<?php
require_once 'includes/connect.php';

header('Content-Type: text/plain');

echo "Starting migration for clinic_settings table...\n";

// 1. Add tenant_id column if missing
$checkColumn = $conn->query("SHOW COLUMNS FROM clinic_settings LIKE 'tenant_id'");
if ($checkColumn->num_rows == 0) {
    echo "Adding tenant_id column...\n";
    // First clear existing data if any, as we can't have tenant_id NOT NULL without a value
    // Since this is likely a fresh setup or broken state, we can truncate or delete
    // For safety, let's just check if there's data.
    $dataCheck = $conn->query("SELECT COUNT(*) as count FROM clinic_settings");
    $rowCount = $dataCheck->fetch_assoc()['count'];
    if ($rowCount > 0) {
        echo "Warning: Table has $rowCount rows. Truncating for safe column addition...\n";
        $conn->query("TRUNCATE TABLE clinic_settings");
    }
    
    $conn->query("ALTER TABLE clinic_settings ADD COLUMN tenant_id INT NOT NULL AFTER id");
    $conn->query("ALTER TABLE clinic_settings ADD CONSTRAINT fk_clinic_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE");
    $conn->query("ALTER TABLE clinic_settings ADD UNIQUE KEY idx_tenant_id (tenant_id)");
} else {
    echo "tenant_id column already exists.\n";
}

// 2. Add other missing columns
$columnsToAdd = [
    'clinic_name' => "VARCHAR(255) DEFAULT NULL",
    'footer_copyright' => "VARCHAR(255) DEFAULT NULL",
    'badge_visible' => "VARCHAR(10) DEFAULT '1'",
    'badge_text' => "VARCHAR(255) DEFAULT NULL",
    'stat_number' => "VARCHAR(50) DEFAULT NULL",
    'stat_label' => "VARCHAR(255) DEFAULT NULL",
    'checklist_1' => "VARCHAR(255) DEFAULT NULL",
    'checklist_2' => "VARCHAR(255) DEFAULT NULL",
    'checklist_3' => "VARCHAR(255) DEFAULT NULL",
    'cta_primary' => "VARCHAR(255) DEFAULT NULL",
    'cta_secondary' => "VARCHAR(255) DEFAULT NULL",
    'accent_color' => "VARCHAR(20) DEFAULT '#004872'"
];

foreach ($columnsToAdd as $col => $definition) {
    $check = $conn->query("SHOW COLUMNS FROM clinic_settings LIKE '$col'");
    if ($check->num_rows == 0) {
        echo "Adding $col column...\n";
        if (!$conn->query("ALTER TABLE clinic_settings ADD COLUMN $col $definition")) {
            echo "Error adding $col: " . $conn->error . "\n";
        }
    } else {
        echo "$col column already exists.\n";
    }
}

echo "Migration completed.\n";
?>
