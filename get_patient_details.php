<?php
/**
 * ============================================
 * GET PATIENT DETAILS API ENDPOINT
 * Used by: receptionist_patients.php modal AJAX calls
 * Returns: JSON with patient full details
 * ============================================
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$response = ['success' => false, 'error' => 'Invalid request'];

// Verify session
if (!isset($_SESSION['tenant_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Only allow Receptionist and Admin roles to access patient details
if (!in_array($_SESSION['role'], ['Receptionist', 'Admin', 'Super Admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit();
}

$tenantId = $_SESSION['tenant_id'];
$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($patientId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid patient ID']);
    exit();
}

// Fetch patient details
$stmt = mysqli_prepare($conn, "SELECT 
    p.patient_id,
    p.first_name,
    p.last_name,
    p.contact_number,
    p.email,
    p.birthdate,
    p.gender,
    p.address,
    p.emergency_contact,
    p.medical_history,
    (SELECT DATE_FORMAT(MAX(appointment_date), '%M %d, %Y') FROM appointment WHERE patient_id = p.patient_id AND tenant_id = ?) AS last_appointment
    FROM patient p
    WHERE p.patient_id = ? AND p.tenant_id = ?");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database query failed']);
    exit();
}

mysqli_stmt_bind_param($stmt, 'iii', $tenantId, $patientId, $tenantId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && $result->num_rows > 0) {
    $patient = $result->fetch_assoc();
    
    // Calculate age from birthdate
    $birthDate = new DateTime($patient['birthdate']);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    
    $response = [
        'success' => true,
        'patient' => [
            'patient_id' => (int)$patient['patient_id'],
            'first_name' => h($patient['first_name']),
            'last_name' => h($patient['last_name']),
            'full_name' => h($patient['first_name'] . ' ' . $patient['last_name']),
            'contact_number' => h($patient['contact_number']),
            'email' => h($patient['email']),
            'birthdate' => h(date('M d, Y', strtotime($patient['birthdate']))),
            'age' => $age,
            'gender' => h($patient['gender']),
            'address' => h($patient['address'] ?? 'Not provided'),
            'emergency_contact' => h($patient['emergency_contact'] ?? 'Not provided'),
            'medical_history' => h($patient['medical_history'] ?? 'None recorded'),
            'last_appointment' => $patient['last_appointment'] ?? 'No appointments'
        ]
    ];
} else {
    http_response_code(404);
    $response = ['success' => false, 'error' => 'Patient not found'];
}

echo json_encode($response);
?>
