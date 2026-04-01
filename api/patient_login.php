<?php
die("PHP is working! If you see this instantly, the problem is your database connection in connect.php.");
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
// Ensure connect.php handles the Azure SSL certificate
require_once __DIR__ . '/../connect.php';

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
            'message' => 'Credentials required',
            // Optional: Remove 'debug' in production
            'debug' => [
                'method' => $_SERVER['REQUEST_METHOD'],
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none'
            ]
        ]);
        exit;
    }

    // 5. Database Query
    // We join 'tenants' to ensure the clinic is active
    $stmt = $conn->prepare("
        SELECT p.*, t.company_name 
        FROM patients p
        JOIN tenants t ON p.tenant_id = t.tenant_id
        WHERE (p.email = ? OR p.username = ?) AND t.status = 'active'
    ");
    
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($patient = $result->fetch_assoc()) {
        // 6. Password Verification
        if (password_verify($password, $patient['password_hash'])) {
            
            // Security: Never send the hash back to the app
            unset($patient['password_hash']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'data' => $patient 
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Account not found or clinic inactive']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
}

$conn->close();
?>