<?php
require_once __DIR__ . '/../includes/connect.php';

$tenantId = 1; // Change to a valid tenant ID for testing
$period = 'all';
$dateFilter = '';
$sqlDateCol = 'py.billing_date';
$filter = '';

$query = "(SELECT py.billing_id AS payment_id,
                  py.amount_paid AS amount,
                  py.payment_status AS status,
                  py.billing_date,
                  py.billing_date AS payment_date,
                  p.first_name, p.last_name,
                  NULL AS payment_type,
                  'web' AS source
           FROM billing py
           LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
           LEFT JOIN patient     p ON a.patient_id      = p.patient_id
           WHERE py.tenant_id = ? AND py.payment_status IN ('paid', 'partial') $filter)
           
           UNION ALL
           
           (SELECT r.payment_id,
                  r.amount,
                  r.status,
                  r.payment_date AS billing_date,
                  r.payment_date,
                  p.first_name, p.last_name,
                  r.payment_type,
                  'mobile' AS source
           FROM payment r
           LEFT JOIN appointment a ON r.appointment_id = a.appointment_id
           LEFT JOIN patient     p ON a.patient_id      = p.patient_id
           WHERE r.tenant_id = ? AND r.status = 'paid' AND r.appointment_id IS NOT NULL 
           AND (r.payment_type = 'deposit' OR r.payment_type = 'downpayment') " 
           . str_replace('py.billing_date', 'r.payment_date', $filter) . ")
           
           ORDER BY billing_date DESC";

echo "Query: \n" . $query . "\n\n";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo "Prepare failed: " . $conn->error . "\n";
} else {
    echo "Prepare successful.\n";
    $stmt->bind_param('ii', $tenantId, $tenantId);
    if ($stmt->execute()) {
        echo "Execute successful.\n";
        $result = $stmt->get_result();
        echo "Rows: " . $result->num_rows . "\n";
    } else {
        echo "Execute failed: " . $stmt->error . "\n";
    }
}
?>
