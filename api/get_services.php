<?php
// ============================================================
// FILE TYPE: API ENDPOINT — send to groupmate for deployment
// PATH on server: /api/get_services.php
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
    SELECT service_id, service_name, description, price, category
    FROM service
    WHERE tenant_id = ?
    ORDER BY category, service_name
");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

$services = [];
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}

echo json_encode([
    'success'  => true,
    'message'  => 'Services fetched successfully',
    'services' => $services
]);

$stmt->close();
$conn->close();
?>