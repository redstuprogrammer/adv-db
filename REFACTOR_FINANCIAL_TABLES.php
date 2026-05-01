<?php
/**
 * REFACTOR_FINANCIAL_TABLES_V2.php
 * 
 * Objectives:
 * 1. Prepare 'billing' table for full patient payment tracking.
 * 2. Migrate data from current dental 'payment' to 'billing'.
 * 3. Rename 'tenant_subscription_revenue' to 'payment' (Platform revenue).
 * 4. Add missing gateway columns to the new 'payment' table.
 */

require_once __DIR__ . '/includes/connect.php';

header('Content-Type: text/plain');
echo "Starting Financial Schema Refactor V2...\n";

// --- STEP 1: Enhance 'billing' table (Patient Revenue) ---
echo "Enhancing 'billing' table schema...\n";

$billingCols = [
    'mode' => "VARCHAR(50) DEFAULT 'Cash'",
    'procedures_json' => "TEXT",
    'source' => "VARCHAR(50) DEFAULT 'web'",
    'reference_number' => "VARCHAR(100) DEFAULT NULL",
    'payment_type' => "ENUM('full', 'deposit') DEFAULT 'full'",
    'paymongo_session_id' => "VARCHAR(255) DEFAULT NULL",
    'paymongo_payment_id' => "VARCHAR(255) DEFAULT NULL"
];

foreach ($billingCols as $col => $definition) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM billing LIKE '$col'");
    if (mysqli_num_rows($check) == 0) {
        echo "Adding column '$col' to 'billing'...\n";
        mysqli_query($conn, "ALTER TABLE billing ADD COLUMN $col $definition");
    }
}

// --- STEP 2: Migrate data from old dental 'payment' to 'billing' ---
echo "Checking for data to migrate...\n";

$checkPaymentTable = mysqli_query($conn, "SHOW TABLES LIKE 'payment'");
if (mysqli_num_rows($checkPaymentTable) > 0) {
    // Check if it's the dental one (has procedures_json)
    $checkDental = mysqli_query($conn, "SHOW COLUMNS FROM payment LIKE 'procedures_json'");
    if (mysqli_num_rows($checkDental) > 0) {
        echo "Migrating dental records to 'billing'...\n";
        
        $records = mysqli_query($conn, "SELECT * FROM payment");
        while ($row = mysqli_fetch_assoc($records)) {
            $tid = (int)$row['tenant_id'];
            $aid = (int)$row['appointment_id'];
            $pid = 0;
            $appResult = mysqli_query($conn, "SELECT patient_id FROM appointment WHERE appointment_id = $aid");
            if ($appRow = mysqli_fetch_assoc($appResult)) {
                $pid = (int)$appRow['patient_id'];
            }
            
            $amount = (float)$row['amount'];
            $mode = mysqli_real_escape_string($conn, $row['mode'] ?? 'Cash');
            $status = mysqli_real_escape_string($conn, $row['status'] ?? 'pending');
            $json = mysqli_real_escape_string($conn, $row['procedures_json'] ?? '[]');
            $src = mysqli_real_escape_string($conn, $row['source'] ?? 'web');
            $ref = mysqli_real_escape_string($conn, $row['reference_number'] ?? '');
            $type = mysqli_real_escape_string($conn, $row['payment_type'] ?? 'full');
            $pLink = mysqli_real_escape_string($conn, $row['paymongo_link_id'] ?? '');
            $pPay = mysqli_real_escape_string($conn, $row['paymongo_payment_id'] ?? '');
            $date = $row['payment_date'] ?? date('Y-m-d H:i:s');

            $pStatus = (strtolower($status) == 'paid') ? 'paid' : 'unpaid';

            $insertSql = "INSERT INTO billing 
                (tenant_id, appointment_id, patient_id, service_id, total_amount, amount_paid, payment_status, mode, procedures_json, source, reference_number, payment_type, paymongo_session_id, paymongo_payment_id, billing_date)
                VALUES ($tid, $aid, $pid, 0, $amount, $amount, '$pStatus', '$mode', '$json', '$src', '$ref', '$type', '$pLink', '$pPay', '$date')";
            
            mysqli_query($conn, $insertSql);
        }
        
        echo "Renaming old dental 'payment' to 'payment_backup_dental'...\n";
        mysqli_query($conn, "RENAME TABLE payment TO payment_backup_dental");
    }
}

// --- STEP 3: Rename 'tenant_subscription_revenue' to 'payment' (Platform Revenue) ---
echo "Switching 'tenant_subscription_revenue' to 'payment'...\n";

$checkRev = mysqli_query($conn, "SHOW TABLES LIKE 'tenant_subscription_revenue'");
if (mysqli_num_rows($checkRev) > 0) {
    echo "Renaming 'tenant_subscription_revenue' to 'payment'...\n";
    mysqli_query($conn, "RENAME TABLE tenant_subscription_revenue TO payment");
    
    // Optional: Rename revenue_id to payment_id for consistency
    mysqli_query($conn, "ALTER TABLE payment CHANGE revenue_id payment_id INT AUTO_INCREMENT");
}

// --- STEP 4: Enhance the NEW 'payment' table (Subscription Revenue) ---
echo "Adding gateway columns to the new 'payment' table...\n";

$paymentCols = [
    'mode' => "VARCHAR(50) DEFAULT 'Online'",
    'source' => "VARCHAR(50) DEFAULT 'web'",
    'procedures_json' => "TEXT",
    'payment_type' => "VARCHAR(50) DEFAULT 'full'",
    'paymongo_link_id' => "VARCHAR(255) DEFAULT NULL",
    'paymongo_payment_id' => "VARCHAR(255) DEFAULT NULL"
];

foreach ($paymentCols as $col => $definition) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM payment LIKE '$col'");
    if (mysqli_num_rows($check) == 0) {
        echo "Adding column '$col' to 'payment'...\n";
        mysqli_query($conn, "ALTER TABLE payment ADD COLUMN $col $definition");
    }
}

echo "\nRefactor completed.\n";
