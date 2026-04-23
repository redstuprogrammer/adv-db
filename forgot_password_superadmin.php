<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if superadmin exists
            $stmt = $pdo->prepare("SELECT superadmin_id FROM superadmins WHERE email = ?");
            $stmt->execute([$email]);
            $superadmin = $stmt->fetch();

            if ($superadmin) {
                // Generate secure token
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

                // Insert token
                $stmt = $pdo->prepare("INSERT INTO superadmin_reset_tokens (token, sa_email, expiry) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expiry = ?");
                $stmt->execute([$token, $email, $expiry, $token, $expiry]);

                // Send email (PHPMailer or log)
                $resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                           '://' . $_SERVER['HTTP_HOST'] . 
                           dirname($_SERVER['SCRIPT_NAME']) . '/reset_password_superadmin.php?token=' . urlencode($token);

                $emailSent = sendResetEmail($email, $resetUrl);

                $message = $emailSent ? 'Reset link sent to your email!' : 'Token generated. Check temp_emails/emails.log for link.';
            } else {
                $message = 'If email registered, reset link sent (privacy).'; // Don't reveal emails
            }
        } catch (Exception $e) {
            $error = 'Error processing request. Try again.';
            error_log('Forgot password superadmin error: ' . $e->getMessage());
        }
    }
}

function sendResetEmail($email, $resetUrl) {
    $logDir = __DIR__ . '/temp_emails';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);

    $logEntry = date('Y-m-d H:i:s') . " SuperAdmin Reset\nTo: $email\nLink: $resetUrl\n" . str_repeat('=', 60) . "\n\n";
    return file_put_contents($logDir . '/emails.log', $logEntry, FILE_APPEND | LOCK_EX) !== false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - OralSync Super Admin</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        .auth-container { max-width: 420px; margin: 80px auto; padding: 2rem; background: white; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.12); }
        .auth-header { text-align: center; margin-bottom: 2rem; }
        .auth-header h1 { color: #0d3b66; font-size: 1.75rem; margin: 0 0 0.5rem 0; font-weight: 800; }
        .auth-header p { color: #64748b; margin: 0; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .form-group input { width: 100%; padding: 0.875rem 1rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; transition: all 0.2s; box-sizing: border-box; }
        .form-group input:focus { outline: none; border-color: #0d3b66; box-shadow: 0 0 0 3px rgba(13,59,102,0.1); }
        .sa-btn { width: 100%; padding: 1rem; background: #0d3b66; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .sa-btn:hover { background: #0a2f52; }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; font-weight: 500; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .back-link { text-align: center; margin-top: 1.5rem; }
        .back-link a { color: #0d3b66; text-decoration: none; font-weight: 500; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem;">
    <div class="auth-container">
        <div class="auth-header">
            <h1>Forgot Password?</h1>
            <p>Enter your email to receive a reset link</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="your-superadmin@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <button type="submit" class="sa-btn">Send Reset Link</button>
        </form>

        <div class="back-link">
            <a href="superadmin_login.php">← Back to Login</a>
        </div>
    </div>
</body>
</html>

