<?php
// ============================================================
// FILE TYPE: API ENDPOINT
// PATH on server: /api/apply_discount.php
// ============================================================
// POST JSON body:
//   billing_id    (int,    required)
//   tenant_id     (int,    required)
//   patient_id    (int,    required)
//   discount_type (string, required) — 'PWD' | 'Senior' | 'None'
//
// What it does:
//   - Saves original_amount if not already saved
//   - Applies 20% discount (PH law mandated for PWD and Senior)
//   - Updates total_amount, discount_amount, discount_type on billing
//   - Returns new total_amount
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

$body          = json_decode(file_get_contents('php://input'), true);
$billing_id    = $body['billing_id']    ?? null;
$tenant_id     = $body['tenant_id']     ?? null;
$patient_id    = $body['patient_id']    ?? null;
$discount_type = strtolower($body['discount_type'] ?? 'none');

if (!$billing_id || !$tenant_id || !$patient_id) {
    echo json_encode(['success' => false, 'message' => 'billing_id, tenant_id, and patient_id are required.']);
    exit;
}

// Fetch current billing row
$chk = $conn->prepare("
    SELECT total_amount, original_amount, payment_status
    FROM billing
    WHERE billing_id = ? AND tenant_id = ? AND patient_id = ?
    LIMIT 1
");
$chk->bind_param("iii", $billing_id, $tenant_id, $patient_id);
$chk->execute();
$bill = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$bill) {
    echo json_encode(['success' => false, 'message' => 'Billing record not found.']);
    $conn->close(); exit;
}

if ($bill['payment_status'] === 'paid') {
    echo json_encode(['success' => false, 'message' => 'Cannot apply discount to an already paid bill.']);
    $conn->close(); exit;
}

// Use saved original_amount if exists; otherwise save current total_amount as original
$original = $bill['original_amount'] !== null
    ? (float) $bill['original_amount']
    : (float) $bill['total_amount'];

// Calculate discount
$discount_rate   = 0.00;
$discount_label  = null;

if (in_array($discount_type, ['pwd', 'senior'])) {
    $discount_rate  = 0.20; // 20% mandated by PH law
    $discount_label = $discount_type === 'pwd' ? 'PWD' : 'Senior';
}

$discount_amount = round($original * $discount_rate, 2);
$new_total       = round($original - $discount_amount, 2);

// Update billing row
$upd = $conn->prepare("
    UPDATE billing
    SET original_amount = ?,
        discount_type   = ?,
        discount_amount = ?,
        total_amount    = ?
    WHERE billing_id = ? AND tenant_id = ?
");
$upd->bind_param("dsddi i", $original, $discount_label, $discount_amount, $new_total, $billing_id, $tenant_id);

if (!$upd->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to apply discount: ' . $upd->error]);
    $upd->close(); $conn->close(); exit;
}
$upd->close();
$conn->close();

echo json_encode([
    'success'         => true,
    'message'         => $discount_label
        ? "20% {$discount_label} discount applied."
        : 'No discount applied.',
    'original_amount' => $original,
    'discount_type'   => $discount_label,
    'discount_amount' => $discount_amount,
    'new_total'       => $new_total,
]);
?>