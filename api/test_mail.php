<?php
// ============================================================
// FILE: /api/test_mail.php
// ============================================================
// TEMPORARY diagnostic file — DELETE after confirming mail works.
// Access: /api/test_mail.php?to=your@email.com
// ============================================================

// Basic protection — remove or change this key before deploying
$allowed_key = 'oralsync_test_2026';
if (($_GET['key'] ?? '') !== $allowed_key) {
    http_response_code(403);
    die('<h2>403 — Forbidden</h2><p>Pass ?key=oralsync_test_2026 to run this.</p>');
}

$to = trim($_GET['to'] ?? '');
if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    die('<h2>Missing or invalid ?to= email address.</h2>');
}

require_once __DIR__ . '/../config/mailer.php';

echo '<style>body{font-family:monospace;padding:24px;background:#f0f4f8}
      pre{background:#1e293b;color:#e2e8f0;padding:16px;border-radius:8px;overflow-x:auto}
      .ok{color:#22c55e;font-weight:bold}.fail{color:#ef4444;font-weight:bold}
      .warn{color:#f59e0b;font-weight:bold}h2{margin-top:24px}</style>';

echo '<h1>OralSync — Mail Diagnostic</h1>';

// ── Step 1: Show env vars (masked) ───────────────────────────
echo '<h2>Step 1: Azure Environment Variables</h2><pre>';

$vars = ['SMTP_HOST','SMTP_HOSTNAME','SMTP_USER','SMTP_USERNAME','SMTP_PASS','SMTP_PASSWORD','SMTP_PORT','SMTP_FROM','SMTP_FROM_NAME'];

$foundHost = false;
foreach ($vars as $var) {
    $val = getenv($var);
    if ($val) {
        // Mask sensitive values
        $masked = in_array($var, ['SMTP_PASS','SMTP_PASSWORD'])
            ? str_repeat('*', strlen($val))
            : (strlen($val) > 4 ? substr($val, 0, 3) . str_repeat('*', strlen($val) - 3) : '***');
        echo '<span class="ok">✓</span> ' . $var . ' = ' . $masked . "\n";
        if (in_array($var, ['SMTP_HOST','SMTP_HOSTNAME'])) $foundHost = true;
    } else {
        echo '<span class="fail">✗</span> ' . $var . ' = NOT SET' . "\n";
    }
}

if (!$foundHost) {
    echo "\n<span class=\"warn\">⚠ SMTP_HOST not found — mailer will fall back to PHP mail() which won't work on Azure.</span>\n";
}
echo '</pre>';

// ── Step 2: Attempt send ──────────────────────────────────────
echo '<h2>Step 2: Sending Test Email to ' . htmlspecialchars($to) . '</h2><pre>';

$subject = 'OralSync — Mail Test ' . date('Y-m-d H:i:s');
$html    = '
<!DOCTYPE html><html><body style="font-family:Arial;background:#f0f4f8;padding:24px">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:16px;padding:32px">
  <h2 style="color:#2563eb">OralSync Mail Test</h2>
  <p>This is a test email sent at <strong>' . date('Y-m-d H:i:s') . '</strong>.</p>
  <p>If you received this, the mailer is configured correctly.</p>
  <p style="color:#94a3b8;font-size:12px">Sent from test_mail.php — delete this file after confirming.</p>
</div>
</body></html>';
$plain = 'OralSync Mail Test — sent at ' . date('Y-m-d H:i:s') . '. Mailer is working.';

try {
    $mail = createMailer($to, $subject, $html, $plain);
    $mail->isHTML(true);
    $mail->send();
    echo '<span class="ok">✓ EMAIL SENT SUCCESSFULLY to ' . htmlspecialchars($to) . '</span>' . "\n\n";
    echo "Subject : {$subject}\n";
    echo 'Sent at  : ' . date('Y-m-d H:i:s') . "\n";
    echo "\nCheck your inbox (and spam). If it arrived, the mailer stack is fully working.\n";
    echo "You can now safely delete test_mail.php from the server.\n";
} catch (\Exception $e) {
    echo '<span class="fail">✗ SEND FAILED</span>' . "\n\n";
    echo 'Error : ' . $e->getMessage() . "\n\n";
    echo "Common fixes:\n";
    echo "  1. SMTP_HOST not set in Azure App Settings → Configuration\n";
    echo "  2. Gmail App Password wrong or expired (re-generate at myaccount.google.com)\n";
    echo "  3. 2-Step Verification not enabled on the Gmail account\n";
    echo "  4. Azure outbound port 587 blocked (unlikely but possible)\n";
}

echo '</pre>';

// ── Step 3: PHP mail() fallback check ────────────────────────
echo '<h2>Step 3: PHP mail() availability (fallback only)</h2><pre>';
echo function_exists('mail')
    ? '<span class="warn">⚠ mail() function exists but will NOT work on Azure App Service — use SMTP.</span>'
    : '<span class="fail">✗ mail() not available.</span>';
echo "\n</pre>";

echo '<p style="color:#94a3b8;font-size:12px;margin-top:32px">⚠ Delete /api/test_mail.php from the server once testing is done.</p>';
?>