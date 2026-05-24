<?php
/**
 * ============================================================
 * FILE: /api/verify_email.php
 * ============================================================
 * GET /api/verify_email.php?token=<64-char-hex>
 *
 * Patient clicks this from their verification email.
 * Validates token → sets email_verified = 1 → shows result page.
 * ============================================================
 */

require_once '../config/db.php';

$token      = trim($_GET['token'] ?? '');
$error      = '';
$success    = false;
$firstName  = '';

if (empty($token)) {
    $error = 'No verification token provided. Please use the link from your email.';
} else {
    try {
        $stmt = $pdo->prepare('
            SELECT patient_id, first_name, email_verified, email_verification_expires
            FROM patient
            WHERE email_verification_token = ?
            LIMIT 1
        ');
        $stmt->execute([$token]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient) {
            $error = 'This verification link is invalid or has already been used.';
        } elseif ($patient['email_verified'] == 1) {
            // Already verified — treat as success so re-clicking link is not confusing
            $success   = true;
            $firstName = $patient['first_name'];
        } elseif (new DateTime() > new DateTime($patient['email_verification_expires'])) {
            $error = 'This verification link has expired. Please register again or contact support.';
        } else {
            // Mark verified and clear the token
            $upd = $pdo->prepare('
                UPDATE patient
                SET email_verified              = 1,
                    email_verification_token    = NULL,
                    email_verification_expires  = NULL
                WHERE patient_id = ?
            ');
            $upd->execute([$patient['patient_id']]);
            $success   = true;
            $firstName = $patient['first_name'];
        }
    } catch (\PDOException $e) {
        error_log('Email verification error: ' . $e->getMessage());
        $error = 'Server error. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Verification — OralSync</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
           background: #f0f4f8; min-height: 100vh;
           display: flex; align-items: center; justify-content: center; padding: 20px; }
    .card { background: #fff; border-radius: 20px; padding: 40px 36px;
            width: 100%; max-width: 420px; box-shadow: 0 8px 32px rgba(0,0,0,0.08); text-align: center; }
    .logo { display: flex; align-items: center; justify-content: center;
            gap: 10px; margin-bottom: 28px; }
    .logo-box { background: #2563eb; border-radius: 12px; padding: 8px 14px;
                color: #fff; font-size: 16px; font-weight: 800; }
    .icon { width: 72px; height: 72px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; font-size: 32px; }
    .icon-success { background: #dcfce7; }
    .icon-error   { background: #fef2f2; }
    h2 { font-size: 22px; font-weight: 700; color: #1e293b; margin-bottom: 10px; }
    p  { font-size: 14px; color: #475569; line-height: 1.7; margin-bottom: 16px; }
    .note { font-size: 12px; color: #94a3b8; margin-top: 20px; }
    .alert { padding: 12px 16px; border-radius: 10px; font-size: 14px;
             margin-bottom: 20px; text-align: left; }
    .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">
      <div class="logo-box">OralSync</div>
    </div>

    <?php if ($success): ?>
      <div class="icon icon-success">✅</div>
      <h2>Email Verified!</h2>
      <p>Hi <?= htmlspecialchars($firstName) ?>, your email has been verified successfully.</p>
      <p>You can now <strong>log in to the OralSync app</strong> and start booking appointments.</p>
      <div class="note">You may close this window and return to the app.</div>

    <?php else: ?>
      <div class="icon icon-error">❌</div>
      <h2>Verification Failed</h2>
      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <p>Please return to the OralSync app and try registering again, or contact your clinic for support.</p>
    <?php endif; ?>
  </div>
</body>
</html>