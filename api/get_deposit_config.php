<?php
// ============================================================
// FILE TYPE: API ENDPOINT — deploy to server
// PATH on server: /api/get_deposit_config.php
// ============================================================
// GET params:
//   tenant_id  (int, required)
//
// Returns the booking_deposit_amount set by the clinic.
// If NULL or not set → no deposit required for this clinic.
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
    SELECT booking_deposit_amount
    FROM tenant_configs
    WHERE tenant_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$amount = $row ? $row['booking_deposit_amount'] : null;

echo json_encode([
    'success'         => true,
    'deposit_required' => !is_null($amount) && $amount > 0,
    'deposit_amount'  => $amount ? (float)$amount : null,
]);
?>