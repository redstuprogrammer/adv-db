<?php
/**
 * OralSync Live Webhook Receiver
 * Location: /webhook.php
 */
require_once __DIR__ . '/includes/connect.php';

// 1. Capture the incoming JSON from PayMongo
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!$data || !isset($data['data']['attributes']['type'])) {
    http_response_code(400);
    exit("Invalid Payload");
}

$event_type = $data['data']['attributes']['type'];
$session_obj = $data['data']['attributes']['data']['attributes'];
$session_id = $data['data']['attributes']['data']['id']; 

// 2. Process only the 'paid' event
if ($event_type === 'checkout_session.payment.paid') {
    try {
        $payment_id_final = $session_obj['payments'][0]['id'] ?? 'LIVE_PAYMENT';
        
        // Metadata extraction
        $metadata = $session_obj['metadata'] ?? [];
        $tenant_id = $metadata['tenant_id'] ?? null;
        $tier_key = $metadata['tier_key'] ?? null;
        $amount_paid = $session_obj['line_items'][0]['amount'] / 100;

        if ($tenant_id) {
            // A. Update Payment Record
            $sql_pay = "UPDATE payment 
                        SET status = 'paid', 
                            paymongo_payment_id = ? 
                        WHERE paymongo_link_id = ? AND status = 'pending'";
            $stmt_pay = $conn->prepare($sql_pay);
            $stmt_pay->bind_param("ss", $payment_id_final, $session_id);
            $stmt_pay->execute();

            // B. Update Tenant Subscription
            if ($tier_key) {
                $sql_tenant = "UPDATE tenants 
                               SET subscription_tier = ?, 
                                   subscription_start_date = NOW(),
                                   status = 'active'
                               WHERE tenant_id = ?";
                $stmt_tenant = $conn->prepare($sql_tenant);
                $stmt_tenant->bind_param("si", $tier_key, $tenant_id);
                $stmt_tenant->execute();

                // C. Log Revenue (Match with payment table)
                $procedures_json = json_encode([
                    'item' => 'Subscription Renewal',
                    'tier' => $tier_key,
                    'billing_period_start' => date('Y-m-d H:i:s')
                ]);
                
                $sql_rev = "INSERT INTO payment (
                                tenant_id, amount, status, payment_date, procedures_json
                            ) VALUES (?, ?, 'paid', NOW(), ?)";
                $stmt_rev = $conn->prepare($sql_rev);
                $stmt_rev->bind_param("ids", $tenant_id, $amount_paid, $procedures_json);
                $stmt_rev->execute();
            }

            error_log("Subscription Success: Tenant $tenant_id, Session $session_id");
        }

    } catch (Exception $e) {
        error_log("Webhook SQL Error: " . $e->getMessage());
        http_response_code(500);
        exit;
    }
}

// Always return 200 to acknowledge receipt
http_response_code(200);