<?php
// ============================================================
// FILE TYPE: API ENDPOINT — deploy to server
// PATH on server: /api/create_booking_with_deposit.php
// ============================================================
// Called at Step 4 "Confirm & Book" of the booking wizard.
//
// POST JSON body:
//   patient_id        (int,    required)
//   tenant_id         (int,    required)
//   dentist_id        (int,    required)
//   appointment_date  (string YYYY-MM-DD, required)
//   appointment_time  (string HH:MM, required)
//   notes             (string, optional)
//
// Behavior:
//   1. Validates input + checks for double-booking
//   2. Reads booking_deposit_amount from tenant_configs
//   3a. deposit > 0 → appointment status = 'pending_payment',
//       creates a billing row (amount_paid = 0),
//       returns { appointment_id, billing_id, deposit_amount, deposit_required: true }
//   3b. no deposit  → appointment status = 'pending',
//       returns { appointment_id, deposit_required: false }
//
// ⚠️  SCHEMA CHANGES REQUIRED (ask groupmate):
//   1. ALTER TABLE appointment MODIFY COLUMN status
//      ENUM('pending','pending_payment','completed','cancelled','approved','disapproved')
//      NOT NULL DEFAULT 'pending';
//
//   2. ALTER TABLE billing MODIFY COLUMN service_id INT NULL;
//      (service is assigned by clinic staff later — cannot be known at booking time)
//
//   3. Ensure billing table has these columns (add if missing):
//      paymongo_session_id VARCHAR(255) NULL,
//      reference_number    VARCHAR(255) NULL,
//      mode                VARCHAR(50)  NULL
//
//   4. In tenant_configs, add a row per tenant:
//      ('booking_deposit_amount', '500.00')  -- or whatever amount
//      ('cancellation_hours', '24')          -- hours before slot
// ============================================================

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level()) ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Internal server error.', 'debug' => $e['message']]);
    }
});

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../connect.php';
date_default_timezone_set('Asia/Manila');

if (!isset($conn) || !$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

$patient_id       = $body['patient_id']       ?? null;
$tenant_id        = $body['tenant_id']        ?? null;
$dentist_id       = $body['dentist_id']       ?? null;
$appointment_date = $body['appointment_date'] ?? null;
$appointment_time = $body['appointment_time'] ?? null;
$notes            = $body['notes']            ?? null;

// ─── Validate required fields ─────────────────────────────
if (!$patient_id || !$tenant_id || !$dentist_id || !$appointment_date || !$appointment_time) {
    echo json_encode([
        'success' => false,
        'message' => 'patient_id, tenant_id, dentist_id, appointment_date, and appointment_time are all required.',
    ]);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    echo json_encode(['success' => false, 'message' => 'appointment_date must be YYYY-MM-DD.']);
    exit;
}

if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'appointment_date cannot be in the past.']);
    exit;
}

if ($appointment_date === date('Y-m-d')) {
    $slot_ts    = strtotime($appointment_date . ' ' . $appointment_time);
    $min_allowed = time() + (1 * 60 * 60);
    if ($slot_ts < $min_allowed) {
        echo json_encode(['success' => false, 'message' => 'Same-day bookings must be at least 1 hour from now.']);
        exit;
    }
}

// ─── Double-booking check ─────────────────────────────────
$chk = $conn->prepare("
    SELECT appointment_id FROM appointment
    WHERE dentist_id       = ?
      AND tenant_id        = ?
      AND appointment_date = ?
      AND appointment_time = ?
      AND status NOT IN ('cancelled', 'voided')
    LIMIT 1
");
if (!$chk) {
    echo json_encode(['success' => false, 'message' => 'DB error (double-book check): ' . $conn->error]);
    exit;
}
$chk->bind_param("iiss", $dentist_id, $tenant_id, $appointment_date, $appointment_time);
$chk->execute();
$chk->store_result();
if ($chk->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'This time slot is already booked. Please choose another.']);
    $chk->close(); $conn->close(); exit;
}
$chk->close();

// ─── Read deposit amount from tenant_configs ──────────────
$dep_stmt = $conn->prepare("
    SELECT config_value FROM tenant_configs
    WHERE tenant_id = ? AND config_key = 'booking_deposit_amount'
    LIMIT 1
");
$dep_stmt->bind_param("i", $tenant_id);
$dep_stmt->execute();
$dep_row = $dep_stmt->get_result()->fetch_assoc();
$dep_stmt->close();

$deposit_amount   = ($dep_row && $dep_row['config_value'] !== null && $dep_row['config_value'] !== '')
                    ? (float)$dep_row['config_value']
                    : null;
$deposit_required = $deposit_amount !== null && $deposit_amount > 0;

// Status is 'pending_payment' when deposit is required, 'pending' otherwise.
$initial_status = $deposit_required ? 'pending_payment' : 'pending';

// ─── Insert appointment ───────────────────────────────────
$conn->begin_transaction();

try {
    $ins = $conn->prepare("
        INSERT INTO appointment
            (tenant_id, patient_id, dentist_id, appointment_date, appointment_time, notes, service_id, status)
        VALUES
            (?, ?, ?, ?, ?, ?, NULL, ?)
    ");
    if (!$ins) throw new Exception('DB error (insert appt): ' . $conn->error);

    $ins->bind_param("iiissss", $tenant_id, $patient_id, $dentist_id,
                                $appointment_date, $appointment_time, $notes, $initial_status);
    if (!$ins->execute()) throw new Exception('Failed to create appointment: ' . $ins->error);

    $appointment_id = $ins->insert_id;
    $ins->close();

    $billing_id = null;

    // ─── If deposit required, create billing row ──────────
    if ($deposit_required) {
        // service_id must be nullable for this to work (see schema note at top)
        $bill = $conn->prepare("
            INSERT INTO billing
                (tenant_id, appointment_id, patient_id, service_id, total_amount, amount_paid, payment_status, payment_type)
            VALUES
                (?, ?, ?, NULL, ?, 0.00, 'unpaid', 'deposit')
        ");
        if (!$bill) throw new Exception('DB error (insert billing): ' . $conn->error);

        $bill->bind_param("iiid", $tenant_id, $appointment_id, $patient_id, $deposit_amount);
        if (!$bill->execute()) throw new Exception('Failed to create billing row: ' . $bill->error);

        $billing_id = $bill->insert_id;
        $bill->close();
    }

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
    'message'          => $deposit_required ? 'Appointment reserved. Complete deposit to confirm.' : 'Appointment booked successfully.',
    'appointment_id'   => $appointment_id,
    'billing_id'       => $billing_id,
    'deposit_required' => $deposit_required,
    'deposit_amount'   => $deposit_required ? $deposit_amount : null,
    'status'           => $initial_status,
]);
?>