<?php
// Buffer ALL output from this point — prevents TCPDF internals or included
// files from sending bytes before our header() calls.
ob_start();

// PDF generation can be slow for large reports; disable the FPM timeout
// for this script only and keep the connection alive if the client disconnects.
set_time_limit(0);
ignore_user_abort(true);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

// Auth check via SessionManager (consistent with the rest of the app)
$sessionManager = SessionManager::getInstance();
$isSuperAdmin   = $sessionManager->isSuperAdmin();
$isTenantAdmin  = $sessionManager->isTenantUser() && $sessionManager->getRole() === 'admin';

if (!$isSuperAdmin && !$isTenantAdmin) {
    ob_end_clean();
    header('Location: ' . ($isSuperAdmin ? 'superadmin_login.php' : 'tenant_login.php'));
    exit;
}

require_once __DIR__ . '/pdf_generator.php';

// Handle POST for reports
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $type = $data['type'] ?? 'standard';

    // Sales/professional reports fetch their own data from the DB,
    // but all other types still require a "data" payload.
    if (!$data || (!isset($data['data']) && $type !== 'sales' && $type !== 'professional')) {
        ob_end_clean();
        http_response_code(400);
        echo 'Invalid data';
        exit;
    }

    $reportData = $data['data'];
    $title      = $data['title'] ?? 'OralSync Report';
    $type       = $data['type'] ?? 'standard';

    // Use new Blade-based generator for sales reports
    if ($type === 'sales' || $type === 'professional') {
        require_once __DIR__ . '/pdf_generator_blade.php';
        require_once __DIR__ . '/includes/connect.php';
        
        $generator = new OralSyncPDFGenerator();
        $reportData = [];
        $context = '';
        $period = $data['period'] ?? 'all';
        
        $dateFilter = "";
        $periodTitle = "All Time";
        
        switch ($period) {
            case 'daily':
                $dateFilter = " AND DATE(%s) = CURDATE()";
                $periodTitle = "Daily (" . date('M d, Y') . ")";
                break;
            case 'weekly':
                $dateFilter = " AND YEARWEEK(%s, 1) = YEARWEEK(CURDATE(), 1)";
                $periodTitle = "Weekly (Week " . date('W') . ", " . date('Y') . ")";
                break;
            case 'monthly':
                $dateFilter = " AND YEAR(%s) = YEAR(CURDATE()) AND MONTH(%s) = MONTH(CURDATE())";
                $periodTitle = "Monthly (" . date('F Y') . ")";
                break;
            case 'yearly':
                $dateFilter = " AND YEAR(%s) = YEAR(CURDATE())";
                $periodTitle = "Yearly (" . date('Y') . ")";
                break;
        }

        if ($isSuperAdmin) {
            $context = 'superadmin';
            $sqlDateCol = "r.payment_date";
            $filter = $dateFilter ? sprintf($dateFilter, $sqlDateCol, $sqlDateCol) : "";
            
            // Fetch aggregated revenue from all Tenant Subscriptions
            $query = "SELECT r.*, t.company_name as tenant_name, t.subscription_tier as plan 
                      FROM payment r
                      JOIN tenants t ON r.tenant_id = t.tenant_id
                      WHERE r.status = 'paid' $filter
                      ORDER BY r.payment_date DESC";
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $reportData[] = $row;
            }
            $title = "Super Admin Sales Report - $periodTitle";
        } elseif ($isTenantAdmin) {
            $context = 'tenant';
            $tenantId = $sessionManager->getTenantId();
            $sqlDateCol = "py.billing_date"; // Use billing_date for sales reports
            $filter = $dateFilter ? sprintf($dateFilter, $sqlDateCol, $sqlDateCol) : "";
            
            // Fetch clinic-specific revenue
            $query = "SELECT py.*, py.billing_id as payment_id, py.amount_paid as amount, p.first_name, p.last_name, 
                             COALESCE(s.service_name, 'General Service') AS service, 
                             py.billing_date
                      FROM billing py
                      LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
                      LEFT JOIN patient p ON a.patient_id = p.patient_id
                      LEFT JOIN service s ON a.service_id = s.service_id
                      WHERE py.tenant_id = ? AND py.payment_status = 'Paid' $filter
                      ORDER BY py.billing_date DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $reportData[] = $row;
            }
            $title = "Clinic Sales Report - $periodTitle";
        }

        if (empty($reportData)) {
            ob_end_clean();
            http_response_code(404);
            echo "No data found for the selected $period report.";
            exit;
        }

        $pdfContent = $generator->generateSalesReport($reportData, $title, $context);
    } else {
        // Use original generator for other reports
        require_once __DIR__ . '/pdf_generator.php';
        $pdfContent = generatePDF($reportData, $title, '', $type);
    }

    // Guard against generator returning null (would cause strlen deprecation
    // and send a broken Content-Length header)
    if (empty($pdfContent)) {
        ob_end_clean();
        http_response_code(500);
        echo 'PDF generation failed — generator returned empty content.';
        exit;
    }

    // Discard any buffered output from TCPDF internals before sending headers
    ob_end_clean();

    $safeFilename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $title) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit;
}

// Handle GET for single invoice PDF
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $paymentId = (int)$_GET['id'];

    if ($paymentId <= 0) {
        ob_end_clean();
        http_response_code(400);
        echo 'Invalid payment ID';
        exit;
    }

    // Get payment details
    require_once __DIR__ . '/includes/connect.php';
    require_once __DIR__ . '/includes/tenant_utils.php';

    $tenantId = $sessionManager->getTenantId();
    if (!$tenantId) {
        ob_end_clean();
        http_response_code(403);
        echo 'Unauthorized';
        exit;
    }

    $query = "SELECT 
                py.billing_id as payment_id, py.amount_paid as amount, py.payment_status as status, py.mode, py.billing_date as payment_date,
                py.procedures_json,
                p.first_name, p.last_name,
                COALESCE(s.service_name, 'General Service') AS service_name,
                a.appointment_date,
                t.company_name
              FROM billing py
              LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
              LEFT JOIN patient p ON a.patient_id = p.patient_id
              LEFT JOIN service s ON a.service_id = s.service_id
              LEFT JOIN tenants t ON py.tenant_id = t.tenant_id
              WHERE py.billing_id = ? AND py.tenant_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $paymentId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();

    if (!$payment) {
        http_response_code(404);
        echo 'Payment not found';
        exit;
    }

    // Parse services from procedures_json if available
    $servicesList = $payment['service_name'];
    $procedures = json_decode($payment['procedures_json'] ?? '[]', true);
    if (is_array($procedures) && !empty($procedures)) {
        $names = array_column($procedures, 'name');
        if (!empty($names)) {
            $servicesList = implode(', ', $names);
        }
    }

    // Generate invoice PDF
    $invoiceData = [
        ['OralSync Invoice'],
        ['Invoice #: ' . str_pad($payment['payment_id'], 4, '0', STR_PAD_LEFT)],
        ['Date: ' . date('F j, Y')],
        [''],
        ['Clinic: ' . $payment['company_name']],
        ['Patient: ' . $payment['first_name'] . ' ' . $payment['last_name']],
        ['Service: ' . $servicesList],
        ['Appointment Date: ' . ($payment['appointment_date'] ? date('M d, Y', strtotime($payment['appointment_date'])) : 'N/A')],
        ['Payment Mode: ' . ucfirst($payment['mode'])],
        ['Amount: ₱' . number_format($payment['amount'], 2)],
        ['Status: ' . ucfirst($payment['status'])],
    ];

    $title = 'Invoice #' . str_pad($payment['payment_id'], 4, '0', STR_PAD_LEFT);
    $pdfContent = generatePDF($invoiceData, $title, 'invoice_' . $payment['payment_id'] . '.pdf', 'professional');

    if (empty($pdfContent)) {
        ob_end_clean();
        http_response_code(500);
        echo 'PDF generation failed — generator returned empty content.';
        exit;
    }

    ob_end_clean();
    $safeFilename = 'invoice_' . $payment['payment_id'] . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $safeFilename . '"');
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit;
}

ob_end_clean();
http_response_code(400);
echo 'Invalid request';
?>
