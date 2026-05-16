<?php
// ============================================================
// API ENDPOINT: get_monthly_availability.php
// Returns availability summary for a clinic/dentist for a specific month
// ============================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../connect.php';

$tenantId = $_GET['tenant_id'] ?? '';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$dentistId = $_GET['dentist_id'] ?? null;

if (empty($tenantId)) {
    echo json_encode(['success' => false, 'message' => 'Tenant ID is required']);
    exit;
}

// 1. Get Clinic Closed Days
$clinicClosedDays = [];
$stmt = $conn->prepare("SELECT day_of_week FROM clinic_schedules WHERE tenant_id = ? AND is_closed = 1");
$stmt->bind_param('i', $tenantId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $clinicClosedDays[] = $row['day_of_week'];
}
$stmt->close();

// 2. Get Dentist Working Days (if dentist selected)
$dentistWorkingDays = [];
if ($dentistId) {
    $stmt = $conn->prepare("SELECT day_of_week FROM dentist_schedule WHERE tenant_id = ? AND dentist_id = ? AND is_available = 1");
    $stmt->bind_param('ii', $tenantId, $dentistId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $dentistWorkingDays[] = $row['day_of_week'];
    }
    $stmt->close();
}

// 3. Get Appointment Counts per Day (to detect full days)
$appointmentCounts = [];
$monthStr = sprintf('%04d-%02d', $year, $month);
$stmt = $conn->prepare("SELECT appointment_date, COUNT(*) as count FROM appointment WHERE tenant_id = ? AND appointment_date LIKE ? AND status NOT IN ('Cancelled', 'Disapproved') " . ($dentistId ? "AND dentist_id = ?" : "") . " GROUP BY appointment_date");
$likeMonth = $monthStr . '-%';
if ($dentistId) {
    $stmt->bind_param('isi', $tenantId, $likeMonth, $dentistId);
} else {
    $stmt->bind_param('is', $tenantId, $likeMonth);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $appointmentCounts[$row['appointment_date']] = (int)$row['count'];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'clinic_closed_days' => $clinicClosedDays,
    'dentist_working_days' => $dentistWorkingDays,
    'appointment_counts' => $appointmentCounts,
    'dentist_id' => $dentistId
]);
?>
