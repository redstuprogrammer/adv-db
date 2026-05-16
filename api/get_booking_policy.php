<?php
// ============================================================
// FILE TYPE: API ENDPOINT — deploy to server
// PATH on server: /api/get_booking_policy.php
// ============================================================
// Called when the patient reaches Step 4 (Review) of the
// booking wizard so the app can display:
//   - deposit amount before they commit
//   - cancellation window deadline
//
// GET params:
//   tenant_id  (int, required)
//
// Returns:
//   {
//     success: true,
//     deposit_amount:      float|null,   -- null = no deposit required
//     has_deposit:         bool,
//     cancellation_hours:  int,          -- hours before slot (default 24)
//     cancellation_policy: string        -- human-readable summary
//   }
//
// Config keys read from tenant_configs table:
//   'booking_deposit_amount'  -- e.g. "500.00"  (NULL / absent = no deposit)
//   'cancellation_hours'      -- e.g. "24"      (default: 24)
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

// ─── Fetch all relevant config keys in one query ──────────
$stmt = $conn->prepare("
    SELECT config_key, config_value
    FROM tenant_configs
    WHERE tenant_id  = ?
      AND config_key IN ('booking_deposit_amount', 'cancellation_hours')
");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Map to key → value
$config = [];
foreach ($rows as $row) {
    $config[$row['config_key']] = $row['config_value'];
}

// ─── Derive values with safe defaults ────────────────────
$raw_deposit = $config['booking_deposit_amount'] ?? null;
$deposit_amount = ($raw_deposit !== null && $raw_deposit !== '') ? (float)$raw_deposit : null;
$has_deposit    = $deposit_amount !== null && $deposit_amount > 0;

$cancellation_hours = isset($config['cancellation_hours']) && $config['cancellation_hours'] !== ''
    ? (int)$config['cancellation_hours']
    : 24; // default: 24 hours

// Human-readable policy summary
if ($cancellation_hours >= 48) {
    $window_text = '2 days';
} elseif ($cancellation_hours === 24) {
    $window_text = '24 hours';
} else {
    $window_text = $cancellation_hours . ' hours';
}

$cancellation_policy = "You can cancel up to {$window_text} before your appointment. "
                     . "Cancellations past this window are not allowed.";

if ($has_deposit) {
    $formatted = number_format($deposit_amount, 2);
    $cancellation_policy .= " If you cancel within the window after paying a deposit, "
                          . "a refund will be processed by the clinic.";
}

echo json_encode([
    'success'             => true,
    'deposit_amount'      => $deposit_amount,
    'has_deposit'         => $has_deposit,
    'cancellation_hours'  => $cancellation_hours,
    'cancellation_policy' => $cancellation_policy,
]);
?>