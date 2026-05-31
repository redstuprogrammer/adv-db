<?php
// API: Charge a saved provider token (PayMongo)
// POST JSON: { "tenant_id": 1, "subscription_id": 2, "amount": 2000 }

ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$tenant_id = intval($body['tenant_id'] ?? 0);
$subscription_id = intval($body['subscription_id'] ?? 0);
$amount = floatval($body['amount'] ?? 0); // amount in PHP (decimal)
$attempt_count = intval($body['attempt_count'] ?? 0);

if (!$tenant_id || !$subscription_id || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing tenant_id, subscription_id, or amount']);
    exit;
}

// Lookup default payment method
$pmStmt = $conn->prepare('SELECT id, provider_token FROM payment_methods WHERE tenant_id = ? AND is_default = 1 LIMIT 1');
if (!$pmStmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
}
$pmStmt->bind_param('i', $tenant_id);
$pmStmt->execute();
$pm = $pmStmt->get_result()->fetch_assoc();
$pmStmt->close();

if (empty($pm) || empty($pm['provider_token'])) {
    echo json_encode(['success' => false, 'message' => 'No default saved payment method with provider token found']);
    exit;
}

$provider_token = $pm['provider_token'];

// create a renewal attempt record
$insAttempt = $conn->prepare('INSERT INTO subscription_renewal_attempts (subscription_id, tenant_id, attempt_count, status) VALUES (?, ?, ?, ?)');
if ($insAttempt) {
    $nextStatus = 'pending';
    $ac = max(1, $attempt_count);
    $insAttempt->bind_param('iiis', $subscription_id, $tenant_id, $ac, $nextStatus);
    $insAttempt->execute();
    $attemptId = $conn->insert_id;
    $insAttempt->close();
} else {
    $attemptId = null;
}

// Prepare PayMongo request
$pm_config = null;
$candidates = [__DIR__ . '/../config/paymongo.php', dirname(__DIR__) . '/config/paymongo.php', $_SERVER['DOCUMENT_ROOT'] . '/config/paymongo.php'];
foreach ($candidates as $p) { if (file_exists($p)) { $pm_config = require $p; break; } }
$secret = $pm_config['secret_key'] ?? getenv('PAYMONGO_SECRET_KEY') ?? '';
if (!$secret) {
    echo json_encode(['success' => false, 'message' => 'PayMongo secret missing']);
    exit;
}

$auth = base64_encode($secret . ':');

// Amount in centavos
$amount_centavos = intval(round($amount * 100));

$payload = [
    'data' => [
        'attributes' => [
            'amount' => $amount_centavos,
            'currency' => 'PHP',
            'description' => 'Subscription renewal',
            'metadata' => [ 'tenant_id' => (string)$tenant_id, 'subscription_id' => (string)$subscription_id ],
        ]
    ]
];

// If provider_token looks like a payment_method id, attach it
if (strpos($provider_token, 'pm_') === 0 || strpos($provider_token, 'pm-') === 0) {
    $payload['data']['attributes']['payment_method'] = ['id' => $provider_token, 'type' => 'card'];
} else {
    // fallback: try using provider_token as source
    $payload['data']['attributes']['source'] = ['id' => $provider_token];
}

$ch = curl_init('https://api.paymongo.com/v1/payments');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . $auth,
    ],
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 20,
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

error_log('[charge_provider_token] tenant=' . $tenant_id . ' sub=' . $subscription_id . ' token=' . substr($provider_token,0,10) . ' http=' . $http . ' err=' . $err . ' resp=' . substr($resp,0,400));

if (!$resp) {
    echo json_encode(['success' => false, 'message' => 'No response from PayMongo', 'debug' => $err]);
    exit;
}

$j = json_decode($resp, true);

// Determine success
$status = $j['data']['attributes']['status'] ?? $j['data']['attributes']['payment_status'] ?? null;
if ($http >= 200 && $http < 300 && in_array($status, ['paid','succeeded','captured'], true)) {
    // Create billing record and update subscription period
    // (Simplified: insert a payment row and extend subscription current_period_end by plan duration if available)
    $conn->begin_transaction();
    try {
        $reference = $j['data']['id'] ?? uniqid('pm_');
        $ins = $conn->prepare('INSERT INTO payment (tenant_id, amount, status, payment_date, paymongo_link_id) VALUES (?, ?, ?, NOW(), ?)');
        if ($ins) { $paidAmount = $amount; $ins->bind_param('idss', $tenant_id, $paidAmount, $status, $reference); $ins->execute(); $ins->close(); }

        // Update subscription current_period_end if subscription exists
        $subStmt = $conn->prepare('SELECT id, plan_id, current_period_end FROM subscriptions WHERE id = ? AND tenant_id = ? LIMIT 1');
        if ($subStmt) {
            $subStmt->bind_param('ii', $subscription_id, $tenant_id);
            $subStmt->execute();
            $sub = $subStmt->get_result()->fetch_assoc();
            $subStmt->close();
            if ($sub) {
                // find plan duration
                $planDays = null;
                if (!empty($sub['plan_id'])) {
                    $p = $conn->prepare('SELECT duration_days FROM subscription_plans WHERE plan_id = ? LIMIT 1');
                    if ($p) { $p->bind_param('i', $sub['plan_id']); $p->execute(); $pR = $p->get_result()->fetch_assoc(); $planDays = intval($pR['duration_days'] ?? 0); $p->close(); }
                }
                $now = new DateTime();
                $currentEnd = $sub['current_period_end'] ? new DateTime($sub['current_period_end']) : $now;
                $addDays = $planDays > 0 ? $planDays : 30;
                $currentEnd->modify('+' . $addDays . ' days');
                $upd = $conn->prepare('UPDATE subscriptions SET current_period_end = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?');
                if ($upd) { $newEndStr = $currentEnd->format('Y-m-d H:i:s'); $upd->bind_param('sii', $newEndStr, $subscription_id, $tenant_id); $upd->execute(); $upd->close(); }
            }
        }

        $conn->commit();
        // mark attempt success
        if (!empty($attemptId)) {
            $u = $conn->prepare('UPDATE subscription_renewal_attempts SET status = ?, response = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            if ($u) { $sresp = json_encode($j); $s = 'success'; $u->bind_param('ssi', $s, $sresp, $attemptId); $u->execute(); $u->close(); }
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Charged successfully', 'paymongo_response' => $j]);
    exit;
}

// Charge failed — compute backoff and schedule retry
$respText = is_string($resp) ? $resp : json_encode($resp);
$maxAttempts = 3;
$newAttemptCount = max(1, $attempt_count) + 1;
$delaySeconds = min(pow(2, $newAttemptCount) * 60, 60 * 60 * 24 * 7); // exponential, cap 7 days
$nextRetryAt = date('Y-m-d H:i:s', time() + $delaySeconds);

if (!empty($attemptId)) {
    $u2 = $conn->prepare('UPDATE subscription_renewal_attempts SET attempt_count = ?, status = ?, response = ?, next_retry_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    if ($u2) { $st = 'failed'; $u2->bind_param('isssi', $newAttemptCount, $st, $respText, $nextRetryAt, $attemptId); $u2->execute(); $u2->close(); }
}

// If exceeds max attempts, send notification to tenant owner
if ($newAttemptCount >= $maxAttempts) {
    // fetch tenant contact email
    $tq = $conn->prepare('SELECT owner_name, contact_email FROM tenants WHERE tenant_id = ? LIMIT 1');
    if ($tq) { $tq->bind_param('i', $tenant_id); $tq->execute(); $trow = $tq->get_result()->fetch_assoc(); $tq->close(); }
    $ownerEmail = $trow['contact_email'] ?? null;
    $ownerName = $trow['owner_name'] ?? 'Clinic Owner';
    if ($ownerEmail) {
        require_once __DIR__ . '/../config/send_mail.php';
        $subject = 'Subscription Renewal Failed — OralSync';
        $body = "<h2>Subscription Renewal Failed</h2><p>Hi {$ownerName},</p><p>We could not process your subscription renewal after {$newAttemptCount} attempts. Please update your payment method in your OralSync account.</p><div class='info-box'><div class='info-row'><span class='info-label'>Tenant ID</span><span class='info-value'>{$tenant_id}</span></div><div class='info-row'><span class='info-label'>Subscription</span><span class='info-value'>{$subscription_id}</span></div></div><p>If you'd like assistance, contact support.</p>";
        $alt = "Subscription renewal failed for tenant {$tenant_id}, subscription {$subscription_id}.";
        dispatchMail($ownerEmail, $ownerName, $subject, $body, $alt);
    }
}

echo json_encode(['success' => false, 'message' => 'Charge failed', 'http' => $http, 'resp' => $j, 'next_retry_at' => $nextRetryAt, 'attempt' => $newAttemptCount]);

?>
