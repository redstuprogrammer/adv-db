<?php
/**
 * Migration: Update existing deposit records
 * This script identifies billing records with amounts of 200 or 500
 * and updates their payment_type to 'deposit' (Downpayment).
 */

require_once __DIR__ . '/includes/connect.php';

echo "Starting migration...\n";

// Use amount_paid to identify the downpayments
$query = "UPDATE billing 
          SET payment_type = 'deposit' 
          WHERE amount_paid IN (200.00, 500.00) 
          AND (payment_type IS NULL OR payment_type = 'full')";

if ($conn->query($query)) {
    $affectedRows = $conn->affected_rows;
    echo "Billing Table: Updated $affectedRows records to 'deposit' type.\n";
} else {
    echo "Billing Table update failed: " . $conn->error . "\n";
}

// Also update the payment table
$queryPayment = "UPDATE payment 
                 SET payment_type = 'deposit' 
                 WHERE amount IN (200.00, 500.00) 
                 AND (payment_type IS NULL OR payment_type = 'full')";

if ($conn->query($queryPayment)) {
    $affectedRows = $conn->affected_rows;
    echo "Payment Table: Updated $affectedRows records to 'deposit' type.\n";
} else {
    echo "Payment Table update failed: " . $conn->error . "\n";
}

$conn->close();
?>
