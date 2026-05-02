<?php
// ============================================================
// FILE TYPE: API ENDPOINT
// PATH on server: /api/create_paymongo_link.php
// ============================================================
// FIXES:
//   1. Added ob_start() + register_shutdown_function to catch
//      silent PHP fatal errors that were causing HTTP 500
//      with empty body (Azure kills the process on timeout,
//      nothing gets sent back to the mobile app).
//   2. Added CURLOPT_TIMEOUT + CURLOPT_CONNECTTIMEOUT so a
//      slow PayMongo response doesn't hang until PHP's
//      execution limit kills the script.
//   3. Added set_time_limit(60) for safety on Azure.
//   4. Added CURLOPT_SSL_VERIFYPEER = true (explicit, safer).
// ============================================================

ob_start(); // Buffer all output so shutdown handler can clean up

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Internal server error. Please try again.',
            'debug'   => $error['message'],
            'file'    => basename($error['file']),
            'line'    => $error['line'],
        ]);
    }
    ob_end_flush();
});

set_time_limit(60);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../connect.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

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

$pm_config = require_once __DIR__ . '/../config/paymongo.php';
$secret    = $pm_config['secret_key'] ?? '';
$auth      = base64_encode($secret . ':');

$reference_number = 'MOB-' . $patient_id . '-' . time();
$amount_centavos  = (int) round($amount * 100);

$base_url = 'https://oralsync3-g6hpg2fhdyfuagdy.eastasia-01.azurewebsites.net/api';

$success_url_temp = $base_url . '/payment_return.php?status=success'
    . '&ref='     . urlencode($reference_number)
    . '&bill_id=' . intval($billing_id)
    . '&session_id=PLACEHOLDER';

$failed_url = $base_url . '/payment_return.php?status=failed'
    . '&ref=' . urlencode($reference_number);

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
                'success' => $success_url_temp,
                'failed'  => $failed_url,
            ],
        ],
    ],
]);

// ─── cURL with explicit timeouts to prevent Azure silent crash ──
$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
curl_setopt_array($ch, [
    CURLOPT_POST            => true,
    CURLOPT_POSTFIELDS      => $data,
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_CONNECTTIMEOUT  => 10,   // fail fast if PayMongo unreachable
    CURLOPT_TIMEOUT         => 30,   // max 30s for the full request
    CURLOPT_SSL_VERIFYPEER  => true,
    CURLOPT_SSL_VERIFYHOST  => 2,
    CURLOPT_HTTPHEADER      => [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);

$pm_response = curl_exec($ch);
$http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_errno  = curl_errno($ch);
$curl_error  = curl_error($ch);
curl_close($ch);

if ($curl_errno || !$pm_response) {
    error_log("[create_paymongo_link] cURL error #{$curl_errno}: {$curl_error}");
    // Surface a user-friendly error instead of crashing
    echo json_encode([
        'success' => false,
        'message' => 'Could not reach the payment provider. Please check your internet connection and try again.',
        'debug'   => "cURL #{$curl_errno}: {$curl_error}",
    ]);
    exit;
}

error_log("[create_paymongo_link] PayMongo HTTP={$http_code} body=" . substr($pm_response, 0, 300));

$pm_data = json_decode($pm_response, true);

if ($http_code !== 200 || !isset($pm_data['data'])) {
    $pm_error = $pm_data['errors'][0]['detail'] ?? ('PayMongo returned HTTP ' . $http_code);
    error_log("[create_paymongo_link] PayMongo error: {$pm_error}");
    echo json_encode(['success' => false, 'message' => $pm_error]);
    exit;
}

$session_id   = $pm_data['data']['id'];
$checkout_url = $pm_data['data']['attributes']['checkout_url'];

// ─── Store session_id + reference on billing row ──────────
$upd = $conn->prepare("
    UPDATE billing
    SET paymongo_session_id = ?, reference_number = ?
    WHERE billing_id = ? AND tenant_id = ?
");
if (!$upd) {
    error_log("[create_paymongo_link] DB prepare error: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . $conn->error]);
    exit;
}
$upd->bind_param("ssii", $session_id, $reference_number, $billing_id, $tenant_id);
$upd->execute();
$affected = $upd->affected_rows;
$upd->close();
$conn->close();

if ($affected === 0) {
    error_log("[create_paymongo_link] WARNING: 0 rows updated. billing_id={$billing_id} tenant_id={$tenant_id}");
}

echo json_encode([
    'success'          => true,
    'checkout_url'     => $checkout_url,
    'session_id'       => $session_id,
    'reference_number' => $reference_number,
    'message'          => 'Checkout session created successfully.',
]);
?>