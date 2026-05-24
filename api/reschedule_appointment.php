<?php
// ============================================================
// FILE: /api/reschedule_appointment.php
// ============================================================
// POST JSON body:
//   appointment_id  (int,    required) — original appointment
//   patient_id      (int,    required)
//   tenant_id       (int,    required)
//   new_date        (string YYYY-MM-DD, required)
//   new_time        (string HH:MM, required)
//
// Flow:
//   1. Validate original appointment belongs to patient
//   2. Enforce 24-hour reschedule window on original slot
//   3. Check new slot availability (duration-aware)
//   4. Cancel original appointment (frees the slot)
//   5. Create new appointment with status 'reschedule_pending'
//      and rescheduled_from_id pointing to original
//   6. Copy services from original to new appointment
//   7. Send reschedule request email
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

$body           = json_decode(file_get_contents('php://input'), true);
$appointment_id = $body['appointment_id'] ?? null;
$patient_id     = $body['patient_id']     ?? null;
$tenant_id      = $body['tenant_id']      ?? null;
$new_date       = $body['new_date']       ?? null;
$new_time       = $body['new_time']       ?? null;

// ── Validate inputs ───────────────────────────────────────────
if (!$appointment_id || !$patient_id || !$tenant_id || !$new_date || !$new_time) {
    echo json_encode(['success' => false, 'message' => 'appointment_id, patient_id, tenant_id, new_date, and new_time are required.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
    echo json_encode(['success' => false, 'message' => 'new_date must be YYYY-MM-DD.']);
    exit;
}

if (strtotime($new_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'New appointment date cannot be in the past.']);
    exit;
}

// ── Fetch original appointment ────────────────────────────────
$stmt = $conn->prepare("
    SELECT a.appointment_id, a.status, a.appointment_date, a.appointment_time,
           a.dentist_id, a.notes, a.total_duration_minutes, a.policy_agreed,
           p.first_name, p.email,
           CONCAT(d.first_name, ' ', d.last_name) AS dentist_name
    FROM appointment a
    JOIN patient  p ON a.patient_id  = p.patient_id
    LEFT JOIN dentist d ON a.dentist_id = d.dentist_id
    WHERE a.appointment_id = ? AND a.patient_id = ? AND a.tenant_id = ?
    LIMIT 1
");
$stmt->bind_param("iii", $appointment_id, $patient_id, $tenant_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appt) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
    $conn->close(); exit;
}

// Only allow reschedule on active appointments
if (!in_array($appt['status'], ['pending', 'confirmed', 'reschedule_pending'])) {
    echo json_encode([
        'success' => false,
        'message' => "Appointments with status \"{$appt['status']}\" cannot be rescheduled.",
    ]);
    $conn->close(); exit;
}

// ── 24-hour window check on original slot ─────────────────────
$old_slot_ts = strtotime($appt['appointment_date'] . ' ' . ($appt['appointment_time'] ?? '23:59:59'));
$hours_until = ($old_slot_ts - time()) / 3600;

if ($hours_until < 24) {
    $hours_left = max(0, round($hours_until, 1));
    echo json_encode([
        'success'     => false,
        'message'     => "Rescheduling must be requested at least 24 hours before the original appointment. Your appointment is in {$hours_left} hour(s) — rescheduling is no longer available.",
        'hours_until' => $hours_left,
    ]);
    $conn->close(); exit;
}

// ── Same-day 1hr buffer on new slot ──────────────────────────
if ($new_date === date('Y-m-d')) {
    $new_slot_ts = strtotime($new_date . ' ' . $new_time);
    if ($new_slot_ts < time() + 3600) {
        echo json_encode(['success' => false, 'message' => 'Same-day bookings must be at least 1 hour from now.']);
        $conn->close(); exit;
    }
}

// ── Duration-aware conflict check on new slot ─────────────────
$dentist_id     = $appt['dentist_id'];
$total_duration = (int)($appt['total_duration_minutes'] ?? 30);
$buffer         = 15;
$new_start_ts   = strtotime($new_date . ' ' . $new_time);
$new_end_ts     = $new_start_ts + (($total_duration + $buffer) * 60);

$chk = $conn->prepare("
    SELECT appointment_time, COALESCE(total_duration_minutes, 30) AS dur
    FROM appointment
    WHERE dentist_id = ? AND tenant_id = ? AND appointment_date = ?
      AND status NOT IN ('cancelled', 'no_show', 'declined')
      AND appointment_id != ?
");
$chk->bind_param("iisi", $dentist_id, $tenant_id, $new_date, $appointment_id);
$chk->execute();
$existing = $chk->get_result()->fetch_all(MYSQLI_ASSOC);
$chk->close();

foreach ($existing as $ex) {
    $ex_start = strtotime($new_date . ' ' . $ex['appointment_time']);
    $ex_end   = $ex_start + (((int)$ex['dur'] + $buffer) * 60);
    if ($new_start_ts < $ex_end && $new_end_ts > $ex_start) {
        echo json_encode(['success' => false, 'message' => 'The requested time slot is not available. Please choose a different time.']);
        $conn->close(); exit;
    }
}

// ── Fetch original appointment_services (to copy) ────────────
$svc_stmt = $conn->prepare("
    SELECT service_id, service_name, duration_minutes, price
    FROM appointment_services
    WHERE appointment_id = ?
");
$svc_stmt->bind_param("i", $appointment_id);
$svc_stmt->execute();
$original_services = $svc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$svc_stmt->close();

// ── Transaction ───────────────────────────────────────────────
$conn->begin_transaction();

try {
    $now = date('Y-m-d H:i:s');

    // 1. Cancel original appointment (frees the old slot)
    $cancel = $conn->prepare("
        UPDATE appointment SET status = 'cancelled'
        WHERE appointment_id = ? AND patient_id = ? AND tenant_id = ?
    ");
    $cancel->bind_param("iii", $appointment_id, $patient_id, $tenant_id);
    if (!$cancel->execute()) throw new Exception('Failed to cancel original: ' . $cancel->error);
    $cancel->close();

    // 2. Insert new appointment with reschedule_pending status
    $policy = (int)$appt['policy_agreed'];
    $ins    = $conn->prepare("
        INSERT INTO appointment
            (tenant_id, patient_id, dentist_id, appointment_date, appointment_time,
             notes, service_id, status, total_duration_minutes, policy_agreed,
             rescheduled_from_id, reschedule_requested_at)
        VALUES (?, ?, ?, ?, ?, ?, NULL, 'reschedule_pending', ?, ?, ?, ?)
    ");
    $ins->bind_param(
        "iiissssiis",
        $tenant_id, $patient_id, $dentist_id,
        $new_date, $new_time,
        $appt['notes'], $total_duration,
        $policy,
        $appointment_id, $now
    );
    if (!$ins->execute()) throw new Exception('Failed to create reschedule appointment: ' . $ins->error);
    $new_appointment_id = $conn->insert_id;
    $ins->close();

    // 3. Copy services to new appointment
    if (!empty($original_services)) {
        $svc_ins = $conn->prepare("
            INSERT INTO appointment_services (appointment_id, service_id, service_name, duration_minutes, price)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($original_services as $svc) {
            $svc_id    = (int)   $svc['service_id'];
            $svc_name  =          $svc['service_name'];
            $svc_dur   = (int)   $svc['duration_minutes'];
            $svc_price = (float) $svc['price'];
            $svc_ins->bind_param("iisid", $new_appointment_id, $svc_id, $svc_name, $svc_dur, $svc_price);
            if (!$svc_ins->execute()) throw new Exception('Failed to copy service: ' . $svc_ins->error);
        }
        $svc_ins->close();
    }

    $conn->commit();

} catch (Exception $ex) {
    $conn->rollback();
    $conn->close();
    echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
    exit;
}

$conn->close();

// ── Send reschedule request email (non-blocking) ──────────────
if ($appt['email']) {
    sendRescheduleRequestEmail(
        $appt['email'],
        $appt['first_name'],
        date('F j, Y', strtotime($appt['appointment_date'])),
        date('g:i A',  strtotime($appt['appointment_time'] ?? '00:00')),
        date('F j, Y', strtotime($new_date)),
        date('g:i A',  strtotime($new_time)),
        $appt['dentist_name'] ?? 'Your dentist'
    );
}

echo json_encode([
    'success'            => true,
    'message'            => 'Reschedule request submitted. The clinic will review your new schedule.',
    'new_appointment_id' => $new_appointment_id,
    'status'             => 'reschedule_pending',
]);
?>