<?php
// ============================================================
// FILE TYPE: API ENDPOINT — deploy to server
// PATH on server: /api/get_deposit_config.php
// ============================================================
// GET ?tenant_id=X
//
// Returns the deposit setting for a clinic from tenant_configs.
// booking_deposit_amount = NULL  → no deposit required
// booking_deposit_amount = 200   → ₱200 deposit required at booking
//
// Returns:
//   { success, deposit_required: bool, deposit_amount: float|null }
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

$tenant_id = $_GET['tenant_id'] ?? null;

if (!$tenant_id || !is_numeric($tenant_id)) {
    echo json_encode(['success' => false, 'message' => 'A valid tenant_id is required.']);
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
$stmt->bind_result($deposit_amount);
$found = $stmt->fetch();
$stmt->close();
$conn->close();

if (!$found) {
    // No config row found — treat as no deposit required
    echo json_encode([
        'success'          => true,
        'deposit_required' => false,
        'deposit_amount'   => null,
    ]);
    exit;
}

echo json_encode([
    'success'          => true,
    'deposit_required' => $deposit_amount !== null,
    'deposit_amount'   => $deposit_amount !== null ? (float) $deposit_amount : null,
]);
?>