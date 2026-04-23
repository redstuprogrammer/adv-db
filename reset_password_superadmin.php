<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/session_utils.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $token = trim($_POST['token'] ?? '');

    if (empty($token)) {
        $error = 'Invalid or missing reset token.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Verify token and get superadmin email
            $stmt = $pdo->prepare("SELECT sa_email, expiry FROM superadmin_reset_tokens WHERE token = ? AND expiry > NOW()");
            $stmt->execute([$token]);
            $token_data = $stmt->fetch();

            if (!$token_data) {
                $error = 'Invalid or expired reset token.';
            } else {
                // Hash new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Update superadmin password
                $stmt = $pdo->prepare("UPDATE superadmins SET password = ? WHERE email = ?");
                $stmt->execute([$hashed_password, $token_data['sa_email']]);

                // Delete used token
                $stmt = $pdo->prepare("DELETE FROM superadmin_reset_tokens WHERE token = ?");
                $stmt->execute([$token]);

                $success = 'Password reset successfully! You can now log in with your new password.';
            }
        } catch (Exception $e) {
            $error = 'Database error occurred. Please try again.';
            error_log('Superadmin password reset error: ' . $e->getMessage());
        }
    }
} elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
    // Verify token exists and not expired (display form if valid)
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM superadmin_reset_tokens WHERE token = ? AND expiry > NOW()");
        $stmt->execute([$token]);
        if (!$stmt->fetch()) {
            $error = 'Invalid or expired reset token.';
        }
    } catch (Exception $e) {
        $error = 'Token verification failed.';
    }
} else {
    header('Location: superadmin_login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Super Admin Password - OralSync</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        .reset-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .reset-form-group {
            margin-bottom: 20px;
        }
        .reset-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .reset-form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .reset-form-group input:focus {
            outline: none;
            border-color: #0d3b66;
        }
        .sa-btn {
            width: 100%;
            padding: 14px;
            background: #0d3b66;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .sa-btn:hover {
            background: #0a2f52;
        }
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #0d3b66;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <div class="reset-container">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #0d3b66; font-size: 28px; margin: 0 0 10px 0; font-weight: 800;">Reset Password</h1>
            <p style="color: #64748b; margin: 0;">Super Admin Account</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <div class="back-link">
                <a href="superadmin_login.php">Return to Login</a>
            </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' || !empty($error)): ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token ?? ''); ?>">
                
                <div class="reset-form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                </div>
                
                <div class="reset-form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                </div>
                
                <button type="submit" class="sa-btn">Reset Password</button>
            </form>
            
            <div class="back-link">
                <a href="superadmin_login.php">Back to Login</a>
            </div>
        <?php else: ?>
            <div class="alert alert-error">Access denied. Invalid request.</div>
            <div class="back-link">
                <a href="superadmin_login.php">Return to Login</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Real-time password match validation
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const form = document.querySelector('form');

            if (password && confirmPassword && form) {
                confirmPassword.addEventListener('input', function() {
                    if (this.value && this.value !== password.value) {
                        this.setCustomValidity('Passwords do not match');
                    } else {
                        this.setCustomValidity('');
                    }
                });

                password.addEventListener('input', function() {
                    confirmPassword.setCustomValidity('');
                    if (this.value.length < 8) {
                        this.setCustomValidity('Password must be at least 8 characters');
                    } else {
                        this.setCustomValidity('');
                    }
                });

                form.addEventListener('submit', function(e) {
                    if (password.value !== confirmPassword.value) {
                        e.preventDefault();
                        alert('Passwords do not match!');
                    }
                });
            }
        });
    </script>
</body>
</html>
