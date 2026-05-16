<?php
/**
 * =============================================================================
 * PATIENT RESET PASSWORD — FINAL (Phase 1.3)
 * =============================================================================
 * URL: GET  /api/patient_reset_password.php?token=<64-char>
 *      POST /api/patient_reset_password.php
 *
 * This is a WEB PAGE (not JSON API) — opens in patient's browser from email.
 * GET  → validate token → show new password form
 * POST → validate token + save new password → show success
 *
 * On success: clears password_reset_token + password_reset_expires,
 * and also clears must_change_password just in case.
 * =============================================================================
 */

header('Access-Control-Allow-Origin: *');
require_once '../config/db.php';

$token      = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error      = '';
$success    = false;
$tokenValid = false;
$patientId  = null;

if (!empty($token)) {
    try {
        $stmt = $pdo->prepare('
            SELECT patient_id, first_name
            FROM patient
            WHERE password_reset_token = ?
              AND password_reset_expires > NOW()
            LIMIT 1
        ');
        $stmt->execute([$token]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($patient) {
            $tokenValid = true;
            $patientId  = $patient['patient_id'];
        } else {
            $error = 'This reset link is invalid or has expired. Please request a new one from the app.';
        }
    } catch (\PDOException $e) {
        error_log('Reset token lookup error: ' . $e->getMessage());
        $error = 'Server error. Please try again later.';
    }
} else {
    $error = 'No reset token provided. Please use the link from your email.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $newPassword     = $_POST['new_password']     ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error      = 'Password must be at least 8 characters.';
        $tokenValid = true;
    } elseif ($newPassword !== $confirmPassword) {
        $error      = 'Passwords do not match.';
        $tokenValid = true;
    } else {
        try {
            $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt   = $pdo->prepare('
                UPDATE patient
                SET password_hash          = ?,
                    password_reset_token   = NULL,
                    password_reset_expires = NULL,
                    must_change_password   = 0
                WHERE patient_id = ?
            ');
            $stmt->execute([$hashed, $patientId]);
            $success    = true;
            $tokenValid = false;
        } catch (\PDOException $e) {
            error_log('Password reset update error: ' . $e->getMessage());
            $error = 'Server error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password — OralSync</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;background:#f0f4f8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:#fff;border-radius:20px;padding:40px 36px;width:100%;max-width:420px;box-shadow:0 8px 32px rgba(0,0,0,0.08)}
    .logo{display:flex;align-items:center;gap:12px;margin-bottom:28px}
    .logo-box{width:44px;height:44px;background:#2563eb;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px}
    .logo-text{font-size:20px;font-weight:800;color:#1e293b;letter-spacing:-0.5px}
    h2{font-size:22px;font-weight:700;color:#1e293b;margin-bottom:8px}
    .subtitle{font-size:14px;color:#94a3b8;margin-bottom:28px}
    .field{margin-bottom:18px}
    label{display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px}
    input[type=password]{width:100%;height:50px;padding:0 14px;font-size:15px;color:#1e293b;border:1.5px solid #e2e8f0;border-radius:12px;outline:none;transition:border-color .2s}
    input[type=password]:focus{border-color:#2563eb}
    .helper{font-size:12px;color:#94a3b8;margin-top:5px}
    .btn{width:100%;height:52px;background:#2563eb;color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;margin-top:24px;transition:opacity .2s}
    .btn:hover{opacity:.9}
    .alert{padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:20px}
    .alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    .success-icon{width:64px;height:64px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:30px}
    .success-title{text-align:center;font-size:20px;font-weight:700;color:#1e293b;margin-bottom:10px}
    .success-body{text-align:center;color:#475569;font-size:14px;line-height:1.6}
    .back-note{margin-top:28px;text-align:center;font-size:13px;color:#94a3b8}
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">
      <div class="logo-box">🦷</div>
      <span class="logo-text">OralSync</span>
    </div>

    <?php if ($success): ?>
      <div class="success-icon">✅</div>
      <div class="success-title">Password Reset!</div>
      <p class="success-body">Your password has been updated. You can now log in to the OralSync app with your new password.</p>
      <p class="back-note">You may close this window and return to the app.</p>

    <?php elseif ($error && !$tokenValid): ?>
      <h2>Link Expired</h2>
      <p class="subtitle">This reset link is no longer valid.</p>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <p style="font-size:14px;color:#475569">Please go back to the OralSync app and request a new password reset link.</p>

    <?php else: ?>
      <h2>Set New Password</h2>
      <p class="subtitle">Enter your new password below.</p>
      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST" action="">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="field">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" placeholder="••••••••" required minlength="8">
          <div class="helper">Minimum 8 characters</div>
        </div>
        <div class="field">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required minlength="8">
        </div>
        <button type="submit" class="btn">Reset Password</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>