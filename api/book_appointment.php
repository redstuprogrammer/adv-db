<?php
// ============================================================
// FILE TYPE: API ENDPOINT — deploy to server
// PATH on server: /api/book_appointment.php
// ============================================================
// POST JSON body:
//   patient_id        (int, required)
//   tenant_id         (int, required)
//   dentist_id        (int, required)
//   appointment_date  (string YYYY-MM-DD, required)
//   appointment_time  (string HH:MM, required)
//   notes             (string, optional)
//
// If clinic has booking_deposit_amount set → status = 'pending_payment'
// Otherwise → status = 'pending'
//
// service_id is intentionally NULL — assigned by staff on web portal.
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

$patient_id       = $body['patient_id']       ?? null;
$tenant_id        = $body['tenant_id']        ?? null;
$dentist_id       = $body['dentist_id']       ?? null;
$appointment_date = $body['appointment_date'] ?? null;
$appointment_time = $body['appointment_time'] ?? null;
$notes            = $body['notes']            ?? null;

// Validate required fields
if (!$patient_id || !$tenant_id || !$dentist_id || !$appointment_date || !$appointment_time) {
    echo json_encode([
        'success' => false,
        'message' => 'patient_id, tenant_id, dentist_id, appointment_date, and appointment_time are all required'
    ]);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    echo json_encode(['success' => false, 'message' => 'appointment_date must be YYYY-MM-DD']);
    exit;
}

// Validate date is not in the past
if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'appointment_date cannot be in the past']);
    exit;
}

// Validate time is at least 2 hours from now if booking same day
if ($appointment_date === date('Y-m-d')) {
    $slot_timestamp  = strtotime($appointment_date . ' ' . $appointment_time);
    $min_allowed     = time() + (2 * 60 * 60); // 2 hours from now
    if ($slot_timestamp < $min_allowed) {
        echo json_encode([
            'success' => false,
            'message' => 'Same-day bookings must be at least 2 hours from now.'
        ]);
        exit;
    }
}

// Check for double-booking (same dentist + date + time, not cancelled/voided)
$check = $conn->prepare("
    SELECT appointment_id FROM appointment
    WHERE dentist_id       = ?
      AND tenant_id        = ?
      AND appointment_date = ?
      AND appointment_time = ?
      AND status NOT IN ('cancelled', 'voided')
    LIMIT 1
");
$check->bind_param("iiss", $dentist_id, $tenant_id, $appointment_date, $appointment_time);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'This time slot is already booked. Please choose another.']);
    $check->close();
    $conn->close();
    exit;
}
$check->close();

// Check if clinic requires a deposit
$cfg = $conn->prepare("
    SELECT booking_deposit_amount
    FROM tenant_configs
    WHERE tenant_id = ?
    LIMIT 1
");
$cfg->bind_param("i", $tenant_id);
$cfg->execute();
$cfg_row = $cfg->get_result()->fetch_assoc();
$cfg->close();

$deposit_amount   = $cfg_row ? $cfg_row['booking_deposit_amount'] : null;
$deposit_required = !is_null($deposit_amount) && $deposit_amount > 0;

// Status depends on whether deposit is required
$initial_status = $deposit_required ? 'pending_payment' : 'pending';

// Insert appointment
$stmt = $conn->prepare("
    INSERT INTO appointment
        (tenant_id, patient_id, dentist_id, appointment_date, appointment_time, notes, service_id, status)
    VALUES
        (?, ?, ?, ?, ?, ?, NULL, ?)
");
$stmt->bind_param("iiissss", $tenant_id, $patient_id, $dentist_id, $appointment_date, $appointment_time, $notes, $initial_status);

if ($stmt->execute()) {
    echo json_encode([
        'success'          => true,
        'message'          => 'Appointment booked successfully',
        'appointment_id'   => $stmt->insert_id,
        'status'           => $initial_status,
        'deposit_required' => $deposit_required,
        'deposit_amount'   => $deposit_required ? (float)$deposit_amount : null,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to book appointment. Please try again.',
        'error'   => $stmt->error,
    ]);
}

$stmt->close();
$conn->close();
?>