<?php
require "connect.php"; // Ensure this file defines $pdo

// 1. Credentials - Use environment variables for security
$secret = getenv('PAYMONGO_SECRET_KEY') ?: "REPLACE_WITH_YOUR_KEY"; 
$auth = base64_encode($secret . ":");

// 2. Test Data
$tenant_id = 1;
$appointment_id = 99; // Test ID
$amount = 100.00;     // PHP 100.00

// 3. Prepare Data for Checkout Session (The reliable way for Test Mode)
$data = [
    "data" => [
        "attributes" => [
            "payment_method_types" => ["gcash", "card", "paymaya"],
            "line_items" => [
                [
                    "currency" => "PHP",
                    "amount" => $amount * 100, // 10000 centavos
                    "description" => "Test Appointment Payment",
                    "name" => "Dental Service Test",
                    "quantity" => 1
                ]
            ],
            "description" => "OralSync Test Transaction"
        ]
    ]
];

// 4. Hit the Checkout Sessions Endpoint
$ch = curl_init("https://api.paymongo.com/v1/checkout_sessions");

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic $auth",
    "Content-Type: application/json",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

// 5. Handle Errors
if (!isset($result['data'])) {
    die("PayMongo Error: " . $response);
}

// 6. Extract Session Info
$session_id = $result['data']['id'];
$checkout_url = $result['data']['attributes']['checkout_url'];

// 7. Insert into Database
try {
    $stmt = $pdo->prepare("
        INSERT INTO billing 
        (tenant_id, appointment_id, amount_paid, total_amount, payment_status, mode, source, payment_type, paymongo_link_id)
        VALUES (?, ?, ?, ?, 'pending', 'online', 'web', 'full', ?)
    ");

    $stmt->execute([
        $tenant_id,
        $appointment_id,
        $amount,
        $amount,
        $session_id // Storing Session ID for reconciliation
    ]);

    // 8. Redirect to the PayMongo Mock Payment Page
    header("Location: " . $checkout_url);
    exit;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}