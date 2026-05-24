<?php
// ============================================================
// FILE: /api/test_mail.php  — Enhanced v2
// DELETE from server after confirming mail works.
// Access: /api/test_mail.php?key=oralsync_test_2026&to=your@email.com
// ============================================================

$allowed_key = 'oralsync_test_2026';
if (($_GET['key'] ?? '') !== $allowed_key) {
    http_response_code(403);
    die('<h2>403 — Forbidden</h2>');
}

$to = trim($_GET['to'] ?? '');
if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    die('<h2>Missing or invalid ?to= email address.</h2>');
}

require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo '<style>
body{font-family:monospace;padding:24px;background:#f0f4f8}
pre{background:#1e293b;color:#e2e8f0;padding:16px;border-radius:8px;white-space:pre-wrap}
.ok{color:#22c55e;font-weight:bold}
.fail{color:#ef4444;font-weight:bold}
.warn{color:#f59e0b;font-weight:bold}
h2{margin-top:28px;color:#1e293b}
</style>';

echo '<div style="display:inline-block;background:#2563eb;color:#fff;font-weight:800;font-size:13px;padding:6px 16px;border-radius:20px;margin-bottom:16px;letter-spacing:1px">VERSION 2 — ' . date('Y-m-d H:i:s') . '</div>';
echo '<h1 style="color:#1e293b;margin-top:8px">OralSync — Mail Diagnostic</h1>';

// ── Step 1: Raw env var check + length ───────────────────────
echo '<h2>Step 1: Environment Variables + Length Check</h2><pre>';

$host     = getenv('SMTP_HOST')     ?: getenv('SMTP_HOSTNAME') ?: '';
$username = getenv('SMTP_USER')     ?: getenv('SMTP_USERNAME') ?: '';
$password = getenv('SMTP_PASS')     ?: getenv('SMTP_PASSWORD') ?: '';
$port     = (int)(getenv('SMTP_PORT') ?: 587);
$from     = getenv('SMTP_FROM')     ?: getenv('SMTP_FROM_EMAIL') ?: '';
$fromName = getenv('SMTP_FROM_NAME') ?: 'OralSync';

// Trimmed versions
$host_t     = trim($host);
$username_t = trim($username);
$password_t = trim($password);
$from_t     = trim($from);

$vars = [
    'SMTP_HOST'      => [$host,     $host_t],
    'SMTP_USERNAME'  => [$username, $username_t],
    'SMTP_PASSWORD'  => [$password, $password_t],
    'SMTP_PORT'      => [(string)$port, (string)$port],
    'SMTP_FROM'      => [$from,     $from_t],
    'SMTP_FROM_NAME' => [$fromName, $fromName],
];

foreach ($vars as $name => [$raw, $trimmed]) {
    if (!$raw) {
        echo '<span class="fail">✗</span> ' . $name . " = NOT SET\n";
        continue;
    }
    $icon      = '<span class="ok">✓</span>';
    $rawLen    = strlen($raw);
    $trimLen   = strlen($trimmed);
    $spaceWarn = ($rawLen !== $trimLen)
        ? ' <span class="fail">⚠ HAS WHITESPACE! raw=' . $rawLen . ' trimmed=' . $trimLen . '</span>'
        : ' (len=' . $rawLen . ')';
    $masked = in_array($name, ['SMTP_PASSWORD'])
        ? str_repeat('*', $trimLen)
        : (strlen($trimmed) > 4 ? substr($trimmed, 0, 3) . str_repeat('*', $trimLen - 3) : '***');
    echo $icon . ' ' . $name . ' = ' . $masked . $spaceWarn . "\n";
}

echo '</pre>';

// ── Step 2: Direct send (trimmed credentials, port 587) ──────
echo '<h2>Step 2: Direct Send — Port 587 TLS (trimmed credentials)</h2><pre>';

function tryMail($host, $username, $password, $port, $secure, $from, $fromName, $to, $label) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = $secure;
        $mail->Port       = $port;
        $mail->setFrom($from ?: $username, $fromName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'OralSync Test — ' . $label . ' — ' . date('H:i:s');
        $mail->Body    = '<p>Test email via <b>' . htmlspecialchars($label) . '</b> at ' . date('Y-m-d H:i:s') . '.</p><p>If you see this, the mailer is working.</p>';
        $mail->AltBody = 'Test email via ' . $label . ' at ' . date('Y-m-d H:i:s') . '.';
        $mail->send();
        return ['ok' => true];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

if (!$host_t || !$username_t || !$password_t) {
    echo '<span class="fail">✗ Skipped — missing credentials.</span>' . "\n";
} else {
    $r587 = tryMail($host_t, $username_t, $password_t, 587,
                    PHPMailer::ENCRYPTION_STARTTLS, $from_t, $fromName, $to, '587-TLS');
    if ($r587['ok']) {
        echo '<span class="ok">✓ SENT via port 587 TLS! Check inbox for ' . htmlspecialchars($to) . '</span>' . "\n";
    } else {
        echo '<span class="fail">✗ FAILED port 587 TLS</span>' . "\n";
        echo 'Error: ' . $r587['error'] . "\n";
    }
}
echo '</pre>';

// ── Step 3: Direct send — port 465 SSL ───────────────────────
echo '<h2>Step 3: Direct Send — Port 465 SSL (alternative)</h2><pre>';

if (!$host_t || !$username_t || !$password_t) {
    echo '<span class="fail">✗ Skipped — missing credentials.</span>' . "\n";
} else {
    $r465 = tryMail($host_t, $username_t, $password_t, 465,
                    PHPMailer::ENCRYPTION_SMTPS, $from_t, $fromName, $to, '465-SSL');
    if ($r465['ok']) {
        echo '<span class="ok">✓ SENT via port 465 SSL! Check inbox for ' . htmlspecialchars($to) . '</span>' . "\n";
        echo "\n<b>Fix:</b> Update SMTP_PORT to 465 in Azure App Settings.\n";
    } else {
        echo '<span class="fail">✗ FAILED port 465 SSL</span>' . "\n";
        echo 'Error: ' . $r465['error'] . "\n";
        echo "\n<span class=\"warn\">Both ports failed — password is likely revoked. Web dev needs to regenerate the App Password on myaccount.google.com.</span>\n";
    }
}
echo '</pre>';

echo '<p style="color:#94a3b8;font-size:12px;margin-top:32px">⚠ Delete /api/test_mail.php after confirming mail works.</p>';
?>