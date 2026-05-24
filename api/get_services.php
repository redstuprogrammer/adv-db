<?php
// ============================================================
// FILE: /api/get_services.php
// ============================================================
// GET ?tenant_id=X
// Returns all services for a tenant including duration_minutes.
// Used by BookingScreen to populate the service cart.
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

if (empty($tenant_id) || !is_numeric($tenant_id)) {
    echo json_encode(['success' => false, 'message' => 'Valid tenant_id is required']);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        service_id,
        service_name,
        description,
        CAST(price AS DECIMAL(10,2))    AS price,
        duration_minutes,
        category
    FROM service
    WHERE tenant_id = ?
    ORDER BY category ASC, service_name ASC
");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

$services = [];
while ($row = $result->fetch_assoc()) {
    $services[] = [
        'service_id'       => (int)    $row['service_id'],
        'service_name'     =>           $row['service_name'],
        'description'      =>           $row['description'],
        'price'            => (float)   $row['price'],
        'duration_minutes' => (int)     $row['duration_minutes'],
        'category'         =>           $row['category'],
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success'  => true,
    'message'  => count($services) ? 'Services fetched successfully' : 'No services found',
    'services' => $services,
]);
?>