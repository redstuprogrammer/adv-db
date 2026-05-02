<?php
// ============================================================
// FILE TYPE: API ENDPOINT
// PATH on server: /api/confirm_paymongo_payment.php
// ============================================================
// FIXES applied to original file:
//   1. Added ob_start() + register_shutdown_function so PHP
//      fatal errors return JSON instead of empty HTTP 500.
//   2. Robust config/paymongo.php loader with path fallbacks
//      (Azure __DIR__ can resolve differently per deployment).
//   3. Added CURLOPT_TIMEOUT + CURLOPT_CONNECTTIMEOUT so a
//      slow PayMongo response doesn't kill the script silently.
//   4. set_time_limit(60) for Azure safety.
// ============================================================

ob_start();

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Internal server error.',
            'debug'   => $error['message'],
            'file'    => basename($error['file']),
            'line'    => $error['line'],
        ]);
    }
    ob_end_flush();
});

set_time_limit(60);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../connect.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

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

// ─── Robust config/paymongo.php loader ────────────────────
$pm_config = null;
$config_candidates = [
    __DIR__ . '/../config/paymongo.php',
    dirname(__DIR__) . '/config/paymongo.php',
    $_SERVER['DOCUMENT_ROOT'] . '/config/paymongo.php',
];
foreach ($config_candidates as $path) {
    if (file_exists($path)) {
        $pm_config = require $path;
        break;
    }
}

$secret = $pm_config['secret_key'] ?? getenv('PAYMONGO_SECRET_KEY') ?? '';
$auth   = base64_encode($secret . ':');

if (!$secret) {
    error_log("[confirm_payment] ERROR: PayMongo secret key not found. Tried: " . implode(', ', $config_candidates));
    echo json_encode(['success' => false, 'message' => 'Server configuration error: payment credentials missing.']);
    exit;
}

// ─── Verify checkout session with PayMongo ────────────────
$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions/' . urlencode($session_id));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Authorization: Basic ' . $auth,
    ],
]);
$pm_response = curl_exec($ch);
$http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_errno  = curl_errno($ch);
$curl_error  = curl_error($ch);
curl_close($ch);

if ($curl_errno || !$pm_response) {
    error_log("[confirm_payment] cURL error #{$curl_errno}: {$curl_error}");
    echo json_encode(['success' => false, 'message' => 'Could not verify payment with PayMongo.', 'debug' => "cURL #{$curl_errno}: {$curl_error}"]);
    exit;
}

error_log("[confirm_payment] PayMongo HTTP=$http_code response=" . substr($pm_response, 0, 500));

$pm_data = json_decode($pm_response, true);

// ─── Determine if payment is confirmed ───────────────────
$pm_payment_status   = $pm_data['data']['attributes']['payment_status'] ?? 'unpaid';
$pm_payments         = $pm_data['data']['attributes']['payments']       ?? [];
$payment_confirmed   = false;
$paymongo_payment_id = null;

if ($pm_payment_status === 'paid') {
    $payment_confirmed = true;
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
    $dbg = $conn->prepare("SELECT billing_id, tenant_id, payment_status FROM billing WHERE billing_id = ? LIMIT 1");
    $dbg->bind_param("i", $billing_id);
    $dbg->execute();
    $dbgRow = $dbg->get_result()->fetch_assoc();
    $dbg->close();
    error_log("[confirm_payment] Debug lookup: " . json_encode($dbgRow));
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