<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../connect.php';

$tenant_id = $_GET['tenant_id'] ?? '';
date_default_timezone_set('UTC');
$date = $_GET['date'] ?? '';

if (empty($tenant_id) || !is_numeric($tenant_id) || empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Valid tenant_id and date (YYYY-MM-DD) are required.']);
    exit;
}

$day_of_week = date('l', strtotime($date));

$stmt = $conn->prepare("SELECT is_closed FROM clinic_schedules WHERE tenant_id = ? AND day_of_week = ? LIMIT 1");
$stmt->bind_param('is', $tenant_id, $day_of_week);
$stmt->execute();
$clinicRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$clinicRow) {
    echo json_encode(['success' => true, 'clinic_closed' => false, 'dentists' => [], 'message' => 'Clinic schedule has not been configured for ' . $day_of_week . '.']);
    exit;
}

if ((int)$clinicRow['is_closed'] === 1) {
    echo json_encode(['success' => true, 'clinic_closed' => true, 'dentists' => [], 'message' => 'Clinic is closed on ' . $day_of_week . '.']);
    exit;
}

$stmt = $conn->prepare("SELECT d.dentist_id, d.first_name, d.last_name, ds.start_time, ds.end_time
    FROM dentist_schedule ds
    JOIN dentist d ON ds.dentist_id = d.dentist_id
    WHERE ds.tenant_id = ? AND ds.day_of_week = ? AND ds.is_available = 1 AND ds.start_time != ds.end_time
    ORDER BY d.last_name, d.first_name");
$stmt->bind_param('is', $tenant_id, $day_of_week);
$stmt->execute();
$result = $stmt->get_result();
$dentists = [];
while ($row = $result->fetch_assoc()) {
    $dentists[] = [
        'dentist_id' => (int)$row['dentist_id'],
        'first_name' => $row['first_name'] ?? '',
        'last_name' => $row['last_name'] ?? '',
        'start_time' => $row['start_time'],
        'end_time' => $row['end_time'],
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'clinic_closed' => false,
    'dentists' => $dentists,
    'message' => 'Available dentists loaded successfully.'
]);

$conn->close();
