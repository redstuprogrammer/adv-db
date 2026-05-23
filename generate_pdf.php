<?php
// Output buffering MUST be the very first thing — before any require or output.
// Any byte emitted before the PDF binary stream (including PHP notices/warnings)
// will corrupt the Content-Length header and cause "Failed to load PDF document."
ob_start();

// Disable zlib compression which can corrupt binary PDF output when combined
// with Content-Length.
if (ini_get('zlib.output_compression')) {
    ini_set('zlib.output_compression', 'Off');
}
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}

// PDF generation can be slow for large reports.
set_time_limit(0);
ignore_user_abort(true);

// Suppress display of PHP errors/warnings — log them instead so they don't
// inject stray bytes into the PDF stream.
ini_set('display_errors', '0');
ini_set('log_errors',     '1');

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

$sessionManager = SessionManager::getInstance();
$isSuperAdmin   = $sessionManager->isSuperAdmin();
$isTenantAdmin  = $sessionManager->isTenantUser() && strtolower((string)$sessionManager->getRole()) === 'admin';

if (!$isSuperAdmin && !$isTenantAdmin) {
    ob_end_clean();
    header('Location: ' . ($isSuperAdmin ? 'superadmin_login.php' : 'tenant_login.php'));
    exit;
}

require_once __DIR__ . '/pdf_generator.php';

// ---------------------------------------------------------------------------
// POST — generate a report PDF
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $type = $data['type'] ?? 'standard';

    // Sales/professional reports fetch their own data from the DB;
    // all other types still require a "data" payload.
    if (!$data || (!isset($data['data']) && $type !== 'sales' && $type !== 'professional')) {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(400);
        echo 'Invalid data';
        exit;
    }

    $reportData = $data['data'] ?? [];
    $title      = $data['title'] ?? 'OralSync Report';

    if ($type === 'sales' || $type === 'professional') {
        require_once __DIR__ . '/pdf_generator_blade.php';
        require_once __DIR__ . '/includes/connect.php';

        // Migration Check: Ensure payment.appointment_id exists for the UNION query
        $checkCol = $conn->query("SHOW COLUMNS FROM payment LIKE 'appointment_id'");
        if ($checkCol && $checkCol->num_rows == 0) {
            $conn->query("ALTER TABLE payment ADD COLUMN appointment_id INT AFTER tenant_id");
        }

        $generator  = new OralSyncPDFGenerator();
        $reportData = [];

        // Pull clinic info early so the generator can embed it in headers/footers.
        // We query after $conn is available (required above). Silently skip on failure.
        if (isset($conn)) {
            $clinicStmt = $conn->prepare("SELECT company_name, address, contact_phone FROM tenants WHERE tenant_id = ? LIMIT 1");
            if ($clinicStmt) {
                $tmpTid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
                $clinicStmt->bind_param('i', $tmpTid);
                $clinicStmt->execute();
                $clinicRow = $clinicStmt->get_result()->fetch_assoc();
                if ($clinicRow) {
                    $generator->setClinicInfo(
                        $clinicRow['company_name'] ?? '',
                        $clinicRow['address']       ?? '',
                        $clinicRow['contact_phone'] ?? ''
                    );
                }
            }
        }
        $period     = $data['period'] ?? 'all';

        $dateFilter  = '';
        $periodTitle = 'All Time';

        switch ($period) {
            case 'daily':
                $dateFilter  = ' AND DATE(%s) = CURDATE()';
                $periodTitle = 'Daily (' . date('M d, Y') . ')';
                break;
            case 'weekly':
                $dateFilter  = ' AND YEARWEEK(%s, 1) = YEARWEEK(CURDATE(), 1)';
                $periodTitle = 'Weekly (Week ' . date('W') . ', ' . date('Y') . ')';
                break;
            case 'monthly':
                $dateFilter  = ' AND YEAR(%s) = YEAR(CURDATE()) AND MONTH(%s) = MONTH(CURDATE())';
                $periodTitle = 'Monthly (' . date('F Y') . ')';
                break;
            case 'yearly':
                $dateFilter  = ' AND YEAR(%s) = YEAR(CURDATE())';
                $periodTitle = 'Yearly (' . date('Y') . ')';
                break;
        }

        $isClinicReport = ($type === 'sales');
        
        if ($isSuperAdmin && !$isClinicReport) {
            $context    = 'superadmin';
            $sqlDateCol = 'r.payment_date';
            $filter     = $dateFilter ? str_replace('%s', $sqlDateCol, $dateFilter) : '';

            $query  = "SELECT r.*, r.amount, t.company_name AS tenant_name, t.subscription_tier AS plan
                       FROM payment r
                       JOIN tenants t ON r.tenant_id = t.tenant_id
                       WHERE r.status = 'paid' AND r.appointment_id IS NULL $filter
                       ORDER BY r.payment_date DESC";
            $result = $conn->query($query);

            if (!$result) {
                error_log('SuperAdmin PDF Query failed: ' . $conn->error);
                while (ob_get_level() > 0) ob_end_clean();
                http_response_code(500);
                echo 'Database query failed. Please check server logs.';
                exit;
            }
            while ($row = $result->fetch_assoc()) {
                $reportData[] = $row;
            }
            $title = 'Super Admin Sales Report - ' . $periodTitle;

        } elseif ($isTenantAdmin || ($isSuperAdmin && $isClinicReport)) {
            $context  = 'tenant';
            
            // If superadmin is viewing a clinic, we need the tenant_id from the data or URL
            $tenantId = $sessionManager->getTenantId();
            if (!$tenantId && isset($data['tenant_id'])) {
                $tenantId = (int)$data['tenant_id'];
            }
            // Fallback: read directly from raw session (in case SessionManager uses a different key)
            if (!$tenantId && !empty($_SESSION['tenant_id'])) {
                $tenantId = (int)$_SESSION['tenant_id'];
            }
            if (!$tenantId && isset($_GET['tenant'])) {
                // Try to resolve tenant_id from slug if provided
                $slug = $_GET['tenant'];
                $stmt = $conn->prepare("SELECT tenant_id FROM tenants WHERE subdomain_slug = ?");
                $stmt->bind_param('s', $slug);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    $row = $res->fetch_assoc();
                    $tenantId = $row['tenant_id'] ?? null;
                }
            }

            if (!$tenantId) {
                while (ob_get_level() > 0) ob_end_clean();
                http_response_code(403);
                echo 'Unauthorized or missing tenant context';
                exit;
            }

            $sqlDateCol = 'py.billing_date';
            // monthly needs two placeholders
            $filter = $dateFilter
                ? str_replace('%s', $sqlDateCol, $dateFilter)
                : '';

            $query = "SELECT py.billing_id AS payment_id,
                             py.amount_paid AS amount,
                             CONVERT(py.payment_status USING utf8mb4) COLLATE utf8mb4_general_ci AS status,
                             py.billing_date,
                             py.billing_date AS payment_date,
                             CONVERT(p.first_name      USING utf8mb4) COLLATE utf8mb4_general_ci AS first_name,
                             CONVERT(p.last_name       USING utf8mb4) COLLATE utf8mb4_general_ci AS last_name,
                             CONVERT(py.payment_type   USING utf8mb4) COLLATE utf8mb4_general_ci AS payment_type,
                             CONVERT('web'             USING utf8mb4) COLLATE utf8mb4_general_ci AS source
                      FROM billing py
                      LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
                      LEFT JOIN patient     p ON a.patient_id      = p.patient_id
                      WHERE py.tenant_id = ? AND py.payment_status IN ('paid', 'partial') $filter
                      
                      UNION ALL
                      
                      SELECT r.payment_id,
                             r.amount,
                             CONVERT(r.status          USING utf8mb4) COLLATE utf8mb4_general_ci AS status,
                             r.payment_date AS billing_date,
                             r.payment_date,
                             CONVERT(p.first_name      USING utf8mb4) COLLATE utf8mb4_general_ci AS first_name,
                             CONVERT(p.last_name       USING utf8mb4) COLLATE utf8mb4_general_ci AS last_name,
                             CONVERT(r.payment_type    USING utf8mb4) COLLATE utf8mb4_general_ci AS payment_type,
                             CONVERT('mobile'          USING utf8mb4) COLLATE utf8mb4_general_ci AS source
                      FROM payment r
                      LEFT JOIN appointment a ON r.appointment_id = a.appointment_id
                      LEFT JOIN patient     p ON a.patient_id      = p.patient_id
                      WHERE r.tenant_id = ? AND r.status = 'paid' AND r.appointment_id IS NOT NULL 
                      AND (r.payment_type = 'deposit' OR r.payment_type = 'downpayment') " 
                      . str_replace('py.billing_date', 'r.payment_date', $filter) . "
                      
                      ORDER BY billing_date DESC";

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                error_log('Tenant PDF Prepare failed: ' . $conn->error);
                while (ob_get_level() > 0) ob_end_clean();
                http_response_code(500);
                echo 'Database preparation failed. Please check server logs.';
                exit;
            }
            $stmt->bind_param('ii', $tenantId, $tenantId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $reportData[] = $row;
                    }
                }
            } else {
                error_log('Tenant PDF Execute failed: ' . $stmt->error);
            }
            $title = 'Clinic Sales Report - ' . $periodTitle;
        }

        if (empty($reportData)) {
            while (ob_get_level() > 0) ob_end_clean();
            http_response_code(404);
            echo 'No data found for the selected ' . htmlspecialchars($period) . ' report.';
            exit;
        }

        try {
            $pdfContent = $generator->generateSalesReport($reportData, $title, $context, $period);
        } catch (Exception $e) {
            error_log('Tenant PDF Generation Error: ' . $e->getMessage());
            while (ob_get_level() > 0) ob_end_clean();
            http_response_code(500);
            echo 'PDF generation failed: ' . $e->getMessage();
            exit;
        }

    } else {
        $pdfContent = generatePDF($reportData, $title, '', $type);
    }

    if (empty($pdfContent)) {
        error_log('PDF generation returned empty content. Type: ' . $type . ', Rows: ' . count($reportData));
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        echo 'PDF generation failed — generator returned empty content.';
        exit;
    }

    while (ob_get_level() > 0) ob_end_clean();

    $safeFilename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $title) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit;
}

// ---------------------------------------------------------------------------
// GET — single invoice PDF
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $paymentId = (int)$_GET['id'];

    if ($paymentId <= 0) {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(400);
        echo 'Invalid payment ID';
        exit;
    }

    require_once __DIR__ . '/includes/connect.php';
    require_once __DIR__ . '/includes/tenant_utils.php';

    $tenantId = $sessionManager->getTenantId();
    if (!$tenantId) {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(403);
        echo 'Unauthorized';
        exit;
    }

    $query = "SELECT
                py.billing_id AS payment_id,
                py.amount_paid AS amount,
                py.payment_status AS status,
                py.mode,
                py.billing_date AS payment_date,
                py.procedures_json,
                p.first_name, p.last_name,
                COALESCE(s.service_name, 'General Service') AS service_name,
                a.appointment_date,
                t.company_name
              FROM billing py
              LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
              LEFT JOIN patient     p ON a.patient_id      = p.patient_id
              LEFT JOIN service     s ON a.service_id      = s.service_id
              LEFT JOIN tenants     t ON py.tenant_id      = t.tenant_id
              WHERE py.billing_id = ? AND py.tenant_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $paymentId, $tenantId);
    $stmt->execute();
    $result  = $stmt->get_result();
    $payment = $result->fetch_assoc();

    if (!$payment) {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(404);
        echo 'Payment not found';
        exit;
    }

    $servicesList = $payment['service_name'];
    $procedures   = json_decode($payment['procedures_json'] ?? '[]', true);
    if (is_array($procedures) && !empty($procedures)) {
        $names = array_column($procedures, 'name');
        if (!empty($names)) {
            $servicesList = implode(', ', $names);
        }
    }

    $invoiceData = [
        ['OralSync Invoice'],
        ['Invoice #: ' . str_pad($payment['payment_id'], 4, '0', STR_PAD_LEFT)],
        ['Date: ' . date('F j, Y')],
        [''],
        ['Clinic: '           . $payment['company_name']],
        ['Patient: '          . $payment['first_name'] . ' ' . $payment['last_name']],
        ['Service: '          . $servicesList],
        ['Appointment Date: ' . ($payment['appointment_date'] ? date('M d, Y', strtotime($payment['appointment_date'])) : 'N/A')],
        ['Payment Mode: '     . ucfirst($payment['mode'])],
        ['Amount: &#8369;'    . number_format($payment['amount'], 2)],
        ['Status: '           . ucfirst($payment['status'])],
    ];

    $title      = 'Invoice #' . str_pad($payment['payment_id'], 4, '0', STR_PAD_LEFT);
    $pdfContent = generatePDF($invoiceData, $title, 'invoice_' . $payment['payment_id'] . '.pdf', 'professional');

    if (empty($pdfContent)) {
        error_log('Invoice PDF generation returned empty. Payment ID: ' . $paymentId);
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        echo 'PDF generation failed — generator returned empty content.';
        exit;
    }

    while (ob_get_level() > 0) ob_end_clean();

    $safeFilename = 'invoice_' . $payment['payment_id'] . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $safeFilename . '"');
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit;
}

while (ob_get_level() > 0) ob_end_clean();
http_response_code(400);
echo 'Invalid request';
