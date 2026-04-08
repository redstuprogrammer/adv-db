<?php
// ============================================================
// FILE TYPE: API ENDPOINT — send to groupmate for deployment
// PATH on server: /api/get_dentists_by_date.php
// ============================================================
// GET params:
//   tenant_id  (int, required)
//   date       (string YYYY-MM-DD, required)
//
// Returns dentists who have is_available=1 on that day of week
// AND still have at least 1 open 30-min slot (not fully booked).
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

$tenant_id = $_GET['tenant_id'] ?? '';
$date      = $_GET['date']      ?? '';

if (empty($tenant_id) || !is_numeric($tenant_id) ||
    empty($date)      || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Valid tenant_id and date (YYYY-MM-DD) are required']);
    exit;
}

$day_of_week = date('l', strtotime($date)); // e.g. "Wednesday"

// Get dentists scheduled + available on this day of week
// Exclude rows where start_time = end_time (those are "off" entries like 00:00-00:00)
$stmt = $conn->prepare("
    SELECT
        d.dentist_id,
        CONCAT(d.first_name, ' ', d.last_name) AS full_name,
        d.first_name,
        d.last_name,
        ds.start_time,
        ds.end_time
    FROM dentist d
    INNER JOIN dentist_schedule ds
        ON  ds.dentist_id   = d.dentist_id
        AND ds.tenant_id    = ?
        AND ds.day_of_week  = ?
        AND ds.is_available = 1
        AND ds.start_time  != ds.end_time
    WHERE d.tenant_id = ?
    ORDER BY d.first_name
");
$stmt->bind_param("isi", $tenant_id, $day_of_week, $tenant_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$slot_duration = 30;
$dentists = [];

foreach ($rows as $row) {
    // Total 30-min slots in this dentist's schedule today
    $start = strtotime($row['start_time']);
    $end   = strtotime($row['end_time']);
    $total = 0;
    $cur   = $start;
    while ($cur < $end) { $total++; $cur = strtotime("+{$slot_duration} minutes", $cur); }

    // Already booked non-cancelled slots on this specific date
    $chk = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM appointment
        WHERE dentist_id       = ?
          AND tenant_id        = ?
          AND appointment_date = ?
          AND status NOT IN ('cancelled')
    ");
    $chk->bind_param("iis", $row['dentist_id'], $tenant_id, $date);
    $chk->execute();
    $booked = (int)$chk->get_result()->fetch_assoc()['cnt'];
    $chk->close();

    // Only show dentist if they still have open slots
    if ($booked < $total) {
        $dentists[] = [
            'dentist_id'     => (int)$row['dentist_id'],
            'full_name'      => $row['full_name'],
            'first_name'     => $row['first_name'],
            'last_name'      => $row['last_name'],
            'schedule_today' => date('g:i A', strtotime($row['start_time']))
                              . ' – '
                              . date('g:i A', strtotime($row['end_time'])),
        ];
    }
}

echo json_encode([
    'success'  => true,
    'message'  => count($dentists) ? 'Dentists fetched successfully' : 'No dentists available on this date',
    'day'      => $day_of_week,
    'date'     => $date,
    'dentists' => $dentists,
]);

$conn->close();
?>