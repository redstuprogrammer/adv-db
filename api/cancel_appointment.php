<?php
// ============================================================
// FILE TYPE: API ENDPOINT — deploy to server
// PATH on server: /api/cancel_appointment.php
// ============================================================
// POST JSON body:
//   appointment_id  (int, required)
//   patient_id      (int, required)
//   tenant_id       (int, required)
//
// Cancellation window rules:
//   - confirmed appointments: must be >48 hours before slot
//   - pending appointments:   must be >24 hours before slot
//   - pending_payment:        can cancel anytime (no deposit paid yet)
//   - done / cancelled / voided: cannot cancel
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

$appointment_id = $body['appointment_id'] ?? null;
$patient_id     = $body['patient_id']     ?? null;
$tenant_id      = $body['tenant_id']      ?? null;

if (!$appointment_id || !$patient_id || !$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'appointment_id, patient_id, and tenant_id are required']);
    exit;
}

// Fetch the appointment — must belong to this patient + tenant
$stmt = $conn->prepare("
    SELECT appointment_id, status, appointment_date, appointment_time
    FROM appointment
    WHERE appointment_id = ?
      AND patient_id     = ?
      AND tenant_id      = ?
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

// Statuses that can never be cancelled
if (in_array($status, ['done', 'cancelled', 'voided'])) {
    echo json_encode([
        'success' => false,
        'message' => "This appointment is already {$status} and cannot be cancelled.",
    ]);
    $conn->close(); exit;
}

// pending_payment → can always cancel (deposit hasn't been paid)
if ($status !== 'pending_payment') {
    // Build the appointment datetime for comparison
    $appt_time = $appt['appointment_time'] ?? '00:00:00';
    $slot_ts   = strtotime($appt['appointment_date'] . ' ' . $appt_time);
    $now       = time();
    $hours_until = ($slot_ts - $now) / 3600;

    // confirmed = 48h window, pending = 24h window
    $required_hours = ($status === 'confirmed') ? 48 : 24;

    if ($hours_until < $required_hours) {
        $window = $required_hours === 48 ? '48 hours' : '24 hours';
        echo json_encode([
            'success'       => false,
            'message'       => "Confirmed appointments must be cancelled at least {$window} before the scheduled time. Your appointment is too soon to cancel.",
            'hours_until'   => round($hours_until, 1),
            'required_hours'=> $required_hours,
        ]);
        $conn->close(); exit;
    }
}

// All checks passed — cancel it
$upd = $conn->prepare("
    UPDATE appointment SET status = 'cancelled'
    WHERE appointment_id = ? AND patient_id = ? AND tenant_id = ?
");
$upd->bind_param("iii", $appointment_id, $patient_id, $tenant_id);
$upd->execute();
$upd->close();
$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Appointment cancelled successfully.',
]);
?>