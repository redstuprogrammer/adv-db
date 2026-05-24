<?php
// ============================================================
// FILE: /api/get_appointment.php
// ============================================================
// GET ?patient_id=X
//
// Changes from previous version:
//   1. Past missed appointments → status 'no_show' (not 'cancelled')
//      and patient.no_show_count is incremented accordingly.
//   2. Each appointment now includes a 'services' array from
//      appointment_services (empty array for pre-migration rows).
//   3. Status values returned are the new clean set:
//      pending | confirmed | reschedule_pending | completed
//      | cancelled | no_show | declined
// ============================================================

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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../connect.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Only GET requests allowed']);
    exit;
}

$patient_id = $_GET['patient_id'] ?? '';

if (empty($patient_id) || !is_numeric($patient_id)) {
    echo json_encode(['success' => false, 'message' => 'A valid patient_id is required']);
    exit;
}

$patient_id = (int) $patient_id;

// ── Step 1: Mark missed appointments as no_show ───────────────
// Only active statuses — not already-terminal ones.
// Terminal = completed, cancelled, no_show, declined.
$no_show_stmt = $conn->prepare("
    UPDATE appointment
    SET status = 'no_show'
    WHERE patient_id = ?
      AND status NOT IN ('completed', 'cancelled', 'no_show', 'declined')
      AND TIMESTAMP(appointment_date, COALESCE(appointment_time, '23:59:59')) < NOW()
");
if (!$no_show_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed (no_show): ' . $conn->error]);
    exit;
}
$no_show_stmt->bind_param("i", $patient_id);
$no_show_stmt->execute();
$newly_marked_no_show = $no_show_stmt->affected_rows;
$no_show_stmt->close();

// ── Step 2: Increment no_show_count if any were just marked ──
if ($newly_marked_no_show > 0) {
    $inc = $conn->prepare("
        UPDATE patient
        SET no_show_count = no_show_count + ?
        WHERE patient_id = ?
    ");
    $inc->bind_param("ii", $newly_marked_no_show, $patient_id);
    $inc->execute();
    $inc->close();
}

// ── Step 3: Fetch all appointments ───────────────────────────
$stmt = $conn->prepare("
    SELECT
        a.appointment_id,
        a.appointment_date          AS date,
        a.appointment_time          AS time,
        a.status,
        a.notes,
        a.tenant_id,
        a.dentist_id,
        a.total_duration_minutes,
        a.rescheduled_from_id,
        CONCAT(d.first_name, ' ', d.last_name) AS doctor
    FROM appointment a
    JOIN tenants t       ON a.tenant_id  = t.tenant_id
    LEFT JOIN dentist d  ON a.dentist_id = d.dentist_id
    WHERE a.patient_id = ?
      AND t.status = 'active'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed (select): ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

$appointments    = [];
$appointment_ids = [];

while ($row = $result->fetch_assoc()) {
    $row['services']    = []; // will be filled below
    $appointments[]     = $row;
    $appointment_ids[]  = (int)$row['appointment_id'];
}
$stmt->close();

// ── Step 4: Fetch services for all appointments in one query ──
if (!empty($appointment_ids)) {
    $placeholders = implode(',', array_fill(0, count($appointment_ids), '?'));
    $types        = str_repeat('i', count($appointment_ids));
    $svc_stmt     = $conn->prepare("
        SELECT appointment_id, service_id, service_name, duration_minutes, price
        FROM appointment_services
        WHERE appointment_id IN ({$placeholders})
        ORDER BY id ASC
    ");
    $svc_stmt->bind_param($types, ...$appointment_ids);
    $svc_stmt->execute();
    $svc_rows = $svc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $svc_stmt->close();

    // Group services by appointment_id
    $services_map = [];
    foreach ($svc_rows as $svc) {
        $aid = (int)$svc['appointment_id'];
        if (!isset($services_map[$aid])) $services_map[$aid] = [];
        $services_map[$aid][] = [
            'service_id'       => (int)   $svc['service_id'],
            'service_name'     =>          $svc['service_name'],
            'duration_minutes' => (int)   $svc['duration_minutes'],
            'price'            => (float) $svc['price'],
        ];
    }

    // Attach services to each appointment
    foreach ($appointments as &$appt) {
        $aid = (int)$appt['appointment_id'];
        $appt['services'] = $services_map[$aid] ?? [];
    }
    unset($appt);
}

$conn->close();

echo json_encode([
    'success'      => true,
    'message'      => 'Appointments fetched successfully',
    'appointments' => $appointments,
]);
?>