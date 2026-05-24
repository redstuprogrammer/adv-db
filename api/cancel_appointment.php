<?php
// ============================================================
// FILE: /api/cancel_appointment.php
// ============================================================
// POST JSON body:
//   appointment_id  (int, required)
//   patient_id      (int, required)
//   tenant_id       (int, required)
//
// Cancellation window rules:
//   confirmed          → must be >24h before slot
//   pending            → must be >24h before slot
//   reschedule_pending → must be >24h before new proposed slot
//   completed / cancelled / no_show / declined → cannot cancel
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../config/send_mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

$body           = json_decode(file_get_contents('php://input'), true);
$appointment_id = $body['appointment_id'] ?? null;
$patient_id     = $body['patient_id']     ?? null;
$tenant_id      = $body['tenant_id']      ?? null;

if (!$appointment_id || !$patient_id || !$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'appointment_id, patient_id, and tenant_id are required.']);
    exit;
}

// Fetch appointment — must belong to this patient + tenant
$stmt = $conn->prepare("
    SELECT a.appointment_id, a.status, a.appointment_date, a.appointment_time,
           p.first_name, p.email,
           CONCAT(d.first_name, ' ', d.last_name) AS dentist_name
    FROM appointment a
    JOIN patient p  ON a.patient_id  = p.patient_id
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

$status = $appt['status'];

// Statuses that cannot be cancelled
if (in_array($status, ['completed', 'cancelled', 'no_show', 'declined'])) {
    echo json_encode([
        'success' => false,
        'message' => "This appointment is already marked as \"{$status}\" and cannot be cancelled.",
    ]);
    $conn->close(); exit;
}

// Enforce 24-hour cancellation window
$slot_ts      = strtotime($appt['appointment_date'] . ' ' . ($appt['appointment_time'] ?? '23:59:59'));
$hours_until  = ($slot_ts - time()) / 3600;

if ($hours_until < 24) {
    $hours_left = max(0, round($hours_until, 1));
    echo json_encode([
        'success'      => false,
        'message'      => "Appointments must be cancelled at least 24 hours before the scheduled time. Your appointment is in {$hours_left} hour(s) — cancellation is no longer available.",
        'hours_until'  => $hours_left,
    ]);
    $conn->close(); exit;
}

// Cancel it
$upd = $conn->prepare("
    UPDATE appointment SET status = 'cancelled'
    WHERE appointment_id = ? AND patient_id = ? AND tenant_id = ?
");
$upd->bind_param("iii", $appointment_id, $patient_id, $tenant_id);
$upd->execute();
$upd->close();
$conn->close();

// Send cancellation email (non-blocking)
if ($appt['email']) {
    sendCancellationEmail(
        $appt['email'],
        $appt['first_name'],
        date('F j, Y', strtotime($appt['appointment_date'])),
        date('g:i A',  strtotime($appt['appointment_time'] ?? '00:00')),
        $appt['dentist_name'] ?? 'Your dentist'
    );
}

echo json_encode([
    'success' => true,
    'message' => 'Appointment cancelled successfully.',
]);
?>