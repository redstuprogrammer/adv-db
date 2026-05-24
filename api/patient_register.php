<?php
/**
 * ============================================================
 * PATIENT REGISTER — Phase 1.5 (with email verification)
 * ============================================================
 * Endpoint: POST /api/patient_register.php
 * ============================================================
 */

header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/send_mail.php';

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

// ── Validate required fields ──────────────────────────────────
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
$first_name = $name_parts[0];
$last_name  = $name_parts[1] ?? '';

$birthdate = isset($input['birthdate']) && trim($input['birthdate']) !== ''
    ? trim($input['birthdate']) : null;

$gender = isset($input['gender']) && trim($input['gender']) !== ''
    ? trim($input['gender']) : null;

if ($birthdate !== null) {
    $d = DateTime::createFromFormat('Y-m-d', $birthdate);
    if (!$d || $d->format('Y-m-d') !== $birthdate || $d > new DateTime()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid date of birth. Use YYYY-MM-DD.']);
        exit;
    }
}

// ── Transaction ───────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // Verify tenant
    $stmt = $pdo->prepare('
        SELECT tenant_id, company_name, status FROM tenants
        WHERE tenant_code = ? LIMIT 1
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
        echo json_encode(['success' => false, 'message' => 'This clinic is currently inactive.']);
        exit;
    }

    $tenant_id   = $tenant['tenant_id'];
    $clinic_name = $tenant['company_name'];

    // Check email uniqueness within tenant
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

    // Generate tenant_patient_id
    $stmt = $pdo->prepare('
        SELECT COALESCE(MAX(tenant_patient_id), 0) + 1 AS next_id
        FROM patient WHERE tenant_id = ? FOR UPDATE
    ');
    $stmt->execute([$tenant_id]);
    $tenant_patient_id = (int) $stmt->fetch(PDO::FETCH_ASSOC)['next_id'];

    // Generate email verification token
    $verification_token   = bin2hex(random_bytes(32)); // 64-char hex
    $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Insert patient — email_verified = 0 until they click the link
    $stmt = $pdo->prepare('
        INSERT INTO patient (
            tenant_id, first_name, last_name,
            contact_number, email, password_hash,
            must_change_password, tenant_patient_id,
            birthdate, gender,
            email_verified, email_verification_token, email_verification_expires
        ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 0, ?, ?)
    ');
    $stmt->execute([
        $tenant_id, $first_name, $last_name,
        $contact_number, $email, $hashed,
        $tenant_patient_id,
        $birthdate,
        $gender,
        $verification_token,
        $verification_expires,
    ]);

    $patient_id = $pdo->lastInsertId();
    $pdo->commit();

    // Send verification email — non-blocking (failure doesn't break registration)
    $mailSent = sendVerificationEmail($email, $first_name, $verification_token);
    if (!$mailSent) {
        error_log("[Register] Verification email failed for patient_id={$patient_id} email={$email}");
    }

    http_response_code(201);
    echo json_encode([
        'success'           => true,
        'message'           => 'Account created. Please check your email to verify your account before logging in.',
        'email_sent'        => $mailSent,
        'data' => [
            'patient_id'           => (int) $patient_id,
            'tenant_id'            => (int) $tenant_id,
            'first_name'           => $first_name,
            'last_name'            => $last_name,
            'email'                => $email,
            'contact_number'       => $contact_number,
            'clinic_name'          => $clinic_name,
            'company_name'         => $clinic_name,
            'birthdate'            => $birthdate,
            'gender'               => $gender,
            'must_change_password' => 0,
            'email_verified'       => 0,
        ]
    ]);

} catch (\PDOException $e) {
    $pdo->rollBack();
    error_log('Registration error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
}
?>