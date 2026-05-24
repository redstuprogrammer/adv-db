<?php
// ============================================================
// FILE: /api/book_appointment.php
// ============================================================
// POST JSON body:
//   patient_id             (int,    required)
//   tenant_id              (int,    required)
//   dentist_id             (int,    required)
//   appointment_date       (string YYYY-MM-DD, required)
//   appointment_time       (string HH:MM, required)
//   total_duration_minutes (int,    required)
//   policy_agreed          (bool,   required) -- must be true
//   services               (array,  required) -- [{ service_id }]
//   notes                  (string, optional)
// ============================================================

date_default_timezone_set('Asia/Manila');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level()) ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Internal server error.', 'debug' => $error['message']]);
    }
});

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../config/send_mail.php';

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

$patient_id             = $body['patient_id']             ?? null;
$tenant_id              = $body['tenant_id']              ?? null;
$dentist_id             = $body['dentist_id']             ?? null;
$appointment_date       = $body['appointment_date']       ?? null;
$appointment_time       = $body['appointment_time']       ?? null;
$total_duration_minutes = (int)($body['total_duration_minutes'] ?? 30);
$policy_agreed          = !empty($body['policy_agreed']);
$services               = $body['services']               ?? [];
$notes                  = $body['notes']                  ?? null;

// Validate required fields
if (!$patient_id || !$tenant_id || !$dentist_id || !$appointment_date || !$appointment_time) {
    echo json_encode(['success' => false, 'message' => 'patient_id, tenant_id, dentist_id, appointment_date, and appointment_time are required.']);
    exit;
}

if (!$policy_agreed) {
    echo json_encode(['success' => false, 'message' => 'You must agree to the cancellation policy to book an appointment.']);
    exit;
}

if (empty($services) || !is_array($services)) {
    echo json_encode(['success' => false, 'message' => 'At least one service must be selected.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    echo json_encode(['success' => false, 'message' => 'appointment_date must be YYYY-MM-DD.']);
    exit;
}

if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'Appointment date cannot be in the past.']);
    exit;
}

if ($appointment_date === date('Y-m-d')) {
    $slot_ts     = strtotime($appointment_date . ' ' . $appointment_time);
    $min_allowed = time() + (1 * 60 * 60);
    if ($slot_ts < $min_allowed) {
        echo json_encode(['success' => false, 'message' => 'Same-day bookings must be at least 1 hour from now.']);
        exit;
    }
}

// Duration-aware double-booking check
$buffer    = 15;
$new_start = strtotime($appointment_date . ' ' . $appointment_time);
$new_end   = $new_start + (($total_duration_minutes + $buffer) * 60);

$chk = $conn->prepare("
    SELECT appointment_time, COALESCE(total_duration_minutes, 30) AS dur
    FROM appointment
    WHERE dentist_id = ? AND tenant_id = ? AND appointment_date = ?
      AND status NOT IN ('cancelled', 'no_show', 'declined')
");
$chk->bind_param("iis", $dentist_id, $tenant_id, $appointment_date);
$chk->execute();
$existing = $chk->get_result()->fetch_all(MYSQLI_ASSOC);
$chk->close();

foreach ($existing as $ex) {
    $ex_start = strtotime($appointment_date . ' ' . $ex['appointment_time']);
    $ex_end   = $ex_start + (((int)$ex['dur'] + $buffer) * 60);
    if ($new_start < $ex_end && $new_end > $ex_start) {
        echo json_encode(['success' => false, 'message' => 'This time slot overlaps an existing appointment. Please choose another time.']);
        $conn->close(); exit;
    }
}

// Fetch patient email for notification
$pat = $conn->prepare("SELECT first_name, email FROM patient WHERE patient_id = ? LIMIT 1");
$pat->bind_param("i", $patient_id);
$pat->execute();
$patient_row = $pat->get_result()->fetch_assoc();
$pat->close();

// Fetch dentist name
$den = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM dentist WHERE dentist_id = ? LIMIT 1");
$den->bind_param("i", $dentist_id);
$den->execute();
$dentist_row = $den->get_result()->fetch_assoc();
$den->close();

// Fetch + snapshot selected service details
$service_ids  = array_map(fn($s) => (int)$s['service_id'], $services);
$placeholders = implode(',', array_fill(0, count($service_ids), '?'));
$types        = str_repeat('i', count($service_ids)) . 'i';
$svc_stmt     = $conn->prepare("
    SELECT service_id, service_name, price, duration_minutes
    FROM service WHERE service_id IN ({$placeholders}) AND tenant_id = ?
");
$bind_params = array_merge($service_ids, [(int)$tenant_id]);
$svc_stmt->bind_param($types, ...$bind_params);
$svc_stmt->execute();
$service_rows = $svc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$svc_stmt->close();

if (empty($service_rows)) {
    echo json_encode(['success' => false, 'message' => 'No valid services found for this clinic.']);
    $conn->close(); exit;
}

// Transaction: insert appointment + appointment_services
$conn->begin_transaction();

try {
    $ins = $conn->prepare("
        INSERT INTO appointment
            (tenant_id, patient_id, dentist_id, appointment_date, appointment_time,
             notes, service_id, status, total_duration_minutes, policy_agreed)
        VALUES (?, ?, ?, ?, ?, ?, NULL, 'pending', ?, 1)
    ");
    $ins->bind_param("iiisssi",
        $tenant_id, $patient_id, $dentist_id,
        $appointment_date, $appointment_time,
        $notes, $total_duration_minutes
    );
    if (!$ins->execute()) throw new Exception('Failed to insert appointment: ' . $ins->error);
    $appointment_id = $conn->insert_id;
    $ins->close();

    $svc_ins = $conn->prepare("
        INSERT INTO appointment_services (appointment_id, service_id, service_name, duration_minutes, price)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($service_rows as $svc) {
        $svc_id    = (int)   $svc['service_id'];
        $svc_name  =          $svc['service_name'];
        $svc_dur   = (int)   $svc['duration_minutes'];
        $svc_price = (float) $svc['price'];
        $svc_ins->bind_param("iisid", $appointment_id, $svc_id, $svc_name, $svc_dur, $svc_price);
        if (!$svc_ins->execute()) throw new Exception('Failed to insert service row: ' . $svc_ins->error);
    }
    $svc_ins->close();

    $conn->commit();

} catch (Exception $ex) {
    $conn->rollback();
    $conn->close();
    echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
    exit;
}

$conn->close();

// Send confirmation email (non-blocking)
if ($patient_row) {
    sendBookingConfirmationEmail(
        $patient_row['email'],
        $patient_row['first_name'],
        date('F j, Y', strtotime($appointment_date)),
        date('g:i A',  strtotime($appointment_time)),
        $dentist_row['full_name'] ?? 'Your dentist',
        $service_rows
    );
}

echo json_encode([
    'success'        => true,
    'message'        => 'Appointment submitted successfully. Awaiting clinic approval.',
    'appointment_id' => $appointment_id,
    'status'         => 'pending',
]);
?>