<?php
/**
 * pdf_debug.php — drop this in your project root, then open:
 *   yoursite.com/pdf_debug.php?type=sales&period=all
 *
 * It will tell you EXACTLY what bytes are being sent before the PDF stream,
 * or show the actual error if generation fails.
 *
 * DELETE THIS FILE after debugging.
 */

ob_start();
ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

$sessionManager = SessionManager::getInstance();
$isSuperAdmin   = $sessionManager->isSuperAdmin();
$isTenantAdmin  = $sessionManager->isTenantUser() && $sessionManager->getRole() === 'admin';

// Check 1: auth
if (!$isSuperAdmin && !$isTenantAdmin) {
    die('NOT LOGGED IN as superadmin or tenant admin.');
}

// Check 2: capture any stray output already in the buffer
$strayBefore = ob_get_clean();
ob_start();

require_once __DIR__ . '/includes/connect.php';

$strayAfterConnect = ob_get_clean();
ob_start();

require_once __DIR__ . '/pdf_generator_blade.php';

$strayAfterBlade = ob_get_clean();

// Check 3: DB query
$period     = $_GET['period'] ?? 'all';
$reportData = [];

if ($isSuperAdmin) {
    $context = 'superadmin';
    $query   = "SELECT r.*, r.amount, t.company_name AS tenant_name, t.subscription_tier AS plan
                FROM payment r
                JOIN tenants t ON r.tenant_id = t.tenant_id
                WHERE r.status = 'paid'
                ORDER BY r.payment_date DESC
                LIMIT 5";
    $result  = $conn->query($query);
    if (!$result) {
        die('DB QUERY FAILED: ' . $conn->error);
    }
    while ($row = $result->fetch_assoc()) {
        $reportData[] = $row;
    }
    $title = 'Debug Sales Report';
} else {
    $context  = 'tenant';
    $tenantId = $sessionManager->getTenantId();
    $query    = "SELECT py.billing_id AS payment_id,
                        py.amount_paid AS amount,
                        py.payment_status AS status,
                        py.billing_date AS payment_date,
                        py.billing_date,
                        p.first_name, p.last_name,
                        COALESCE(s.service_name, 'General Service') AS service,
                        COALESCE(s.service_name, 'General Service') AS service_name,
                        a.appointment_date
                 FROM billing py
                 LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
                 LEFT JOIN patient     p ON a.patient_id      = p.patient_id
                 LEFT JOIN service     s ON a.service_id      = s.service_id
                 WHERE py.tenant_id = ? AND py.payment_status = 'paid'
                 ORDER BY py.billing_date DESC
                 LIMIT 5";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die('DB PREPARE FAILED: ' . $conn->error);
    }
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reportData[] = $row;
    }
    $title = 'Debug Tenant Report';
}

// Check 4: generate PDF
ob_start();
$generator = new OralSyncPDFGenerator();
try {
    $pdfContent = $generator->generateSalesReport($reportData, $title, $context);
} catch (\Exception $e) {
    $strayDuringGen = ob_get_clean();
    die('<b>EXCEPTION during generateSalesReport:</b> ' . htmlspecialchars($e->getMessage())
        . '<br><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>'
        . '<br><b>Output captured during generation:</b><br><pre>' . htmlspecialchars($strayDuringGen) . '</pre>');
}
$strayDuringGen = ob_get_clean();

// ── Report ────────────────────────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
echo '<h2>PDF Debug Report</h2>';

echo '<h3>1. Stray output BEFORE connect.php</h3>';
echo '<pre style="background:#fee;padding:8px;">' . (strlen($strayBefore) ? htmlspecialchars($strayBefore) : '<none>') . '</pre>';
echo '<p>Bytes: ' . strlen($strayBefore) . '</p>';

echo '<h3>2. Stray output from connect.php</h3>';
echo '<pre style="background:#fee;padding:8px;">' . (strlen($strayAfterConnect) ? htmlspecialchars($strayAfterConnect) : '<none>') . '</pre>';
echo '<p>Bytes: ' . strlen($strayAfterConnect) . '</p>';

echo '<h3>3. Stray output from pdf_generator_blade.php require</h3>';
echo '<pre style="background:#fee;padding:8px;">' . (strlen($strayAfterBlade) ? htmlspecialchars($strayAfterBlade) : '<none>') . '</pre>';
echo '<p>Bytes: ' . strlen($strayAfterBlade) . '</p>';

echo '<h3>4. Stray output DURING PDF generation</h3>';
echo '<pre style="background:#fee;padding:8px;">' . (strlen($strayDuringGen) ? htmlspecialchars($strayDuringGen) : '<none>') . '</pre>';
echo '<p>Bytes: ' . strlen($strayDuringGen) . '</p>';

echo '<h3>5. DB rows returned</h3>';
echo '<p>' . count($reportData) . ' rows</p>';
if (!empty($reportData)) {
    echo '<p>First row keys: <code>' . implode(', ', array_keys($reportData[0])) . '</code></p>';
}

echo '<h3>6. PDF content</h3>';
echo '<p>Length: ' . strlen($pdfContent) . ' bytes</p>';
echo '<p>First 20 bytes (hex): <code>' . bin2hex(substr($pdfContent, 0, 20)) . '</code></p>';
echo '<p>Starts with %PDF: <b>' . (str_starts_with($pdfContent, '%PDF') ? 'YES ✅' : 'NO ❌ — this is the bug') . '</b></p>';

echo '<h3>7. Blade view file</h3>';
$viewPath = __DIR__ . '/views/sales_report.blade.php';
echo '<p>' . $viewPath . ': <b>' . (file_exists($viewPath) ? 'EXISTS ✅' : 'MISSING ❌') . '</b></p>';

echo '<h3>8. Cache directory</h3>';
$cachePath = __DIR__ . '/cache';
echo '<p>' . $cachePath . ': <b>' . (is_dir($cachePath) ? 'EXISTS' : 'MISSING ❌') . '</b>, writable: <b>' . (is_writable($cachePath) ? 'YES ✅' : 'NO ❌') . '</b></p>';

echo '<h3>9. PHP headers sent</h3>';
$headersList = headers_list();
echo '<pre>' . htmlspecialchars(implode("\n", $headersList)) . '</pre>';

echo '<hr><p><i>Delete pdf_debug.php from your server after use.</i></p>';
