<?php
// ============================================================
// FILE TYPE: API ENDPOINT — deploy to server
// PATH on server: /api/create_paymongo_link.php
// ============================================================
// Uses checkout_sessions (better test mode support than links).
// Matches approach confirmed in pay.php from your groupmate.
//
// POST JSON body:
//   tenant_id      (int,    required)
//   billing_id     (int,    required) — the billing row to pay
//   patient_id     (int,    required)
//   amount         (float,  required) — in PHP pesos e.g. 500.00
//   description    (string, optional)
//
// Returns:
//   { success, checkout_url, session_id, reference_number }
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

$body = json_decode(file_get_contents('php://input'), true);

$tenant_id   = $body['tenant_id']   ?? null;
$billing_id  = $body['billing_id']  ?? null;
$patient_id  = $body['patient_id']  ?? null;
$amount      = $body['amount']      ?? null;
$description = $body['description'] ?? 'Dental Payment - OralSync';

if (!$tenant_id || !$billing_id || !$patient_id || $amount === null) {
    echo json_encode(['success' => false, 'message' => 'tenant_id, billing_id, patient_id, and amount are required']);
    exit;
}

// ─── PayMongo secret key (from env or config) ────────────
$secret = getenv('PAYMONGO_SECRET_KEY');
if (!$secret && file_exists(__DIR__ . '/../config/paymongo.php')) {
    $paymongo_config = require __DIR__ . '/../config/paymongo.php';
    $secret = $paymongo_config['secret_key'] ?? '';
}
if (!$secret) {
    echo json_encode(['success' => false, 'message' => 'PayMongo secret key not configured']);
    exit;
}
$auth = base64_encode($secret . ':');

// ─── Generate reference number ────────────────────────────
$reference_number = 'MOB-' . $patient_id . '-' . time();

// ─── Amount in centavos (pesos × 100) ────────────────────
$amount_centavos = (int) round($amount * 100);

// ─── Redirect URLs — WebView intercepts these ─────────────
$base_url    = 'https://oralsync3-g6hpg2fhdyfuagdy.eastasia-01.azurewebsites.net/api';
$success_url = $base_url . '/payment_return.php?status=success'
    . '&ref='     . urlencode($reference_number)
    . '&bill_id=' . intval($billing_id);
$failed_url  = $base_url . '/payment_return.php?status=failed'
    . '&ref='     . urlencode($reference_number);

// ─── Build checkout_sessions payload ─────────────────────
$data = json_encode([
    'data' => [
        'attributes' => [
            'payment_method_types' => ['gcash', 'card', 'paymaya'],
            'line_items' => [[
                'currency'    => 'PHP',
                'amount'      => $amount_centavos,
                'description' => $description,
                'name'        => 'OralSync - ' . $description,
                'quantity'    => 1,
            ]],
            'description' => 'OralSync Dental Payment',
            'redirect'    => [
                'success' => $success_url,
                'failed'  => $failed_url,
            ],
        ],
    ],
]);

// ─── Call PayMongo API ────────────────────────────────────
$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);

$pm_response = curl_exec($ch);
$http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error  = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo json_encode(['success' => false, 'message' => 'cURL error: ' . $curl_error]);
    exit;
}

$pm_data = json_decode($pm_response, true);

if ($http_code !== 200 || !isset($pm_data['data'])) {
    $pm_error = $pm_data['errors'][0]['detail'] ?? ('PayMongo returned HTTP ' . $http_code);
    echo json_encode(['success' => false, 'message' => $pm_error]);
    exit;
}

$session_id   = $pm_data['data']['id'];
$checkout_url = $pm_data['data']['attributes']['checkout_url'];

// ─── Store session_id + reference on billing row ──────────
// Allows confirm endpoint to verify and reconcile later
$upd = $conn->prepare("
    UPDATE billing
    SET paymongo_session_id = ?, reference_number = ?
    WHERE billing_id = ? AND tenant_id = ?
");
$upd->bind_param("ssii", $session_id, $reference_number, $billing_id, $tenant_id);
$upd->execute();
$upd->close();
$conn->close();

echo json_encode([
    'success'          => true,
    'checkout_url'     => $checkout_url,
    'session_id'       => $session_id,
    'reference_number' => $reference_number,
    'message'          => 'Checkout session created successfully.',
]);
?>