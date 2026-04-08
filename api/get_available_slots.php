<?php
// ============================================================
// FILE TYPE: API ENDPOINT — send to groupmate for deployment
// PATH on server: /api/get_available_slots.php
// ============================================================
// GET params:
//   tenant_id  (int, required)
//   dentist_id (int, required)
//   date       (string YYYY-MM-DD, required)
//
// Returns 30-min slots within the dentist's schedule for that day,
// marking each slot available or unavailable (booked/past).
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Only GET requests allowed']);
    exit;
}

$tenant_id  = $_GET['tenant_id']  ?? '';
$dentist_id = $_GET['dentist_id'] ?? '';
$date       = $_GET['date']       ?? '';

if (empty($tenant_id)  || !is_numeric($tenant_id)  ||
    empty($dentist_id) || !is_numeric($dentist_id) ||
    empty($date)       || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Valid tenant_id, dentist_id, and date (YYYY-MM-DD) are required']);
    exit;
}

$day_of_week = date('l', strtotime($date));

// 1. Get dentist's schedule for that day
$stmt = $conn->prepare("
    SELECT start_time, end_time
    FROM dentist_schedule
    WHERE dentist_id   = ?
      AND tenant_id    = ?
      AND day_of_week  = ?
      AND is_available = 1
      AND start_time  != end_time
    LIMIT 1
");
$stmt->bind_param("iis", $dentist_id, $tenant_id, $day_of_week);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$schedule) {
    echo json_encode(['success' => true, 'message' => 'No schedule for this dentist on ' . $day_of_week, 'slots' => []]);
    exit;
}

// 2. Get already-booked times for this dentist on this date
$stmt = $conn->prepare("
    SELECT appointment_time
    FROM appointment
    WHERE dentist_id       = ?
      AND tenant_id        = ?
      AND appointment_date = ?
      AND status NOT IN ('cancelled')
");
$stmt->bind_param("iis", $dentist_id, $tenant_id, $date);
$stmt->execute();
$res = $stmt->get_result();
$booked_times = [];
while ($row = $res->fetch_assoc()) {
    if ($row['appointment_time']) {
        $booked_times[] = date('H:i', strtotime($row['appointment_time']));
    }
}
$stmt->close();

// 3. Generate 30-min slots
$slot_duration = 30;
$slots   = [];
$current = strtotime($schedule['start_time']);
$end     = strtotime($schedule['end_time']);
$is_today = ($date === date('Y-m-d'));

while ($current < $end) {
    $slot_time   = date('H:i', $current);
    $slot_label  = date('g:i A', $current);
    $slot_passed = $is_today && ($current <= time());

    $slots[] = [
        'time'      => $slot_time,   // HH:MM — what you POST to book
        'label'     => $slot_label,  // h:mm A — what you display
        'available' => !in_array($slot_time, $booked_times) && !$slot_passed,
    ];

    $current = strtotime("+{$slot_duration} minutes", $current);
}

echo json_encode([
    'success'  => true,
    'message'  => 'Slots fetched successfully',
    'day'      => $day_of_week,
    'schedule' => ['start' => $schedule['start_time'], 'end' => $schedule['end_time']],
    'slots'    => $slots,
]);

$conn->close();
?>