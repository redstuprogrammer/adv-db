<?php
// ============================================================
// FILE TYPE: API ENDPOINT
// PATH on server: /api/get_installment_plan.php
// ============================================================
// GET ?plan_id=X&patient_id=Y
//   → returns plan details + all monthly billing rows
//
// GET ?billing_id=X&patient_id=Y
//   → looks up the plan attached to a billing row
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Only GET requests allowed']);
    exit;
}

$patient_id = $_GET['patient_id'] ?? null;
$plan_id    = $_GET['plan_id']    ?? null;
$billing_id = $_GET['billing_id'] ?? null;

if (!$patient_id) {
    echo json_encode(['success' => false, 'message' => 'patient_id is required.']);
    exit;
}

// Resolve plan_id from billing_id if not provided directly
if (!$plan_id && $billing_id) {
    $res = $conn->prepare("SELECT installment_plan_id FROM billing WHERE billing_id = ? AND patient_id = ? LIMIT 1");
    $res->bind_param("ii", $billing_id, $patient_id);
    $res->execute();
    $res->bind_result($plan_id);
    $res->fetch();
    $res->close();
}

if (!$plan_id) {
    echo json_encode(['success' => false, 'message' => 'No installment plan found.']);
    $conn->close(); exit;
}

// Fetch plan
$stmt = $conn->prepare("
    SELECT plan_id, tenant_id, patient_id, billing_id,
           total_amount, monthly_amount, num_months, months_paid, status, created_at
    FROM installment_plan
    WHERE plan_id = ? AND patient_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $plan_id, $patient_id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$plan) {
    echo json_encode(['success' => false, 'message' => 'Installment plan not found.']);
    $conn->close(); exit;
}

// Fetch all monthly billing rows for this plan
$rows_stmt = $conn->prepare("
    SELECT billing_id, total_amount, amount_paid, payment_status, billing_date, reference_number, mode
    FROM billing
    WHERE installment_plan_id = ? AND patient_id = ?
    ORDER BY billing_date ASC
");
$rows_stmt->bind_param("ii", $plan_id, $patient_id);
$rows_stmt->execute();
$result = $rows_stmt->get_result();

$monthly_bills = [];
$month_num     = 1;
while ($row = $result->fetch_assoc()) {
    $row['month_number'] = $month_num++;
    $monthly_bills[]     = $row;
}
$rows_stmt->close();
$conn->close();

echo json_encode([
    'success'       => true,
    'plan'          => $plan,
    'monthly_bills' => $monthly_bills,
]);
?>