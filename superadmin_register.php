<?php
define('ROOT_PATH', __DIR__ . '/');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}
require_once ROOT_PATH . 'includes/security_headers.php';
require_once ROOT_PATH . 'includes/session_utils.php';

// Check if already logged in
$sessionManager = SessionManager::getInstance();
if ($sessionManager->isSuperAdmin()) {
    header('Location: superadmin_dash.php');
    exit();
}

// Only load database when needed
require_once ROOT_PATH . 'settings.php';
require_once ROOT_PATH . 'includes/connect.php';
require_once ROOT_PATH . 'includes/tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($username === '' || $password === '' || $confirmPassword === '') {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        // Check if username already exists
        $stmt = mysqli_prepare($conn, "SELECT id FROM super_admins WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_fetch_assoc($result)) {
            $error = 'Username already exists.';
        } else {
            // Create new superadmin account
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = mysqli_prepare($conn, "INSERT INTO super_admins (username, password_hash, created_at) VALUES (?, ?, NOW())");
            mysqli_stmt_bind_param($insertStmt, "ss", $username, $hashedPassword);

            if (mysqli_stmt_execute($insertStmt)) {
                $success = 'Account created successfully! You can now log in.';
            } else {
                $error = 'Failed to create account. Please try again.';
            }
            mysqli_stmt_close($insertStmt);
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>OralSync | Super Admin Registration</title>
    <link rel="stylesheet" href="/tenant_style.css" />
</head>
<body>
    <div class="t-wrap">
        <div class="t-shell" style="grid-template-columns: 1fr;">
            <section class="t-card">
                <h1 class="t-cardTitle">Super Admin Registration</h1>
                <div class="t-cardSub">Create a new super admin account.</div>

                <?php if ($error): ?>
                    <div class="t-error" style="background: #fee2e2; color: #b91c1c; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; border: 1px solid #fecaca;">
                        <?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div style="background: #d1fae5; color: #065f46; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; border: 1px solid #a7f3d0;">
                        <?php echo h($success); ?>
                    </div>
                <?php endif; ?>

                <form class="t-form" method="POST" action="superadmin_register.php">
                    <div class="t-field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" required />
                    </div>
                    <div class="t-field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" required minlength="8" />
                    </div>
                    <div class="t-field">
                        <label for="confirm_password">Confirm Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" required minlength="8" />
                    </div>
                    <button class="t-btn t-btnPrimary" type="submit">Create Account</button>
                </form>

                <div style="margin-top: 16px; text-align: center;">
                    <a href="superadmin_login.php" style="color: #0d3b66; text-decoration: none; font-size: 14px; font-weight: 600;">Back to Login</a>
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