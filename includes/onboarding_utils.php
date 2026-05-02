<?php
/**
 * onboarding_utils.php - Utilities for tenant onboarding and email delivery
 */

function envOrNull(string $key): ?string {
    $val = getenv($key);
    if ($val === false || $val === null || $val === '') {
        if (isset($_ENV[$key])) $val = (string)$_ENV[$key];
        else if (isset($_SERVER[$key])) $val = (string)$_SERVER[$key];
        else $val = null;
    }
    if ($val === null) return null;
    $val = trim((string)$val);
    return $val === '' ? null : $val;
}

function buildTenantLoginUrl(string $slug): string {
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $scheme = (strtolower($forwardedProto) === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Get base path
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptName = str_replace('\\', '/', $scriptName);
    $dir = rtrim(pathinfo($scriptName, PATHINFO_DIRNAME), '/');
    $base = ($dir === '' || $dir === '.') ? '' : $dir;
    
    // Build full URL
    $url = $scheme . '://' . $host;
    if ($base !== '') {
        // If we are in /api/ or similar, we might need to go up
        if (basename($dir) === 'api') {
            $url .= dirname($base);
        } else {
            $url .= $base;
        }
    }
    $url = rtrim($url, '/') . '/tenant_login.php?tenant=' . rawurlencode($slug);
    
    return $url;
}

function sendTenantOnboardingEmail(array $params): array {
    $smtpHost = envOrNull('SMTP_HOST');
    $smtpPort = envOrNull('SMTP_PORT');
    $smtpUser = envOrNull('SMTP_USERNAME');
    $smtpPass = envOrNull('SMTP_PASSWORD');
    $fromEmail = envOrNull('SMTP_FROM_EMAIL') ?? $smtpUser;
    $fromName = envOrNull('SMTP_FROM_NAME') ?? 'OralSync';

    // Skip email sending if SMTP not configured
    if (!$smtpHost || !$smtpPort || !$smtpUser || !$smtpPass || !$fromEmail) {
        return ['sent' => false, 'error' => 'SMTP settings are missing.'];
    }

    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        return ['sent' => false, 'error' => 'PHPMailer is not installed.'];
    }

    require_once $autoloadPath;

    $clinicName = (string)($params['clinic_name'] ?? '');
    $ownerName = (string)($params['owner_name'] ?? '');
    $ownerEmail = (string)($params['owner_email'] ?? '');
    $tempPassword = (string)($params['temp_password'] ?? '');
    $loginUrl = (string)($params['login_url'] ?? '');

    if ($clinicName === '' || $ownerEmail === '' || $tempPassword === '' || $loginUrl === '') {
        return ['sent' => false, 'error' => 'Email parameters are incomplete.'];
    }

    $safeClinic = htmlspecialchars($clinicName, ENT_QUOTES, 'UTF-8');
    $safeOwner = htmlspecialchars($ownerName ?: 'Clinic Owner', ENT_QUOTES, 'UTF-8');
    $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');
    $safeTempPass = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');

    $subject = "Your OralSync login for {$clinicName}";

    $html = <<<HTML
<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>OralSync Onboarding</title>
  </head>
  <body style="margin:0;padding:0;background:#f8fafc;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;">
    <div style="padding:24px 12px;">
      <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <div style="padding:20px 22px;background:linear-gradient(135deg,#0d3b66,#0f172a);color:#fff;">
          <div style="font-weight:800;letter-spacing:0.2px;font-size:18px;">OralSync</div>
          <div style="opacity:0.9;margin-top:4px;font-size:13px;">Clinic onboarding details</div>
        </div>

        <div style="padding:22px;">
          <div style="font-size:14px;color:#0f172a;line-height:1.6;">
            Hi <strong>{$safeOwner}</strong>,<br />
            Your clinic <strong>{$safeClinic}</strong> has been set up in OralSync.
          </div>

          <div style="margin-top:16px;padding:14px 14px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;">
            <div style="font-size:12px;color:#64748b;margin-bottom:8px;">Your clinic login link</div>
            <div style="font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,Liberation Mono,Courier New,monospace;font-size:13px;color:#0f172a;word-break:break-all;">{$safeLoginUrl}</div>
            <div style="margin-top:8px;color:#64748b;font-size:12px;">
              Tip: to copy, highlight the link and press <strong>Ctrl+C</strong> (or tap-and-hold on mobile).
            </div>
            <div style="margin-top:14px;">
              <a href="{$safeLoginUrl}" style="display:inline-block;background:#22c55e;color:#0b1f13;text-decoration:none;font-weight:800;padding:10px 14px;border-radius:999px;">Open your OralSync Portal</a>
            </div>
          </div>

          <div style="margin-top:14px;padding:14px 14px;border:1px solid #bbf7d0;border-radius:14px;background:#ecfdf3;">
            <div style="font-size:12px;color:#166534;margin-bottom:8px;font-weight:700;">Temporary password</div>
            <div style="font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,Liberation Mono,Courier New,monospace;font-size:18px;color:#0f172a;letter-spacing:0.8px;"><strong>{$safeTempPass}</strong></div>
          </div>

          <div style="margin-top:16px;font-size:13px;color:#0f172a;line-height:1.6;">
            <div style="font-weight:800;color:#0d3b66;margin-bottom:6px;">Next steps</div>
            <ul style="margin:0;padding-left:18px;">
              <li>Open the link above and log in using this email address: <strong>{$ownerEmail}</strong></li>
              <li>Use the temporary password, then change it immediately after you sign in.</li>
              <li>Bookmark your clinic's login link for quick access.</li>
            </ul>
            <div style="margin-top:10px;color:#64748b;font-size:12px;">
              If the button doesn't work, copy & paste the URL into your browser.
            </div>
          </div>
        </div>

        <div style="padding:14px 22px;border-top:1px solid #e2e8f0;background:#f9fafb;color:#64748b;font-size:12px;line-height:1.4;">
          This is an automated message from OralSync. If you didn't expect this email, you can ignore it.
        </div>
      </div>
    </div>
  </body>
</html>
HTML;

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
        $mail->addAddress($ownerEmail, $ownerName ?: $ownerEmail);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = "OralSync login for {$clinicName}\n\nLogin URL: {$loginUrl}\nTemporary password: {$tempPassword}\n\nNext steps:\n- Log in using your email\n- Change your password after signing in\n- Bookmark your clinic link\n";

        $mail->send();
        return ['sent' => true];
    } catch (Throwable $e) {
        return ['sent' => false, 'error' => $e->getMessage()];
    }
}
