<?php
/**
 * =============================================================================
 * PATIENT REGISTER — FINAL (Phase 1.1)
 * =============================================================================
 * Endpoint: POST /api/patient_register.php
 *
 * Schema: Dump20260428
 * → tenant code is tenants.tenant_code (no separate tenant_codes table)
 * → patient columns: patient_id, tenant_id, first_name, last_name,
 *   contact_number, email, password_hash, username, address, birthdate,
 *   gender, occupation, medical_history, allergies, notes,
 *   tenant_patient_id, must_change_password (added via migration),
 *   password_reset_token, password_reset_expires (added via migration)
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

// ============================================================================
// 1. VALIDATE INPUTS
// ============================================================================
$required = ['tenant_code', 'full_name', 'email', 'password', 'contact_number'];
foreach ($required as $field) {
    if (!isset($input[$field]) || trim($input[$field]) === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Required field missing: $field"]);
        exit;
    }
}

$tenant_code    = strtoupper(trim($input['tenant_code']));
$full_name      = trim($input['full_name']);
$email          = strtolower(trim($input['email']));
$password       = $input['password'];
$contact_number = trim($input['contact_number']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}

$name_parts = explode(' ', $full_name, 2);
$first_name  = $name_parts[0];
$last_name   = isset($name_parts[1]) ? $name_parts[1] : '';

// Optional fields sent from the registration form
$birthdate = isset($input['birthdate']) && trim($input['birthdate']) !== ''
    ? trim($input['birthdate'])
    : null;

$gender = isset($input['gender']) && trim($input['gender']) !== ''
    ? trim($input['gender'])
    : null;

// Validate birthdate format when provided
if ($birthdate !== null) {
    $d = DateTime::createFromFormat('Y-m-d', $birthdate);
    if (!$d || $d->format('Y-m-d') !== $birthdate || $d > new DateTime()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid date of birth. Use YYYY-MM-DD and ensure it is in the past.']);
        exit;
    }
}

// ============================================================================
// 2. TRANSACTION
// ============================================================================
try {
    $pdo->beginTransaction();

    // 2a. Verify tenant by tenant_code directly from tenants table
    $stmt = $pdo->prepare('
        SELECT tenant_id, company_name, status
        FROM tenants
        WHERE tenant_code = ?
        LIMIT 1
    ');
    $stmt->execute([$tenant_code]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tenant) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid clinic code. Please check with your clinic.']);
        exit;
    }

    if ($tenant['status'] !== 'active') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This clinic is currently inactive. Please contact your clinic.']);
        exit;
    }

    $tenant_id   = $tenant['tenant_id'];
    $clinic_name = $tenant['company_name'];

    // 2b. Check email uniqueness within tenant
    $stmt = $pdo->prepare('
        SELECT patient_id FROM patient
        WHERE LOWER(TRIM(email)) = ? AND tenant_id = ?
    ');
    $stmt->execute([$email, $tenant_id]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This email is already registered at this clinic.']);
        exit;
    }

    // 2c. Generate tenant_patient_id (per-tenant sequential, NOT NULL + UNIQUE constraint)
    $stmt = $pdo->prepare('
        SELECT COALESCE(MAX(tenant_patient_id), 0) + 1 AS next_id
        FROM patient
        WHERE tenant_id = ?
        FOR UPDATE
    ');
    $stmt->execute([$tenant_id]);
    $tenant_patient_id = (int) $stmt->fetch(PDO::FETCH_ASSOC)['next_id'];

    // 2d. Hash password
    $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // 2e. Insert — only real columns from Dump20260428 + migration columns
    $stmt = $pdo->prepare('
        INSERT INTO patient (
            tenant_id, first_name, last_name,
            contact_number, email, password_hash,
            must_change_password, tenant_patient_id,
            birthdate, gender
        ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
    ');
    $stmt->execute([
        $tenant_id, $first_name, $last_name,
        $contact_number, $email, $hashed,
        $tenant_patient_id,
        $birthdate,
        $gender,
    ]);

    $patient_id = $pdo->lastInsertId();
    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully',
        'data' => [
            'patient_id'           => (int) $patient_id,
            'tenant_id'            => (int) $tenant_id,
            'first_name'           => $first_name,
            'last_name'            => $last_name,
            'email'                => $email,
            'contact_number'       => $contact_number,
            'clinic_name'          => $clinic_name,
            'company_name'         => $clinic_name,   // ProfileScreen reads company_name
            'birthdate'            => $birthdate,
            'gender'               => $gender,
            'must_change_password' => 0,
        ]
    ]);

} catch (\PDOException $e) {
    $pdo->rollBack();
    error_log('Registration error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
}
?>