<?php
// ============================================================
// FILE TYPE: API ENDPOINT — deploy to server
// PATH on server: /api/save_deposit_config.php
// ============================================================
// POST JSON body:
//   tenant   (string, optional if session exists)
//   booking_deposit_amount  (float, required)
//
// Saves the clinic booking downpayment amount into tenant_configs.
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/tenant_utils.php';
require_once __DIR__ . '/../includes/session_utils.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);
$tenantId = getCurrentTenantId();

if (!$tenantId || $tenantId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Tenant context is required.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$amount = $body['booking_deposit_amount'] ?? null;

if ($amount === null || $amount === '') {
    echo json_encode(['success' => false, 'message' => 'booking_deposit_amount is required.']);
    exit;
}

if (!is_numeric($amount) || (float)$amount < 0) {
    echo json_encode(['success' => false, 'message' => 'booking_deposit_amount must be a non-negative number.']);
    exit;
}

$amount = round((float)$amount, 2);

if (!saveTenantConfig($tenantId, ['booking_deposit_amount' => $amount])) {
    echo json_encode(['success' => false, 'message' => 'Unable to save deposit configuration.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Clinic downpayment saved successfully.',
    'booking_deposit_amount' => $amount,
]);
exit;
