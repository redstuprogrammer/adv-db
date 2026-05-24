<?php
// ============================================================
// FILE: /api/get_available_slots.php
// ============================================================
// GET params:
//   tenant_id       (int,    required)
//   dentist_id      (int,    required)
//   date            (string YYYY-MM-DD, required)
//   total_duration  (int,    optional — default 30)
//             Sum of selected service durations in minutes.
//             System adds 15-min sanitation buffer automatically.
//
// A slot is available only when:
//   1. slot_start + total_duration + 15min buffer <= schedule end
//   2. The window [slot_start … slot_start + total_duration + 15]
//      does not overlap any existing appointment's occupied window.
//
// Slot grid: every 30 minutes.
// ============================================================

date_default_timezone_set('Asia/Manila');

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

$tenant_id      = $_GET['tenant_id']      ?? '';
$dentist_id     = $_GET['dentist_id']     ?? '';
$date           = $_GET['date']           ?? '';
$total_duration = isset($_GET['total_duration']) ? (int)$_GET['total_duration'] : 30;

if (empty($tenant_id)  || !is_numeric($tenant_id)  ||
    empty($dentist_id) || !is_numeric($dentist_id) ||
    empty($date)       || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Valid tenant_id, dentist_id, and date are required']);
    exit;
}

// Clamp duration to sensible range
$total_duration = max(15, min($total_duration, 240));
$buffer_minutes = 15; // sanitation buffer between appointments
$full_window    = $total_duration + $buffer_minutes; // total time block needed
$slot_step      = 30; // grid granularity in minutes

$day_of_week = date('l', strtotime($date));

// 1. Fetch dentist schedule for this day
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
    echo json_encode([
        'success' => true,
        'message' => 'No schedule for this dentist on ' . $day_of_week,
        'slots'   => [],
    ]);
    exit;
}

// 2. Fetch all existing booked appointments for this dentist/date
//    including their total_duration so we can compute their occupied window.
$stmt = $conn->prepare("
    SELECT
        appointment_time,
        COALESCE(total_duration_minutes, 30) AS duration_minutes
    FROM appointment
    WHERE dentist_id       = ?
      AND tenant_id        = ?
      AND appointment_date = ?
      AND status NOT IN ('cancelled', 'no_show', 'declined')
");
$stmt->bind_param("iis", $dentist_id, $tenant_id, $date);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Build list of booked windows: [start_ts, end_ts]
// end_ts = appointment_time + duration + 15min buffer
$booked_windows = [];
foreach ($rows as $row) {
    if (!$row['appointment_time']) continue;
    $a_start = strtotime($date . ' ' . $row['appointment_time']);
    $a_end   = $a_start + (((int)$row['duration_minutes'] + $buffer_minutes) * 60);
    $booked_windows[] = [$a_start, $a_end];
}

// 3. Generate candidate slots and evaluate availability
$sched_start  = strtotime($date . ' ' . $schedule['start_time']);
$sched_end    = strtotime($date . ' ' . $schedule['end_time']);
$is_today     = ($date === date('Y-m-d'));
$min_allowed  = time() + (1 * 60 * 60); // 1-hour buffer for same-day

$slots = [];
$cursor = $sched_start;

while ($cursor < $sched_end) {
    $slot_end_ts = $cursor + ($full_window * 60);

    // Rule 1: entire window must fit within schedule
    $fits_schedule = ($slot_end_ts <= $sched_end);

    // Rule 2: same-day 1hr buffer
    $too_soon = $is_today && ($cursor < $min_allowed);

    // Rule 3: no overlap with any existing appointment window
    $has_overlap = false;
    if ($fits_schedule && !$too_soon) {
        foreach ($booked_windows as [$bk_start, $bk_end]) {
            // Overlap if: cursor < bk_end AND slot_end_ts > bk_start
            if ($cursor < $bk_end && $slot_end_ts > $bk_start) {
                $has_overlap = true;
                break;
            }
        }
    }

    $slots[] = [
        'time'      => date('H:i', $cursor),
        'label'     => date('g:i A', $cursor),
        'available' => $fits_schedule && !$too_soon && !$has_overlap,
    ];

    $cursor = strtotime("+{$slot_step} minutes", $cursor);
}

echo json_encode([
    'success'          => true,
    'message'          => 'Slots fetched successfully',
    'day'              => $day_of_week,
    'schedule'         => [
        'start' => $schedule['start_time'],
        'end'   => $schedule['end_time'],
    ],
    'total_duration'   => $total_duration,
    'buffer_minutes'   => $buffer_minutes,
    'slots'            => $slots,
]);
?>