<?php
// ============================================================
// FILE TYPE: API ENDPOINT — send to groupmate for deployment
// PATH on server: /api/book_appointment.php
// ============================================================
// POST JSON body:
//   patient_id        (int, required)
//   tenant_id         (int, required)
//   dentist_id        (int, required)
//   service_id        (int, required)
//   appointment_date  (string YYYY-MM-DD, required)
//   appointment_time  (string HH:MM, required)
//   notes             (string, optional)
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

// Parse JSON body
$body = json_decode(file_get_contents('php://input'), true);

$patient_id       = $body['patient_id']       ?? null;
$tenant_id        = $body['tenant_id']        ?? null;
$dentist_id       = $body['dentist_id']       ?? null;
$service_id       = $body['service_id']       ?? null;
$appointment_date = $body['appointment_date'] ?? null;
$appointment_time = $body['appointment_time'] ?? null;
$notes            = $body['notes']            ?? null;

// Validate required fields
if (!$patient_id || !$tenant_id || !$dentist_id || !$service_id ||
    !$appointment_date || !$appointment_time) {
    echo json_encode([
        'success' => false,
        'message' => 'patient_id, tenant_id, dentist_id, service_id, appointment_date, and appointment_time are all required'
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

// Check for double-booking (same dentist, date, time, not cancelled)
$check = $conn->prepare("
    SELECT appointment_id FROM appointment
    WHERE dentist_id = ?
      AND tenant_id  = ?
      AND appointment_date = ?
      AND appointment_time = ?
      AND status NOT IN ('cancelled')
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

// Get service name for procedure_name
$svc = $conn->prepare("SELECT service_name FROM service WHERE service_id = ? AND tenant_id = ?");
$svc->bind_param("ii", $service_id, $tenant_id);
$svc->execute();
$svc_row = $svc->get_result()->fetch_assoc();
$svc->close();
$procedure_name = $svc_row ? $svc_row['service_name'] : null;

// Insert appointment
$stmt = $conn->prepare("
    INSERT INTO appointment
        (tenant_id, patient_id, dentist_id, appointment_date, appointment_time, notes, service_id, status, procedure_name)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
");
$stmt->bind_param(
    "iiisssis",
    $tenant_id,
    $patient_id,
    $dentist_id,
    $appointment_date,
    $appointment_time,
    $notes,
    $service_id,
    $procedure_name
);

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;
    echo json_encode([
        'success'        => true,
        'message'        => 'Appointment booked successfully',
        'appointment_id' => $new_id
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to book appointment. Please try again.',
        'error'   => $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>