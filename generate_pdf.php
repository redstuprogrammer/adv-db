<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';

// Check if superadmin or tenant admin
$isSuperAdmin = !empty($_SESSION['superadmin_authed']);
$isTenantAdmin = isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin';

if (!$isSuperAdmin && !$isTenantAdmin) {
    header('Location: ' . ($isSuperAdmin ? 'superadmin_login.php' : 'tenant_login.php'));
    exit;
}

require_once __DIR__ . '/pdf_generator.php';

// Handle POST for reports
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['data'])) {
        http_response_code(400);
        echo 'Invalid data';
        exit;
    }

    $reportData = $data['data'];
    $title = $data['title'] ?? 'OralSync Report';
    $type = $data['type'] ?? 'standard';

    // Use new Blade-based generator for sales reports
    if ($type === 'sales') {
        require_once __DIR__ . '/pdf_generator_blade.php';
        $generator = new OralSyncPDFGenerator();
        $pdfContent = $generator->generateSalesReport($reportData, $title);
    } else {
        // Use original generator for other reports
        require_once __DIR__ . '/pdf_generator.php';
        $pdfContent = generatePDF($reportData, $title, '', $type);
    }

    // Output the PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $title) . '.pdf"');
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit;
}

// Handle GET for single invoice PDF
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $paymentId = (int)$_GET['id'];
    $tenantSlug = $_GET['tenant'] ?? '';

    if ($paymentId <= 0) {
        http_response_code(400);
        echo 'Invalid payment ID';
        exit;
    }

    // Get payment details
    require_once __DIR__ . '/includes/connect.php';
    require_once __DIR__ . '/includes/tenant_utils.php';

    $tenantId = getCurrentTenantId();
    if (!$tenantId) {
        http_response_code(403);
        echo 'Unauthorized';
        exit;
    }

    $query = "SELECT 
                py.payment_id, py.amount, py.status, py.mode, py.created_at,
                p.first_name, p.last_name,
                COALESCE(s.service_name, 'General Service') AS service_name,
                a.appointment_date,
                t.company_name
              FROM payment py
              LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
              LEFT JOIN patient p ON a.patient_id = p.patient_id
              LEFT JOIN services s ON py.service_id = s.service_id
              LEFT JOIN tenants t ON py.tenant_id = t.tenant_id
              WHERE py.payment_id = ? AND py.tenant_id = ?";

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

    // Generate invoice PDF
    $invoiceData = [
        ['OralSync Invoice'],
        ['Invoice #: ' . str_pad($payment['payment_id'], 4, '0', STR_PAD_LEFT)],
        ['Date: ' . date('F j, Y')],
        [''],
        ['Clinic: ' . $payment['company_name']],
        ['Patient: ' . $payment['first_name'] . ' ' . $payment['last_name']],
        ['Service: ' . $payment['service_name']],
        ['Appointment Date: ' . ($payment['appointment_date'] ? date('M d, Y', strtotime($payment['appointment_date'])) : 'N/A')],
        ['Payment Mode: ' . ucfirst($payment['mode'])],
        ['Amount: ₱' . number_format($payment['amount'], 2)],
        ['Status: ' . ucfirst($payment['status'])],
    ];

    generatePDF($invoiceData, 'Invoice #' . str_pad($payment['payment_id'], 4, '0', STR_PAD_LEFT), 'invoice_' . $payment['payment_id'] . '.pdf', 'invoice');
    exit;
}

http_response_code(400);
echo 'Invalid request';
?>
