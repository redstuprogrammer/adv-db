<?php
session_start();
require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/connect.php'; // This calls your Azure MySQLi logic

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
        // Prepare statement using $conn from connect.php
        $stmt = mysqli_prepare($conn, "SELECT id, password_hash FROM super_admins WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $inputUser);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($admin = mysqli_fetch_assoc($result)) {
            // Verify the hashed password
            if (password_verify($inputPass, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['superadmin_authed'] = true;
                $_SESSION['superadmin_id'] = $admin['id'];
                
                // Update last login timestamp
                $updateStmt = mysqli_prepare($conn, "UPDATE super_admins SET last_login = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($updateStmt, "i", $admin['id']);
                mysqli_stmt_execute($updateStmt);

                header('Location: superadmin_dash.php');
                exit;
            }
        }
        // Generic error for security (don't tell them if the user exists or not)
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
            </section>
        </div>
    </div>
</body>
</html>