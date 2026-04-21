<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadmin_login.php');
    exit;
}
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($username === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
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
            // Check if email already exists
            $stmt2 = mysqli_prepare($conn, "SELECT id FROM super_admins WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt2, "s", $email);
            mysqli_stmt_execute($stmt2);
            $result2 = mysqli_stmt_get_result($stmt2);

            if (mysqli_fetch_assoc($result2)) {
                $error = 'Email already exists.';
            } else {
                // Create new superadmin account
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = mysqli_prepare($conn, "INSERT INTO super_admins (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())");
                mysqli_stmt_bind_param($insertStmt, "sss", $username, $email, $hashedPassword);

                if (mysqli_stmt_execute($insertStmt)) {
                    $success = 'Super admin account created successfully!';
                } else {
                    $error = 'Failed to create account. Please try again.';
                }
                mysqli_stmt_close($insertStmt);
            }
            mysqli_stmt_close($stmt2);
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OralSync | Create Super Admin</title>
    <link rel="stylesheet" href="style1.css">
    <link rel="stylesheet" href="tenant_style.css">
    <style>
        :root {
            --sa-primary: #0d3b66;
            --sa-bg: #f8fafc;
            --sa-border: #e2e8f0;
            --sa-muted: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--sa-bg);
            color: #1e293b;
            line-height: 1.6;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 32px;
            overflow-y: auto;
        }

        .sa-main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--sa-border);
        }

        .sa-main-header h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #0d3b66;
            margin: 0;
        }

        .sa-main-header span {
            color: var(--sa-muted);
            font-size: 0.9rem;
        }

        .sa-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sa-profile span {
            font-weight: 600;
        }

        .sa-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .sa-card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 15px;
        }

        .sa-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }

        .sa-form-group {
            display: flex;
            flex-direction: column;
        }

        .sa-form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 5px;
        }

        .sa-form-group input {
            padding: 8px 12px;
            border: 1px solid var(--sa-border);
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .sa-form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .sa-btn {
            background: var(--sa-primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
        }

        .sa-btn:hover {
            background: #0a2f52;
        }

        .success-message {
            background: #dcfce7;
            color: #166534;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #22c55e;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #ef4444;
        }
    </style>
</head>
<body>

<div class="container">
    <?php include __DIR__ . '/includes/sidebar_superadmin.php'; ?>

    <main class="main-content">
        <header class="sa-main-header">
            <div>
                <h1>Create Super Admin</h1>
                <span>Create a new super administrator account</span>
            </div>
            <div class="sa-profile">
                <span>Welcome, <strong>Super Admin</strong></span>
                <div class="sa-profile-avatar">🛡️</div>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="error-message"><?php echo h($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo h($success); ?></div>
        <?php endif; ?>

        <div class="sa-card">
            <h2 class="sa-card-title">New Super Admin Account</h2>
            <form method="POST" action="superadmin_create_superadmin.php">
                <div class="sa-form-grid">
                    <div class="sa-form-group">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" required />
                    </div>
                    <div class="sa-form-group">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" required />
                    </div>
                    <div class="sa-form-group">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" required minlength="8" />
                    </div>
                    <div class="sa-form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" required minlength="8" />
                    </div>
                </div>
                <div class="sa-form-actions">
                    <button class="sa-btn" type="submit">Create Account</button>
                </div>
            </form>
        </div>
    </main>
</div>

</body>
</html>