<?php
// ============================================================
// FILE: /api/patient_reset_password.php
// ============================================================
// GET  ?token=<64-char-hex> → validate token → show form
// POST                      → validate + save new password
//
// Web page — patient opens this from the reset email link.
// ============================================================

require_once '../config/db.php';

$token      = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error      = '';
$success    = false;
$tokenValid = false;
$patientId  = null;
$firstName  = '';

if (empty($token)) {
    $error = 'No reset token provided. Please use the link from your email.';
} else {
    try {
        $stmt = $pdo->prepare('
            SELECT patient_id, first_name,
                   password_reset_expires
            FROM patient
            WHERE password_reset_token = ?
            LIMIT 1
        ');
        $stmt->execute([$token]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient) {
            $error = 'This reset link is invalid or has already been used.';
        } elseif (new DateTime() > new DateTime($patient['password_reset_expires'])) {
            $error = 'This reset link has expired. Please request a new one from the app.';
        } else {
            $tokenValid = true;
            $patientId  = $patient['patient_id'];
            $firstName  = $patient['first_name'];
        }
    } catch (\PDOException $e) {
        error_log('[ResetPassword] Token lookup error: ' . $e->getMessage());
        $error = 'Server error. Please try again later.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $newPassword     = $_POST['new_password']     ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
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
            error_log('[ResetPassword] Update error: ' . $e->getMessage());
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
    .logo{display:flex;align-items:center;gap:10px;margin-bottom:28px}
    .logo-box{background:#2563eb;border-radius:12px;padding:8px 14px;color:#fff;font-size:16px;font-weight:800}
    .logo-sub{font-size:12px;color:#94a3b8;font-weight:500}
    h2{font-size:22px;font-weight:700;color:#1e293b;margin-bottom:8px}
    .subtitle{font-size:14px;color:#64748b;margin-bottom:28px;line-height:1.6}
    .field{margin-bottom:18px}
    label{display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px}
    input[type=password]{width:100%;height:50px;padding:0 14px;font-size:15px;color:#1e293b;border:1.5px solid #e2e8f0;border-radius:12px;outline:none;transition:border-color .2s;background:#f8fafc}
    input[type=password]:focus{border-color:#2563eb;background:#fff}
    .helper{font-size:12px;color:#94a3b8;margin-top:5px}
    .btn{width:100%;height:52px;background:#2563eb;color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;margin-top:8px;transition:opacity .2s;display:flex;align-items:center;justify-content:center}
    .btn:hover{opacity:.9}
    .alert{padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:20px}
    .alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    .icon{width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:32px}
    .icon-success{background:#dcfce7}
    .icon-error{background:#fef2f2}
    .text-center{text-align:center}
    .note{font-size:12px;color:#94a3b8;margin-top:20px;text-align:center;line-height:1.6}
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">
      <div class="logo-box">OralSync</div>
      <span class="logo-sub">Dental Management System</span>
    </div>

    <?php if ($success): ?>
      <div class="icon icon-success text-center">✅</div>
      <h2 class="text-center">Password Reset!</h2>
      <p class="subtitle text-center">
        Hi <?= htmlspecialchars($firstName) ?>, your password has been updated successfully.
        You can now log in to the OralSync app with your new password.
      </p>
      <p class="note">You may close this window and return to the app.</p>

    <?php elseif ($error && !$tokenValid): ?>
      <div class="icon icon-error text-center">❌</div>
      <h2 class="text-center">Link Expired</h2>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <p class="subtitle text-center">
        Please go back to the OralSync app and request a new password reset link from the login screen.
      </p>

    <?php else: ?>
      <h2>Set New Password</h2>
      <p class="subtitle">Hi <?= htmlspecialchars($firstName) ?>, enter and confirm your new password below.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="field">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password"
                 placeholder="At least 8 characters" required minlength="8">
          <div class="helper">Minimum 8 characters</div>
        </div>

        <div class="field">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password"
                 placeholder="Re-enter new password" required minlength="8">
        </div>

        <button type="submit" class="btn">Update Password</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>