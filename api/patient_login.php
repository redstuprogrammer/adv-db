<?php
/**
 * =============================================================================
 * PATIENT LOGIN — FINAL (Phase 1.0 + 1.4)
 * =============================================================================
 * Endpoint: POST /api/patient_login.php
 *
 * Returns must_change_password flag so mobile knows whether to
 * force ChangePasswordScreen before going to Home.
 * =============================================================================
 */

header('Content-Type: application/json');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$identifier = trim($input['identifier'] ?? '');
$password   = $input['password'] ?? '';

if (empty($identifier) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email/username and password are required']);
    exit;
}

try {
    // Lookup by email OR username
    $stmt = $pdo->prepare('
        SELECT
            patient_id,
            tenant_id,
            first_name,
            last_name,
            TRIM(email)          AS email,
            username,
            contact_number,
            password_hash,
            must_change_password
        FROM patient
        WHERE LOWER(TRIM(email)) = LOWER(?)
           OR username = ?
        LIMIT 1
    ');
    $stmt->execute([$identifier, $identifier]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Account not found. Check your email or username.']);
        exit;
    }

    if (!password_verify($password, $patient['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Incorrect password. Please try again.']);
        exit;
    }

    // Also fetch clinic name for the home screen greeting
    $stmt2 = $pdo->prepare('SELECT company_name FROM tenants WHERE tenant_id = ? LIMIT 1');
    $stmt2->execute([$patient['tenant_id']]);
    $tenant = $stmt2->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data'    => [
            'patient_id'           => (int) $patient['patient_id'],
            'tenant_id'            => (int) $patient['tenant_id'],
            'first_name'           => $patient['first_name'],
            'last_name'            => $patient['last_name'],
            'email'                => $patient['email'],
            'username'             => $patient['username'],
            'contact_number'       => $patient['contact_number'],
            'clinic_name'          => $tenant['company_name'] ?? '',
            'must_change_password' => (int) $patient['must_change_password'],
        ]
    ]);

} catch (\PDOException $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
}
?>