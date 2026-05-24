<?php
// ============================================================
// FILE: /api/patient_forgot_password.php
// ============================================================
// POST JSON body:
//   email  (string, required)
//
// Always returns success to prevent email enumeration.
// Token is 64-char hex, expires in 1 hour.
// Uses config/send_mail.php — no separate mailer config needed.
// ============================================================

header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/send_mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($input['email'] ?? ''));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
    exit;
}

// Generic response — always returned so attackers can't enumerate emails
$ok = ['success' => true, 'message' => 'If that email is registered, a reset link has been sent.'];

try {
    $stmt = $pdo->prepare('
        SELECT patient_id, first_name, TRIM(email) AS email
        FROM patient
        WHERE LOWER(TRIM(email)) = ?
        LIMIT 1
    ');
    $stmt->execute([$email]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        // Don't reveal whether email exists
        echo json_encode($ok);
        exit;
    }

    // Generate token and expiry
    $token   = bin2hex(random_bytes(32)); // 64-char hex
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store in patient table
    $stmt = $pdo->prepare('
        UPDATE patient
        SET password_reset_token   = ?,
            password_reset_expires = ?
        WHERE patient_id = ?
    ');
    $stmt->execute([$token, $expires, $patient['patient_id']]);

    // Send email (non-blocking — failure still returns success)
    $sent = sendPasswordResetEmail(
        $patient['email'],
        $patient['first_name'],
        $token
    );

    if (!$sent) {
        error_log('[ForgotPassword] Email failed for patient_id=' . $patient['patient_id']);
    }

    echo json_encode($ok);

} catch (\PDOException $e) {
    error_log('[ForgotPassword] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
}
?>