<?php
require_once 'connect.php';

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!$data || !isset($data['data']['attributes']['type'])) {
    http_response_code(400);
    exit("Invalid Payload");
}

$event_type = $data['data']['attributes']['type'];
// Extract the Checkout Session ID (the 'cs_' ID)
$session_id = $data['data']['attributes']['data']['id']; 

if ($event_type === 'checkout_session.payment.paid') {
    try {
        // Extract the actual Payment ID (the 'pay_' or 'pi_' ID)
        $payment_id_final = $data['data']['attributes']['data']['attributes']['payments'][0]['id'] ?? 'LIVE_PAYMENT';
        
        // Use your specific column names: paymongo_link_id and status
        $sql = "UPDATE payment 
                SET status = 'paid', 
                    paymongo_payment_id = ?, 
                    payment_date = CURRENT_TIMESTAMP 
                WHERE paymongo_link_id = ? AND (status = 'pending' OR status = 'pending_payment')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $payment_id_final, $session_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            error_log("SUCCESS: OralSync Payment updated for Session: $session_id");
            
            // NEW: Automatically activate tenant if this was an initial registration payment
            $tenantQuery = "SELECT tenant_id, procedures_json FROM payment WHERE paymongo_link_id = ? LIMIT 1";
            $tStmt = $conn->prepare($tenantQuery);
            $tStmt->bind_param("s", $session_id);
            $tStmt->execute();
            $tResult = $tStmt->get_result();
            if ($tRow = $tResult->fetch_assoc()) {
                $pJson = json_decode($tRow['procedures_json'] ?? '{}', true);
                $isInitial = (isset($pJson['item']) && $pJson['item'] === 'Initial Subscription');
                
                if ($isInitial) {
                    $tId = $tRow['tenant_id'];
                    $activateSql = "UPDATE tenants SET status = 'active' WHERE tenant_id = ? AND status = 'inactive'";
                    $aStmt = $conn->prepare($activateSql);
                    $aStmt->bind_param("i", $tId);
                    $aStmt->execute();
                    if ($aStmt->affected_rows > 0) {
                        error_log("SUCCESS: Tenant $tId activated after successful initial payment.");
                    }
                    $aStmt->close();
                }
            }
            $tStmt->close();
        } else {
            // This tells you if the 'cs_' ID from PayMongo doesn't exist in your table
            error_log("WARNING: Webhook received for $session_id but no matching row found in SQL.");
        }

    } catch (Exception $e) {
        error_log("WEBHOOK SQL ERROR: " . $e->getMessage());
        http_response_code(500);
        exit;
    }
}

http_response_code(200);