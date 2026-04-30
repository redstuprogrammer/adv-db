<?php
require_once __DIR__ . '/includes/connect.php';
require_once 'includes/subscription_tiers.php';

// 1. LIVE CONFIGURATION
$is_live = false; 

// Load secret from Environment Variable (Azure) or Local Config (XAMPP)
$secret_key = getenv('PAYMONGO_SECRET_KEY');
if (!$secret_key && file_exists(__DIR__ . '/config/paymongo.php')) {
    $paymongo_config = require __DIR__ . '/config/paymongo.php';
    $secret_key = $paymongo_config['secret_key'] ?? '';
}

// Fallback for immediate fix (obfuscated to bypass simple scanner if absolutely needed, 
// but preferred to use the config file approach below)
if (!$secret_key) {
    // Note: Set your key in config/paymongo.php or as an environment variable
    die("Error: PayMongo Secret Key not configured. Please set PAYMONGO_SECRET_KEY.");
}

$auth_header = base64_encode($secret_key . ":");

// Your Azure domain from user request
$azure_url = "https://oralsync3-g6hpg2fhdyfuagdy.eastasia-01.azurewebsites.net"; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: subscription_checkout.php");
    exit;
}

$tenant_id = $_POST['tenant_id'];
$tier_key = $_POST['tier_key'];
$source = $_POST['source'] ?? 'web';

// Validate tier and get price
$tier = getTierByKey($tier_key);
if (!$tier) {
    die("Invalid subscription plan selected.");
}

$amount_val = (float)$tier['price_max'];
$procedures_meta = json_encode([
    "item" => "Platform Subscription Renewal",
    "tier_key" => $tier_key
]);

// 2. Create Checkout Session with Redirects
$payload = [
    "data" => [
        "attributes" => [
            "payment_method_types" => ["gcash", "card", "paymaya", "qris"],
            "success_url" => $azure_url . "/dashboard.php?payment=success",
            "cancel_url" => $azure_url . "/subscription_checkout.php",
            "description" => "OralSync Subscription Renewal - " . $tier['name'],
            "line_items" => [[
                "currency" => "PHP",
                "amount" => (int)($amount_val * 100),
                "description" => "Subscription: " . $tier['name'],
                "name" => "Tenant ID: " . $tenant_id,
                "quantity" => 1
            ]],
            "metadata" => [
                "tenant_id" => (string)$tenant_id,
                "tier_key" => $tier_key,
                "type" => "subscription"
            ]
        ]
    ]
];

$ch = curl_init("https://api.paymongo.com/v1/checkout_sessions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Basic " . $auth_header
]);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    die("cURL Error: " . $err);
}

$data_resp = json_decode($response, true);

if (isset($data_resp['errors'])) {
    die("PayMongo Error: " . print_r($data_resp['errors'], true));
}

$paymongo_id = $data_resp['data']['id'];
$checkout_url = $data_resp['data']['attributes']['checkout_url'];

// 3. Database Entry
try {
    // Note: Using 'payment' table as per system requirements
    $sql = "INSERT INTO payment (
                tenant_id, amount, status, mode, 
                source, procedures_json, payment_type, 
                paymongo_link_id, payment_date
            ) VALUES (?, ?, 'pending', 'Online', ?, ?, 'full', ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idsss", $tenant_id, $amount_val, $source, $procedures_meta, $paymongo_id);
    $stmt->execute();

    header("Location: " . $checkout_url);
    exit;
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}