<?php
// ============================================================
// FILE TYPE: API ENDPOINT
// PATH on server: /api/create_installment_plan.php
// ============================================================
// POST JSON body:
//   billing_id  (int,   required) — original billing row
//   tenant_id   (int,   required)
//   patient_id  (int,   required)
//   num_months  (int,   required) — 3 | 6 | 12
//
// What it does:
//   1. Checks patient is ID-verified
//   2. Creates installment_plan row
//   3. Auto-generates num_months billing rows (monthly installments)
//      Each due 30 days apart from today, marked is_installment = 1
//   4. Marks original billing row as 'installment_active'
//
// Returns:
//   { success, plan_id, monthly_amount, num_months, billing_rows[] }
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true);
$billing_id = $body['billing_id'] ?? null;
$tenant_id  = $body['tenant_id']  ?? null;
$patient_id = $body['patient_id'] ?? null;
$num_months = (int) ($body['num_months'] ?? 0);

if (!$billing_id || !$tenant_id || !$patient_id || !in_array($num_months, [3, 6, 12])) {
    echo json_encode(['success' => false, 'message' => 'billing_id, tenant_id, patient_id, and num_months (3|6|12) are required.']);
    exit;
}

// ─── 1. Check patient ID is verified ──────────────────────
$pat = $conn->prepare("SELECT id_verified FROM patient WHERE patient_id = ? LIMIT 1");
$pat->bind_param("i", $patient_id);
$pat->execute();
$patient = $pat->get_result()->fetch_assoc();
$pat->close();

if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'Patient not found.']);
    $conn->close(); exit;
}

if ($patient['id_verified'] !== 'verified') {
    $status = $patient['id_verified'];
    $msg = match($status) {
        'pending'  => 'Your ID is still being verified by the clinic. Please wait for approval before setting up an installment plan.',
        'rejected' => 'Your ID verification was rejected. Please re-upload a valid ID to proceed.',
        default    => 'You need to upload and verify your ID before setting up an installment plan.',
    };
    echo json_encode(['success' => false, 'message' => $msg, 'id_status' => $status]);
    $conn->close(); exit;
}

// ─── 2. Fetch original billing row ────────────────────────
$chk = $conn->prepare("
    SELECT total_amount, payment_status, appointment_id, service_id
    FROM billing
    WHERE billing_id = ? AND tenant_id = ? AND patient_id = ?
    LIMIT 1
");
$chk->bind_param("iii", $billing_id, $tenant_id, $patient_id);
$chk->execute();
$bill = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$bill) {
    echo json_encode(['success' => false, 'message' => 'Billing record not found.']);
    $conn->close(); exit;
}

if ($bill['payment_status'] === 'paid') {
    echo json_encode(['success' => false, 'message' => 'This bill is already fully paid.']);
    $conn->close(); exit;
}

// ─── 3. Calculate monthly amount ─────────────────────────
$total          = (float) $bill['total_amount'];
$monthly_raw    = $total / $num_months;
// Round up centavos to avoid rounding shortfall
$monthly_amount = ceil($monthly_raw * 100) / 100;
// Last month absorbs any rounding difference
$last_amount    = round($total - ($monthly_amount * ($num_months - 1)), 2);

// ─── 4. Create installment_plan row ──────────────────────
$ins_plan = $conn->prepare("
    INSERT INTO installment_plan
        (tenant_id, patient_id, billing_id, total_amount, monthly_amount, num_months, status)
    VALUES (?, ?, ?, ?, ?, ?, 'active')
");
$ins_plan->bind_param("iiiddi", $tenant_id, $patient_id, $billing_id, $total, $monthly_amount, $num_months);

if (!$ins_plan->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to create plan: ' . $ins_plan->error]);
    $ins_plan->close(); $conn->close(); exit;
}
$plan_id = $conn->insert_id;
$ins_plan->close();

// ─── 5. Auto-generate monthly billing rows ───────────────
$billing_rows  = [];
$appointment_id = $bill['appointment_id'];
$service_id     = $bill['service_id'];

for ($i = 1; $i <= $num_months; $i++) {
    $due_date   = date('Y-m-d', strtotime("+{$i} months"));
    $month_amt  = ($i === $num_months) ? $last_amount : $monthly_amount;

    $ins_bill = $conn->prepare("
        INSERT INTO billing
            (tenant_id, appointment_id, patient_id, service_id,
             total_amount, amount_paid, payment_status,
             billing_date, is_installment, installment_plan_id)
        VALUES (?, ?, ?, ?, ?, 0.00, 'unpaid', ?, 1, ?)
    ");
    $ins_bill->bind_param("iiiidsi", $tenant_id, $appointment_id, $patient_id, $service_id, $month_amt, $due_date, $plan_id);

    if (!$ins_bill->execute()) {
        // Best effort — log and continue
        error_log("Installment row {$i} failed: " . $ins_bill->error);
    } else {
        $billing_rows[] = [
            'month'        => $i,
            'billing_id'   => $conn->insert_id,
            'amount'       => $month_amt,
            'due_date'     => $due_date,
        ];
    }
    $ins_bill->close();
}

// ─── 6. Mark original bill as covered by installment plan ─
$upd = $conn->prepare("
    UPDATE billing
    SET payment_status = 'installment_active', installment_plan_id = ?
    WHERE billing_id = ? AND tenant_id = ?
");
$upd->bind_param("iii", $plan_id, $billing_id, $tenant_id);
$upd->execute();
$upd->close();

$conn->close();

echo json_encode([
    'success'        => true,
    'message'        => "Installment plan created. {$num_months} monthly payments of " . number_format($monthly_amount, 2) . " PHP.",
    'plan_id'        => $plan_id,
    'total_amount'   => $total,
    'monthly_amount' => $monthly_amount,
    'num_months'     => $num_months,
    'billing_rows'   => $billing_rows,
]);
?>