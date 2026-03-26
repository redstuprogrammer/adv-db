<?php
/**
 * Mobile App API Endpoint
 * 
 * Moved from /api/patient_login.php to here due to Azure Free tier POST restrictions
 * Mobile apps should POST to /mobile_login.php instead of /api/patient_login.php
 */

// 1. Headers for JSON and Cross-Origin requests (CORS)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight 'OPTIONS' requests from mobile/web browsers
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Database Connection
require_once __DIR__ . '/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 3. Capture Inputs
    // Try standard POST first (Form-data)
    $identifier = $_POST['identifier'] ?? ''; 
    $password = $_POST['password'] ?? '';

    // If empty, try reading the JSON body (Mobile standard)
    if (empty($identifier)) {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $identifier = $data['identifier'] ?? '';
        $password = $data['password'] ?? '';
    }

    // 4. Validation
    if (empty($identifier) || empty($password)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Credentials required'
        ]);
        exit;
    }

    // 5. Database Query
    $stmt = $conn->prepare("
        SELECT p.*, t.company_name 
        FROM patients p
        JOIN tenants t ON p.tenant_id = t.tenant_id
        WHERE (p.email = ? OR p.username = ?) AND t.status = 'active'
    ");
    
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();

    // 6. Verify Password
    if (!$patient) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid credentials'
        ]);
        exit;
    }

    if (!password_verify($password, $patient['password'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid credentials'
        ]);
        exit;
    }

    // 7. Successful login - Return patient data
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'patient' => [
            'patient_id' => $patient['patient_id'],
            'first_name' => $patient['first_name'],
            'last_name' => $patient['last_name'],
            'email' => $patient['email'],
            'phone' => $patient['contact_number'],
            'clinic' => $patient['company_name'],
            'tenant_id' => $patient['tenant_id']
        ]
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
