<?php
session_start();
require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/tenant_utils.php'; // Using your Azure MySQLi connection

/**
 * Escapes HTML for safe output
 */
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputUser = trim((string)($_POST['username'] ?? ''));
    $inputPass = (string)($_POST['password'] ?? '');

    if ($inputUser !== '' && $inputPass !== '') {
        // 1. Check database for the user
        $stmt = mysqli_prepare($conn, "SELECT id, password_hash FROM super_admins WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $inputUser);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($admin = mysqli_fetch_assoc($result)) {
            // 2. PLAIN TEXT COMPARISON (Temporary for development)
            if ($inputPass === $admin['password_hash']) {
                session_regenerate_id(true);
                $_SESSION['superadmin_authed'] = true;
                $_SESSION['superadmin_id'] = $admin['id'];
                $_SESSION['superadmin_username'] = $inputUser;

                // Log superadmin login event
                logActivity($conn, 1, 'Superadmin Login', 'Superadmin logged in', $inputUser, 'superadmin', 'Super Admin');

                // Update last login timestamp
                $updateStmt = mysqli_prepare($conn, "UPDATE super_admins SET last_login = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($updateStmt, "i", $admin['id']);
                mysqli_stmt_execute($updateStmt);

                header('Location: superadmin_dash.php');
                exit;
            }
        }
        
        // If we reach here, authentication failed
        $error = 'Invalid username or password.';
        mysqli_stmt_close($stmt);
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>OralSync | Super Admin Login</title>
    <link rel="stylesheet" href="tenant_style.css" />
</head>
<body>
    <div class="t-wrap">
        <div class="t-shell" style="grid-template-columns: 1fr;">
            <section class="t-card">
                <h1 class="t-cardTitle">Super Admin Login</h1>
                <div class="t-cardSub">Enter your credentials to manage OralSync.</div>
                
                <?php if ($error): ?>
                    <div class="t-error" style="background: #fee2e2; color: #b91c1c; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; border: 1px solid #fecaca;">
                        <?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <form class="t-form" method="POST" action="superadmin_login.php">
                    <div class="t-field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" required />
                    </div>
                    <div class="t-field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" required />
                    </div>
                    <button class="t-btn t-btnPrimary" type="submit">Sign in</button>
                </form>

                <div style="margin-top: 16px; display: flex; gap: 8px; justify-content: space-between;">
                    <a href="forgot_password_superadmin.php" style="color: #0d3b66; text-decoration: none; font-size: 12px; font-weight: 600;">Forgot password?</a>
                </div>

                <div style="margin-top: 2rem; padding: 1rem; background: #f9fafb; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div style="font-weight: 600; margin-bottom: 0.5rem;">Site Links</div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="privacy.php" style="color: #0f172a; text-decoration: none;">Privacy Policy</a>
                        <a href="terms.php" style="color: #0f172a; text-decoration: none;">Terms</a>
                        <a href="contact.php" style="color: #0f172a; text-decoration: none;">Contact</a>
                    </div>
                </div>

                <div class="t-foot" style="margin-top: 1.5rem;">
                    This is a legitimate clinic management demo site. No software downloads are served from this domain.
                </div>

                <div class="t-foot" style="margin-top: 1.5rem; font-size: 0.8rem; opacity: 0.6;">
                    OralSync Platform Management &copy; <?php echo date('Y'); ?>
                </div>
            </section>
        </div>
    </div>
</body>
</html>
