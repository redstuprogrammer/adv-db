<?php
// ============================================================
// FILE TYPE: API ENDPOINT — deploy to server
// PATH on server: /api/pay_deposit.php
// ============================================================
// POST JSON body:
//   tenant_id       (int, required)
//   appointment_id  (int, required)
//   patient_id      (int, required)   — for reference number generation
//   amount          (float, required)
//   mode            (string, required) — 'Cash' | 'Card' | 'Gcash'
//
// What this does:
//   1. Verifies appointment is still in 'pending_payment' status
//   2. Inserts a payment row with payment_type = 'deposit'
//   3. Updates appointment status → 'pending' (now awaiting clinic confirm)
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

$body = json_decode(file_get_contents('php://input'), true);

$tenant_id      = $body['tenant_id']      ?? null;
$appointment_id = $body['appointment_id'] ?? null;
$patient_id     = $body['patient_id']     ?? null;
$amount         = $body['amount']         ?? null;
$mode           = $body['mode']           ?? 'Cash';

if (!$tenant_id || !$appointment_id || !$patient_id || $amount === null) {
    echo json_encode(['success' => false, 'message' => 'tenant_id, appointment_id, patient_id, and amount are required']);
    exit;
}

// Capitalise mode
$mode = ucfirst(strtolower($mode));

// Verify appointment still needs deposit
$chk = $conn->prepare("
    SELECT status FROM appointment
    WHERE appointment_id = ? AND tenant_id = ?
    LIMIT 1
");
$chk->bind_param("ii", $appointment_id, $tenant_id);
$chk->execute();
$appt = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$appt) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    $conn->close(); exit;
}

if ($appt['status'] !== 'pending_payment') {
    echo json_encode([
        'success' => false,
        'message' => $appt['status'] === 'pending'
            ? 'Deposit already paid for this appointment.'
            : 'This appointment is no longer accepting deposits (status: ' . $appt['status'] . ').'
    ]);
    $conn->close(); exit;
}

// Generate reference number: DEP-{patient_id}-{timestamp}
$reference_number = 'DEP-' . $patient_id . '-' . time();

// Insert deposit payment row
$ins = $conn->prepare("
    INSERT INTO payment
        (tenant_id, appointment_id, amount, mode, status, procedures_json, source, reference_number, payment_type)
    VALUES
        (?, ?, ?, ?, 'Paid', '[]', 'mobile', ?, 'deposit')
");
$ins->bind_param("iidss", $tenant_id, $appointment_id, $amount, $mode, $reference_number);

if (!$ins->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to record deposit: ' . $ins->error]);
    $ins->close(); $conn->close(); exit;
}
$payment_id = $conn->insert_id;
$ins->close();

// Move appointment to 'pending' — now visible to clinic for confirmation
$upd = $conn->prepare("
    UPDATE appointment SET status = 'pending'
    WHERE appointment_id = ? AND tenant_id = ?
");
$upd->bind_param("ii", $appointment_id, $tenant_id);
$upd->execute();
$upd->close();

$conn->close();

echo json_encode([
    'success'          => true,
    'message'          => 'Deposit paid successfully. Your appointment is now pending clinic confirmation.',
    'payment_id'       => $payment_id,
    'reference_number' => $reference_number,
]);
?>