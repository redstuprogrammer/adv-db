<?php
// ============================================================
// FILE TYPE: API ENDPOINT — send to groupmate for deployment
// PATH on server: /api/get_dentists.php
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Only GET requests allowed']);
    exit;
}

$tenant_id = $_GET['tenant_id'] ?? '';

if (empty($tenant_id) || !is_numeric($tenant_id)) {
    echo json_encode(['success' => false, 'message' => 'A valid tenant_id is required']);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        d.dentist_id,
        CONCAT(d.first_name, ' ', d.last_name) AS full_name,
        d.first_name,
        d.last_name,
        GROUP_CONCAT(
            DISTINCT CONCAT(ds.day_of_week, ' ', ds.start_time, '-', ds.end_time)
            ORDER BY FIELD(ds.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')
            SEPARATOR ', '
        ) AS schedule_summary
    FROM dentist d
    LEFT JOIN dentist_schedule ds
        ON d.dentist_id = ds.dentist_id AND ds.is_available = 1
    WHERE d.tenant_id = ?
    GROUP BY d.dentist_id, d.first_name, d.last_name
    ORDER BY d.first_name
");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

$dentists = [];
while ($row = $result->fetch_assoc()) {
    $dentists[] = $row;
}

echo json_encode([
    'success'  => true,
    'message'  => 'Dentists fetched successfully',
    'dentists' => $dentists
]);

$stmt->close();
$conn->close();
?>