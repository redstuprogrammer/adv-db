<?php
// CLI script: process auto-renew subscriptions
// Run: php auto_renew_subscriptions.php

require_once __DIR__ . '/../includes/connect.php';

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from CLI.\n";
    exit(1);
}

// Find subscriptions due for renewal and with auto_renew = 1
$sql = "SELECT s.id AS subscription_id, s.tenant_id, s.current_period_end, sp.price
    FROM subscriptions s
    LEFT JOIN subscription_plans sp ON sp.plan_id = s.plan_id
    WHERE s.auto_renew = 1 AND s.current_period_end IS NOT NULL AND s.current_period_end <= NOW()";

$res = $conn->query($sql);
if (!$res) { echo "DB query failed: " . $conn->error . "\n"; exit(1); }

while ($row = $res->fetch_assoc()) {
    $tenantId = intval($row['tenant_id']);
    $subscriptionId = intval($row['subscription_id']);
    $amount = floatval($row['price'] ?? 0.0);

    echo "Processing tenant={$tenantId} subscription={$subscriptionId} amount={$amount}\n";

    // skip if there's a pending attempt scheduled in the future
    $checkAttempt = $conn->prepare('SELECT id, attempt_count, status, next_retry_at FROM subscription_renewal_attempts WHERE subscription_id = ? AND status != "success" ORDER BY id DESC LIMIT 1');
    if ($checkAttempt) {
        $checkAttempt->bind_param('i', $subscriptionId);
        $checkAttempt->execute();
        $lastAttempt = $checkAttempt->get_result()->fetch_assoc();
        $checkAttempt->close();
        if (!empty($lastAttempt) && !empty($lastAttempt['next_retry_at']) && strtotime($lastAttempt['next_retry_at']) > time()) {
            echo "Skipping subscription {$subscriptionId} until retry at {$lastAttempt['next_retry_at']}\n";
            continue;
        }
        $attemptCount = !empty($lastAttempt['attempt_count']) ? intval($lastAttempt['attempt_count']) : 0;
    } else {
        $attemptCount = 0;
    }

    // get default payment method token
    $pmStmt = $conn->prepare('SELECT provider_token FROM payment_methods WHERE tenant_id = ? AND is_default = 1 LIMIT 1');
    if (!$pmStmt) { echo "Prepare failed\n"; continue; }
    $pmStmt->bind_param('i', $tenantId);
    $pmStmt->execute();
    $pm = $pmStmt->get_result()->fetch_assoc();
    $pmStmt->close();

    if (empty($pm) || empty($pm['provider_token'])) {
        echo "No default provider token for tenant {$tenantId} — skipping\n";
        continue;
    }

    $providerToken = $pm['provider_token'];

    // Call internal API to charge
    $payload = json_encode(['tenant_id' => $tenantId, 'subscription_id' => $subscriptionId, 'amount' => $amount, 'attempt_count' => $attemptCount]);
    $ch = curl_init('http://127.0.0.1' . dirname($_SERVER['SCRIPT_NAME']) . '/api/charge_provider_token.php');
    // If running on different host/path, adjust the URL accordingly.
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    echo "Charge response HTTP={$http} err={$err} resp=" . substr($resp,0,400) . "\n";
}

echo "Done.\n";
