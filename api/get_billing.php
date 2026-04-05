<?php
// 1. Headers for JSON and Cross-Origin requests (CORS)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight 'OPTIONS' requests from mobile/web browsers
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Database Connection
// Ensure connect.php handles the Azure SSL certificate
require_once __DIR__ . '/../includes/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // 3. Capture Inputs
    $patient_id = $_GET['patient_id'] ?? '';

    // 4. Validation
    if (empty($patient_id) || !is_numeric($patient_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'A valid patient_id is required',
            'debug' => [
                'method' => $_SERVER['REQUEST_METHOD'],
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none'
            ]
        ]);
        exit;
    }

    // 5. Database Query
    // Get billing info (payments) for the patient's appointments
    $stmt = $conn->prepare("
        SELECT
            p.payment_id,
            p.amount,
            p.mode,
            p.status,
            p.payment_date,
            a.appointment_id,
            a.appointment_date,
            a.procedure_name,
            t.name AS clinic_name
        FROM payment p
        JOIN appointment a ON p.appointment_id = a.appointment_id
        JOIN tenants t ON p.tenant_id = t.tenant_id
        WHERE a.patient_id = ?
          AND t.status = 'active'
        ORDER BY p.payment_date DESC
    ");

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $billings = [];
    while ($row = $result->fetch_assoc()) {
        $billings[] = $row;
    }

    echo json_encode([
        'success'   => true,
        'message'   => 'Billing info fetched successfully',
        'billings'  => $billings
    ]);

    $stmt->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Only GET requests allowed']);
}

$conn->close();
?>