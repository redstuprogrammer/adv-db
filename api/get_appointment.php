<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $patient_id = $_GET['patient_id'] ?? '';

    if (empty($patient_id) || !is_numeric($patient_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'A valid patient_id is required',
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT
            a.appointment_id,
            a.appointment_date  AS date,
            a.appointment_time  AS time,
            a.procedure_name    AS procedure,
            a.status,
            a.notes,
            a.tenant_id,
            CONCAT(d.first_name, ' ', d.last_name) AS doctor
        FROM appointment a
        JOIN tenants t       ON a.tenant_id  = t.tenant_id
        LEFT JOIN dentist d  ON a.dentist_id = d.dentist_id
        WHERE a.patient_id = ?
          AND t.status = 'active'
        ORDER BY a.appointment_date ASC
    ");

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }

    echo json_encode([
        'success'      => true,
        'message'      => 'Appointments fetched successfully',
        'appointments' => $appointments
    ]);

    $stmt->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Only GET requests allowed']);
}

$conn->close();
?>