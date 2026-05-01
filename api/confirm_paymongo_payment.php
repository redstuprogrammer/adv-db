<?php
// ============================================================
// FILE TYPE: API ENDPOINT — deploy to server
// PATH on server: /api/confirm_paymongo_payment.php
// ============================================================
// Called by mobile AFTER PayMongo redirects to success URL.
// Verifies payment status with PayMongo, updates billing,
// AND flips appointment status pending_payment → pending.
//
// POST JSON body:
//   session_id       (string, required) — from create_paymongo_link
//   billing_id       (int,    required)
//   tenant_id        (int,    required)
//   patient_id       (int,    required)
//   amount_paid      (float,  required) — amount the patient paid
//   mode             (string, optional) — 'Card' | 'Gcash' | 'Paymaya'
//
// Returns:
//   { success, message, billing_id, payment_status, reference_number, appointment_id }
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

$session_id  = $body['session_id']  ?? null;
$billing_id  = $body['billing_id']  ?? null;
$tenant_id   = $body['tenant_id']   ?? null;
$patient_id  = $body['patient_id']  ?? null;
$amount_paid = $body['amount_paid'] ?? null;
$mode        = ucfirst(strtolower($body['mode'] ?? 'Card'));

if (!$session_id || !$billing_id || !$tenant_id || !$patient_id || $amount_paid === null) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// ─── PayMongo secret key (Ensure this is set in your environment variables) ───
$secret = getenv('PAYMONGO_SECRET_KEY') ?: ''; 
if (empty($secret)) {
    // For local development only — DO NOT COMMIT REAL KEYS
    // $secret = 'your_test_key_here';
}
$auth   = base64_encode($secret . ':');

// ─── Verify checkout session status with PayMongo ─────────
$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions/' . urlencode($session_id));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Authorization: Basic ' . $auth,
    ],
]);
$pm_response = curl_exec($ch);
$http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error  = curl_error($ch);
curl_close($ch);

if ($curl_error || !$pm_response) {
    echo json_encode(['success' => false, 'message' => 'Could not verify payment with PayMongo.']);
    exit;
}

$pm_data   = json_decode($pm_response, true);
$pm_status = $pm_data['data']['attributes']['payment_status'] ?? 'unpaid';

if ($pm_status !== 'paid') {
    echo json_encode([
        'success' => false,
        'message' => "Payment not yet confirmed by PayMongo (status: {$pm_status}). Please wait a moment and try again.",
    ]);
    exit;
}

// ─── Fetch current billing row (includes appointment_id) ──
$chk = $conn->prepare("
    SELECT total_amount, amount_paid, payment_status, reference_number, appointment_id
    FROM billing
    WHERE billing_id = ? AND tenant_id = ?
    LIMIT 1
");
$chk->bind_param("ii", $billing_id, $tenant_id);
$chk->execute();
$bill = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$bill) {
    echo json_encode(['success' => false, 'message' => 'Billing record not found.']);
    $conn->close(); exit;
}

$appointment_id = $bill['appointment_id'];

// Guard: already fully paid
if ($bill['payment_status'] === 'paid') {
    $conn->close();
    echo json_encode([
        'success'          => true,
        'message'          => 'This bill is already marked as paid.',
        'billing_id'       => $billing_id,
        'payment_status'   => 'paid',
        'reference_number' => $bill['reference_number'],
        'appointment_id'   => $appointment_id,
    ]);
    exit;
}

// ─── Calculate new totals and status ─────────────────────
$new_amount_paid = (float)$bill['amount_paid'] + (float)$amount_paid;
$total           = (float)$bill['total_amount'];

if ($new_amount_paid >= $total) {
    $new_status      = 'paid';
    $new_amount_paid = $total;
} else {
    $new_status = 'partial';
}

$reference_number = $bill['reference_number'] ?? ('MOB-' . $patient_id . '-' . time());

// ─── Update billing + flip appointment atomically ─────────
$conn->begin_transaction();

try {
    // 1. Update billing row
    $upd = $conn->prepare("
        UPDATE billing
        SET amount_paid = ?, payment_status = ?, mode = ?, reference_number = ?
        WHERE billing_id = ? AND tenant_id = ?
    ");
    $upd->bind_param("dsssii", $new_amount_paid, $new_status, $mode, $reference_number, $billing_id, $tenant_id);
    if (!$upd->execute()) throw new Exception('Billing update failed: ' . $upd->error);
    $upd->close();

    // 2. Flip appointment from pending_payment → pending
    // (Only updates if still in pending_payment — idempotent if already flipped)
    $flip = $conn->prepare("
        UPDATE appointment
        SET status = 'pending'
        WHERE appointment_id = ? AND tenant_id = ? AND status = 'pending_payment'
    ");
    $flip->bind_param("ii", $appointment_id, $tenant_id);
    if (!$flip->execute()) throw new Exception('Appointment status update failed: ' . $flip->error);
    $flip->close();

    $conn->commit();

} catch (Exception $ex) {
    $conn->rollback();
    $conn->close();
    echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
    exit;
}

$conn->close();

echo json_encode([
    'success'          => true,
    'message'          => 'Payment confirmed and appointment is now pending clinic review.',
    'billing_id'       => $billing_id,
    'payment_status'   => $new_status,
    'new_amount_paid'  => $new_amount_paid,
    'reference_number' => $reference_number,
    'appointment_id'   => $appointment_id,
]);
?>