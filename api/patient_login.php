<?php
header('Content-Type: application/json');
require_once __DIR__ . '/connect.php'; // Ensure your Azure SSL connection is here

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = $_POST['identifier'] ?? ''; // Can be email or username
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Credentials required']);
        exit;
    }

    // Select all columns to give the mobile app a full profile
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
        if (password_verify($password, $patient['password_hash'])) {
            // Remove sensitive password hash
            unset($patient['password_hash']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'data' => $patient // Includes: address, birthdate, allergies, etc.
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Account not found']);
    }
}
?>