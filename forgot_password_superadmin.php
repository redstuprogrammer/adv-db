<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$message = '';
$isError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    
    if ($username === '') {
        $message = 'Please enter your username.';
        $isError = true;
    } else {
        // Find super admin by username
        $stmt = mysqli_prepare($conn, "SELECT id FROM super_admins WHERE username = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $admin = mysqli_fetch_assoc($res);
            
            if ($admin) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $tokenHash = password_hash($token, PASSWORD_DEFAULT);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $updateStmt = mysqli_prepare($conn, "UPDATE super_admins SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, "ssi", $tokenHash, $expiresAt, (int)$admin['id']);
                    mysqli_stmt_execute($updateStmt);
                    
                    // Build reset link
                    $resetLink = buildSuperAdminResetPasswordUrl($token, (int)$admin['id']);
                    
                    // Send email
                    $adminEmail = getenv('SUPERADMIN_EMAIL') ?: $_ENV['SUPERADMIN_EMAIL'] ?? 'admin@oralsync.com';
                    $emailSent = sendPasswordResetEmail([
                        'to_email' => $adminEmail,
                        'subject_name' => 'OralSync Super Admin',
                        'reset_link' => $resetLink
                    ]);
                    
                    if ($emailSent) {
                        $message = 'Password reset link has been sent to the registered admin email. Check your inbox and spam folder.';
                        $isError = false;
                    } else {
                        $message = 'Email could not be sent. Please try again later or contact support.';
                        $isError = true;
                    }
                }
            } else {
                // Security: Don't reveal if username exists
                $message = 'If this username is registered, you will receive a password reset link shortly.';
                $isError = false;
            }
        }
    }
}

function buildSuperAdminResetPasswordUrl(string $token, int $adminId): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    $base = ($scriptPath === '/' || $scriptPath === '\\' || $scriptPath === '.') ? '' : $scriptPath;
    
    $url = $scheme . '://' . $host;
    if ($base !== '') {
        $url .= $base;
    }
    $url .= '/reset_password_superadmin.php?token=' . urlencode($token) . '&id=' . urlencode((string)$adminId);
    
    return $url;
}

function sendPasswordResetEmail(array $params): bool {
    $toEmail = $params['to_email'] ?? '';
    $subjectName = $params['subject_name'] ?? 'OralSync';
    $resetLink = $params['reset_link'] ?? '';
    
    if (!$toEmail || !$resetLink) {
        return false;
    }
    
    // Try to use PHPMailer if configured
    $smtpHost = getenv('SMTP_HOST') ?: $_ENV['SMTP_HOST'] ?? null;
    
    if ($smtpHost) {
        return sendEmailViaSmtp($toEmail, $subjectName, $resetLink);
    } else {
        // Fallback: Log to file for testing
        return logEmailLocally($toEmail, $subjectName, $resetLink);
    }
}

function sendEmailViaSmtp(string $toEmail, string $subjectName, string $resetLink): bool {
    require_once __DIR__ . '/vendor/autoload.php';
    
    $smtpHost = getenv('SMTP_HOST') ?: $_ENV['SMTP_HOST'] ?? null;
    $smtpPort = getenv('SMTP_PORT') ?: $_ENV['SMTP_PORT'] ?? null;
    $smtpUser = getenv('SMTP_USERNAME') ?: $_ENV['SMTP_USERNAME'] ?? null;
    $smtpPass = getenv('SMTP_PASSWORD') ?: $_ENV['SMTP_PASSWORD'] ?? null;
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: $_ENV['SMTP_FROM_EMAIL'] ?? $smtpUser;
    $fromName = 'OralSync';
    
    if (!$smtpHost || !$smtpPort || !$smtpUser || !$smtpPass) {
        return false;
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)$smtpPort;
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail);
        
        $subject = "Reset Your Password - {$subjectName}";
        $resetLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
        
        $html = <<<HTML
<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Password Reset</title>
  </head>
  <body style="margin:0;padding:0;background:#f8fafc;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;">
    <div style="padding:24px 12px;">
      <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
        <div style="padding:20px 22px;background:linear-gradient(135deg,#0d3b66,#0f172a);color:#fff;">
          <div style="font-weight:800;font-size:18px;">OralSync</div>
          <div style="opacity:0.9;margin-top:4px;font-size:13px;">Admin Password Reset</div>
        </div>
        <div style="padding:22px;">
          <div style="font-size:14px;color:#0f172a;line-height:1.6;">
            Hello Super Admin,<br /><br />
            You requested to reset your password. Click the link below to create a new password. 
            This link will expire in 1 hour.
          </div>
          <div style="margin-top:16px;">
            <a href="{$resetLink}" style="display:inline-block;background:#22c55e;color:#0b1f13;text-decoration:none;font-weight:800;padding:10px 14px;border-radius:999px;">Reset Password</a>
          </div>
          <div style="margin-top:16px;font-size:12px;color:#64748b;">
            Or copy this link: <br />
            <code style="word-break:break-all;">{$resetLink}</code>
          </div>
          <div style="margin-top:20px;font-size:12px;color:#64748b;">
            If you didn't request this, you can ignore this email.
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
HTML;
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}

function logEmailLocally(string $toEmail, string $subjectName, string $resetLink): bool {
    $logDir = __DIR__ . '/temp_emails';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] SuperAdmin Password Reset Email\n";
    $logEntry .= "To: {$toEmail}\n";
    $logEntry .= "Name: {$subjectName}\n";
    $logEntry .= "Reset Link: {$resetLink}\n";
    $logEntry .= str_repeat("=", 80) . "\n\n";
    
    $file = $logDir . '/emails.log';
    return file_put_contents($file, $logEntry, FILE_APPEND) !== false;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | OralSync Super Admin</title>
    <link rel="stylesheet" href="/tenant_style.css">
</head>
<body>
    <div class="t-wrap">
        <div class="t-shell" style="grid-template-columns: 1fr;">
            <section class="t-card">
                <h1 class="t-cardTitle">Forgot Password</h1>
                <div class="t-cardSub">Enter your username and we'll send you a link to reset your password.</div>

                <?php if ($message): ?>
                    <div style="padding: 12px; border-radius: 8px; margin-top: 12px; font-size: 13px; <?php echo $isError ? 'background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;' : 'background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;'; ?>">
                        <?php echo h($message); ?>
                    </div>
                <?php endif; ?>

                <form class="t-form" method="POST">
                    <div class="t-field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" required placeholder="Enter your username" value="<?php echo h($_POST['username'] ?? ''); ?>">
                    </div>
                    <button class="t-btn t-btnPrimary" type="submit">Send Reset Link</button>
                </form>

                <div class="t-foot">
                    <a href="superadmin_login.php" style="color: #0d3b66; text-decoration: none; font-weight: 600;">Back to Login</a>
                </div>
            </section>
        </div>
    </div>
</body>
</html>

