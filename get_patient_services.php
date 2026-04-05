<?php
// get_patient_services.php - Returns appointments for a patient (used by receptionist billing)
define('ROOT_PATH', __DIR__ . '/');

// Headers for JSON and Cross-Origin requests (CORS)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight 'OPTIONS' requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database Connection
require_once ROOT_PATH . 'includes/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $patient_id = $_GET['patient_id'] ?? '';
    $tenant_id = $_GET['tenant_id'] ?? '';

    // Validation
    if (empty($patient_id) || !is_numeric($patient_id)) {
        echo json_encode(['success' => false, 'message' => 'Valid patient_id required']);
        exit;
    }

    if (empty($tenant_id) || !is_numeric($tenant_id)) {
        echo json_encode(['success' => false, 'message' => 'Valid tenant_id required']);
        exit;
    }

    // Get appointments for this patient
    $stmt = $conn->prepare("
        SELECT
            a.appointment_id,
            a.appointment_date,
            a.status,
            a.procedure_name
        FROM appointment a
        WHERE a.patient_id = ? AND a.tenant_id = ?
        ORDER BY a.appointment_date DESC
    ");

    $stmt->bind_param("ii", $patient_id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }

    echo json_encode($appointments);
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'GET method required']);
}

$conn->close();
?>