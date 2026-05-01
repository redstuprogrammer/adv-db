<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function tableHasColumns(mysqli $conn, string $table, array $columns): bool {
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableSafe === '') {
        return false;
    }

    try {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableSafe}`");
        if (!$result) {
            return false;
        }
    } catch (mysqli_sql_exception $e) {
        return false;
    }

    $present = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $field = $row['Field'] ?? '';
        if ($field !== '') {
            $present[$field] = true;
        }
    }
    mysqli_free_result($result);

    foreach ($columns as $column) {
        if (!isset($present[$column])) {
            return false;
        }
    }
    return true;
}

$token = trim((string)($_GET['token'] ?? ''));
$id = (int)($_GET['id'] ?? 0);
$type = trim((string)($_GET['type'] ?? 'tenant')); // 'tenant' or 'user'
$message = '';
$isError = false;
$tokenValid = false;

// Verify token
if ($token && $id) {
    $hasNativeColumns = (
        ($type === 'tenant' && tableHasColumns($conn, 'tenants', ['password_reset_token', 'password_reset_expires'])) ||
        ($type !== 'tenant' && tableHasColumns($conn, 'users', ['password_reset_token', 'password_reset_expires']))
    );

    if ($hasNativeColumns) {
        if ($type === 'tenant') {
            $stmt = mysqli_prepare($conn, "SELECT password_reset_token, password_reset_expires FROM tenants WHERE tenant_id = ? AND password_reset_expires > NOW()");
        } else {
            $stmt = mysqli_prepare($conn, "SELECT password_reset_token, password_reset_expires FROM users WHERE user_id = ? AND password_reset_expires > NOW()");
        }
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $account = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);
            
            if ($account && password_verify($token, (string)$account['password_reset_token'])) {
                $tokenValid = true;
            }
        }
    } elseif (tableHasColumns($conn, 'password_resets', ['email', 'token', 'expires_at'])) {
        // Fallback flow: resolve email from account id, then validate against password_resets.
        $emailStmt = null;
        if ($type === 'tenant') {
            $emailStmt = mysqli_prepare($conn, "SELECT contact_email AS email FROM tenants WHERE tenant_id = ? LIMIT 1");
        } else {
            $emailStmt = mysqli_prepare($conn, "SELECT email FROM users WHERE user_id = ? LIMIT 1");
        }

        if ($emailStmt) {
            mysqli_stmt_bind_param($emailStmt, "i", $id);
            mysqli_stmt_execute($emailStmt);
            $emailRes = mysqli_stmt_get_result($emailStmt);
            $emailRow = mysqli_fetch_assoc($emailRes);
            mysqli_stmt_close($emailStmt);

            $accountEmail = trim((string)($emailRow['email'] ?? ''));
            if ($accountEmail !== '') {
                $tokenStmt = mysqli_prepare($conn, "SELECT token FROM password_resets WHERE email = ? AND expires_at > NOW() LIMIT 1");
                if ($tokenStmt) {
                    mysqli_stmt_bind_param($tokenStmt, "s", $accountEmail);
                    mysqli_stmt_execute($tokenStmt);
                    $tokenRes = mysqli_stmt_get_result($tokenStmt);
                    $resetRow = mysqli_fetch_assoc($tokenRes);
                    mysqli_stmt_close($tokenStmt);

                    if ($resetRow && password_verify($token, (string)$resetRow['token'])) {
                        $tokenValid = true;
                    }
                }
            }
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
        
        if ($type === 'tenant') {
            $hasNativeColumns = tableHasColumns($conn, 'tenants', ['password_reset_token', 'password_reset_expires']);
            $updateSql = $hasNativeColumns
                ? "UPDATE tenants SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE tenant_id = ?"
                : "UPDATE tenants SET password = ? WHERE tenant_id = ?";
            $updateStmt = mysqli_prepare($conn, $updateSql);
        } else {
            $hasNativeColumns = tableHasColumns($conn, 'users', ['password_reset_token', 'password_reset_expires']);
            $updateSql = $hasNativeColumns
                ? "UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE user_id = ?"
                : "UPDATE users SET password = ? WHERE user_id = ?";
            $updateStmt = mysqli_prepare($conn, $updateSql);
        }
        
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, "si", $hashedPassword, $id);
            if (mysqli_stmt_execute($updateStmt)) {
                if (tableHasColumns($conn, 'password_resets', ['email'])) {
                    $emailStmt = null;
                    if ($type === 'tenant') {
                        $emailStmt = mysqli_prepare($conn, "SELECT contact_email AS email FROM tenants WHERE tenant_id = ? LIMIT 1");
                    } else {
                        $emailStmt = mysqli_prepare($conn, "SELECT email FROM users WHERE user_id = ? LIMIT 1");
                    }
                    if ($emailStmt) {
                        mysqli_stmt_bind_param($emailStmt, "i", $id);
                        mysqli_stmt_execute($emailStmt);
                        $emailRes = mysqli_stmt_get_result($emailStmt);
                        $emailRow = mysqli_fetch_assoc($emailRes);
                        mysqli_stmt_close($emailStmt);
                        $accountEmail = trim((string)($emailRow['email'] ?? ''));
                        if ($accountEmail !== '') {
                            $deleteStmt = mysqli_prepare($conn, "DELETE FROM password_resets WHERE email = ?");
                            if ($deleteStmt) {
                                mysqli_stmt_bind_param($deleteStmt, "s", $accountEmail);
                                mysqli_stmt_execute($deleteStmt);
                                mysqli_stmt_close($deleteStmt);
                            }
                        }
                    }
                }

                $message = 'Password reset successfully! You can now log in with your new password.';
                $isError = false;
                $tokenValid = false; // Prevent further resets
                
                // Redirect to login
                $loginUrl = 'tenant_login.php';
                header("Refresh: 3; url=$loginUrl");
            } else {
                $message = 'An error occurred. Please try again.';
                $isError = true;
            }
            mysqli_stmt_close($updateStmt);
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | OralSync</title>
    <link rel="stylesheet" href="tenant_style.css">
</head>
<body>
    <div class="t-wrap">
        <div class="t-shell" style="grid-template-columns: 1fr;">
            <section class="t-card">
                <h1 class="t-cardTitle">Reset Password</h1>
                <div class="t-cardSub">Create a new password for your clinic account.</div>

                <?php if (!$tokenValid): ?>
                    <div style="padding: 12px; border-radius: 8px; margin-top: 12px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; font-size: 13px;">
                        This password reset link is invalid or has expired. Please request a new one.
                    </div>
                    <div class="t-foot" style="margin-top: 20px;">
                        <a href="forgot_password_tenant.php" style="color: #0d3b66; text-decoration: none; font-weight: 600;">Request New Reset Link</a>
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
