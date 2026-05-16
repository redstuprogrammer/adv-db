<?php
/**
 * =============================================================================
 * PATIENT FORGOT PASSWORD — FINAL (Phase 1.3)
 * =============================================================================
 * Endpoint: POST /api/patient_forgot_password.php
 *
 * 1. Finds patient by email
 * 2. Generates secure token, stores in patient.password_reset_token
 * 3. Sends reset link via PHPMailer
 *
 * Always returns success to prevent email enumeration attacks.
 * =============================================================================
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/db.php';
require_once '../config/config_mailer.php';

use PHPMailer\PHPMailer\Exception;

// Define APP_BASE_URL
$is_local = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
define('APP_BASE_URL', $is_local ? 'http://localhost/adv db' : 'https://' . ($_SERVER['HTTP_HOST'] ?? 'yourapp.azurewebsites.net'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['email']) || empty(trim($input['email']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

$email = strtolower(trim($input['email']));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Generic success response — always returned regardless of whether email exists
$genericSuccess = ['success' => true, 'message' => 'If that email is registered, a reset link has been sent.'];

try {
    $stmt = $pdo->prepare('
        SELECT patient_id, first_name, TRIM(email) AS email
        FROM patient
        WHERE LOWER(TRIM(email)) = ?
        LIMIT 1
    ');
    $stmt->execute([$email]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return success even if no patient found (prevents enumeration)
    if (!$patient) {
        echo json_encode($genericSuccess);
        exit;
    }

    // Generate token
    $token   = bin2hex(random_bytes(32)); // 64-char hex
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store token in patient table
    $stmt = $pdo->prepare('
        UPDATE patient
        SET password_reset_token   = ?,
            password_reset_expires = ?
        WHERE patient_id = ?
    ');
    $stmt->execute([$token, $expires, $patient['patient_id']]);

    // Build reset link
    $resetLink = APP_BASE_URL . '/api/patient_reset_password.php?token=' . $token;
    $firstName = htmlspecialchars($patient['first_name']);

    // Send email
    $mail = createMailer();
    $mail->addAddress($patient['email'], $patient['first_name']);
    $mail->Subject = 'Reset Your OralSync Password';
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='UTF-8'>
      <style>
        body { font-family: Arial, sans-serif; background:#f0f4f8; margin:0; padding:20px; }
        .card { max-width:500px; margin:0 auto; background:#fff; border-radius:16px; padding:40px 32px; box-shadow:0 4px 20px rgba(0,0,0,0.08); }
        .logo { text-align:center; margin-bottom:28px; }
        .logo-box { display:inline-block; background:#2563eb; border-radius:14px; padding:14px 20px; color:#fff; font-size:22px; font-weight:800; }
        h2 { color:#1e293b; font-size:22px; margin-bottom:12px; }
        p { color:#475569; font-size:15px; line-height:1.6; margin-bottom:16px; }
        .btn { display:block; background:#2563eb; color:#fff !important; text-decoration:none; text-align:center; border-radius:12px; padding:16px 24px; font-size:16px; font-weight:700; margin:28px 0; }
        .note { font-size:13px; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:16px; margin-top:8px; }
        .url { word-break:break-all; font-size:12px; color:#94a3b8; margin-top:8px; }
      </style>
    </head>
    <body>
      <div class='card'>
        <div class='logo'><div class='logo-box'>OralSync</div></div>
        <h2>Reset Your Password</h2>
        <p>Hi {$firstName},</p>
        <p>We received a request to reset the password for your OralSync account. Click the button below to set a new password:</p>
        <a href='{$resetLink}' class='btn'>Reset My Password</a>
        <p>This link will expire in <strong>1 hour</strong>. If you did not request a password reset, you can safely ignore this email.</p>
        <div class='note'>
          If the button doesn't work, copy and paste this link into your browser:
          <div class='url'>{$resetLink}</div>
        </div>
      </div>
    </body>
    </html>
    ";
    $mail->AltBody = "Hi {$firstName},\n\nReset your OralSync password:\n{$resetLink}\n\nThis link expires in 1 hour.\n\nIf you did not request this, ignore this email.";
    $mail->send();

    echo json_encode($genericSuccess);

} catch (Exception $e) {
    error_log('Mailer error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send reset email. Please try again later.']);
} catch (\PDOException $e) {
    error_log('DB error in forgot password: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
}
?>