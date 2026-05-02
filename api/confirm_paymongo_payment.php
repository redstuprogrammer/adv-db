<?php
// ============================================================
// FILE TYPE: API ENDPOINT
// PATH on server: /api/confirm_paymongo_payment.php
// ============================================================
// FIX 1: PayMongo checkout_sessions uses `payment_status` on
//         the session object, BUT in test mode after GCash
//         payment the session `status` can be 'active' while
//         `payment_status` is 'paid'. We now also check
//         `payments` array inside the session as fallback.
// FIX 2: Returns `retry: true` on "not yet paid" so the mobile
//         knows to keep polling vs. a hard failure.
// FIX 3: Added detailed error logging to server error_log.
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

$session_id  = trim($body['session_id']  ?? '');
$billing_id  = intval($body['billing_id']  ?? 0);
$tenant_id   = intval($body['tenant_id']   ?? 0);
$patient_id  = intval($body['patient_id']  ?? 0);
$amount_paid = $body['amount_paid'] ?? null;
$mode        = ucfirst(strtolower($body['mode'] ?? 'Card'));

if (!$session_id || !$billing_id || !$tenant_id || !$patient_id || $amount_paid === null) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

error_log("[confirm_payment] START session=$session_id billing=$billing_id tenant=$tenant_id patient=$patient_id amount=$amount_paid mode=$mode");

$pm_config = require_once __DIR__ . '/../config/paymongo.php';
$secret    = $pm_config['secret_key'] ?? '';
$auth      = base64_encode($secret . ':');

// ─── Verify checkout session with PayMongo ────────────────
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
    error_log("[confirm_payment] cURL error: $curl_error");
    echo json_encode(['success' => false, 'message' => 'Could not verify payment with PayMongo.']);
    exit;
}

error_log("[confirm_payment] PayMongo HTTP=$http_code response=" . substr($pm_response, 0, 500));

$pm_data = json_decode($pm_response, true);

// ─── Determine if payment is confirmed ───────────────────
// PayMongo checkout_sessions can report payment as:
//   1. session.attributes.payment_status = 'paid'   (most reliable)
//   2. session.attributes.payments array with status 'paid'
// We accept EITHER as confirmation.
$pm_payment_status = $pm_data['data']['attributes']['payment_status'] ?? 'unpaid';
$pm_payments       = $pm_data['data']['attributes']['payments']       ?? [];

$payment_confirmed = false;
$paymongo_payment_id = null;

if ($pm_payment_status === 'paid') {
    $payment_confirmed = true;
    // Try to get payment ID from payments array
    if (!empty($pm_payments) && isset($pm_payments[0]['id'])) {
        $paymongo_payment_id = $pm_payments[0]['id'];
    }
}

// Fallback: check payments array even if top-level status isn't 'paid'
if (!$payment_confirmed && !empty($pm_payments)) {
    foreach ($pm_payments as $pmt) {
        $pmt_status = $pmt['attributes']['status'] ?? '';
        if ($pmt_status === 'paid') {
            $payment_confirmed   = true;
            $paymongo_payment_id = $pmt['id'] ?? null;
            break;
        }
    }
}

error_log("[confirm_payment] payment_status=$pm_payment_status confirmed=" . ($payment_confirmed ? 'YES' : 'NO') . " payment_id=$paymongo_payment_id");

if (!$payment_confirmed) {
    // Return retry:true so mobile knows to keep polling
    echo json_encode([
        'success' => false,
        'retry'   => true,
        'message' => "Payment not yet confirmed by PayMongo (status: {$pm_payment_status}). Retrying…",
    ]);
    exit;
}

// ─── Fetch current billing row ────────────────────────────
$chk = $conn->prepare("
    SELECT total_amount, amount_paid, payment_status, reference_number, appointment_id
    FROM billing
    WHERE billing_id = ? AND tenant_id = ?
    LIMIT 1
");
if (!$chk) {
    error_log("[confirm_payment] DB prepare error: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
}
$chk->bind_param("ii", $billing_id, $tenant_id);
$chk->execute();
$bill = $chk->get_result()->fetch_assoc();
$chk->close();

error_log("[confirm_payment] Billing row: " . json_encode($bill));

if (!$bill) {
    // Billing record not found — this usually means the UPDATE in
    // create_paymongo_link.php matched 0 rows (wrong billing_id or tenant_id).
    // Try fetching just by billing_id to debug.
    $dbg = $conn->prepare("SELECT billing_id, tenant_id, payment_status FROM billing WHERE billing_id = ? LIMIT 1");
    $dbg->bind_param("i", $billing_id);
    $dbg->execute();
    $dbgRow = $dbg->get_result()->fetch_assoc();
    $dbg->close();
    error_log("[confirm_payment] Debug lookup by billing_id only: " . json_encode($dbgRow));

    $conn->close();
    echo json_encode(['success' => false, 'message' => "Billing record not found (billing_id=$billing_id, tenant_id=$tenant_id). Payment was received by PayMongo — please contact the clinic."]);
    exit;
}

$appointment_id = $bill['appointment_id'];

// Already fully paid — idempotent response
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

// ─── Calculate new totals ─────────────────────────────────
$new_amount_paid = (float)$bill['amount_paid'] + (float)$amount_paid;
$total           = (float)$bill['total_amount'];
$new_status      = ($new_amount_paid >= $total) ? 'paid' : 'partial';
if ($new_amount_paid > $total) $new_amount_paid = $total;

$reference_number = $bill['reference_number'] ?? ('MOB-' . $patient_id . '-' . time());

error_log("[confirm_payment] Updating billing: new_amount=$new_amount_paid status=$new_status ref=$reference_number payment_id=$paymongo_payment_id");

// ─── Update billing + flip appointment atomically ─────────
$conn->begin_transaction();

try {
    $upd = $conn->prepare("
        UPDATE billing
        SET amount_paid = ?, payment_status = ?, mode = ?,
            reference_number = ?, paymongo_payment_id = ?
        WHERE billing_id = ? AND tenant_id = ?
    ");
    $upd->bind_param("dssssii",
        $new_amount_paid, $new_status, $mode,
        $reference_number, $paymongo_payment_id,
        $billing_id, $tenant_id
    );
    if (!$upd->execute()) throw new Exception('Billing update failed: ' . $upd->error);
    $upd->close();

    // Flip appointment from pending_payment → pending (idempotent)
    $flip = $conn->prepare("
        UPDATE appointment
        SET status = 'pending'
        WHERE appointment_id = ? AND tenant_id = ? AND status = 'pending_payment'
    ");
    $flip->bind_param("ii", $appointment_id, $tenant_id);
    if (!$flip->execute()) throw new Exception('Appointment flip failed: ' . $flip->error);
    $flip->close();

    $conn->commit();
    error_log("[confirm_payment] SUCCESS billing_id=$billing_id status=$new_status");

} catch (Exception $ex) {
    $conn->rollback();
    $conn->close();
    error_log("[confirm_payment] ROLLBACK: " . $ex->getMessage());
    echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
    exit;
}

$conn->close();

echo json_encode([
    'success'          => true,
    'message'          => 'Payment confirmed successfully.',
    'billing_id'       => $billing_id,
    'payment_status'   => $new_status,
    'new_amount_paid'  => $new_amount_paid,
    'reference_number' => $reference_number,
    'appointment_id'   => $appointment_id,
]);
?>