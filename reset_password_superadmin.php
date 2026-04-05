<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$token = trim((string)($_GET['token'] ?? ''));
$adminId = (int)($_GET['id'] ?? 0);
$message = '';
$isError = false;
$tokenValid = false;

// Verify token
if ($token && $adminId) {
    $stmt = mysqli_prepare($conn, "SELECT password_reset_token, password_reset_expires FROM super_admins WHERE id = ? AND password_reset_expires > NOW()");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $adminId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($res);
        
        if ($admin && password_verify($token, (string)$admin['password_reset_token'])) {
            $tokenValid = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $newPassword = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    
    if ($newPassword === '') {
        $message = 'Please enter a new password.';
        $isError = true;
    } elseif (strlen($newPassword) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $isError = true;
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $isError = true;
    } else {
        // Hash and update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = mysqli_prepare($conn, "UPDATE super_admins SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, "si", $hashedPassword, $adminId);
            if (mysqli_stmt_execute($updateStmt)) {
                $message = 'Password reset successfully! You can now log in with your new password.';
                $isError = false;
                $tokenValid = false; // Prevent further resets
                
                // Redirect to login after 3 seconds
                header('Refresh: 3; url=superadmin_login.php');
            } else {
                $message = 'An error occurred. Please try again.';
                $isError = true;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | OralSync Super Admin</title>
    <link rel="stylesheet" href="/tenant_style.css">
</head>
<body>
    <div class="t-wrap">
        <div class="t-shell" style="grid-template-columns: 1fr;">
            <section class="t-card">
                <h1 class="t-cardTitle">Reset Password</h1>
                <div class="t-cardSub">Create a new password for your super admin account.</div>

                <?php if (!$tokenValid): ?>
                    <div style="padding: 12px; border-radius: 8px; margin-top: 12px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; font-size: 13px;">
                        This password reset link is invalid or has expired. Please request a new one.
                    </div>
                    <div class="t-foot" style="margin-top: 20px;">
                        <a href="forgot_password_superadmin.php" style="color: #0d3b66; text-decoration: none; font-weight: 600;">Request New Reset Link</a>
                    </div>
                <?php else: ?>
                    <?php if ($message): ?>
                        <div style="padding: 12px; border-radius: 8px; margin-top: 12px; font-size: 13px; <?php echo $isError ? 'background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;' : 'background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;'; ?>">
                            <?php echo h($message); ?>
                        </div>
                    <?php endif; ?>

                    <form class="t-form" method="POST">
                        <div class="t-field">
                            <label for="password">New Password</label>
                            <input id="password" name="password" type="password" required placeholder="At least 8 characters">
                        </div>
                        <div class="t-field">
                            <label for="confirm_password">Confirm Password</label>
                            <input id="confirm_password" name="confirm_password" type="password" required placeholder="Re-enter your password">
                        </div>
                        <button class="t-btn t-btnPrimary" type="submit">Reset Password</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </div>
</body>
</html>

