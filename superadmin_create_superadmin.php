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

function sendSuperAdminWelcomeEmail($email, $tempPassword) {
    $smtpHost = getenv('SMTP_HOST') ?: $_ENV['SMTP_HOST'] ?? $_SERVER['SMTP_HOST'] ?? null;
    $smtpPort = getenv('SMTP_PORT') ?: $_ENV['SMTP_PORT'] ?? $_SERVER['SMTP_PORT'] ?? null;
    $smtpUser = getenv('SMTP_USERNAME') ?: $_ENV['SMTP_USERNAME'] ?? $_SERVER['SMTP_USERNAME'] ?? null;
    $smtpPass = getenv('SMTP_PASSWORD') ?: $_ENV['SMTP_PASSWORD'] ?? $_SERVER['SMTP_PASSWORD'] ?? null;
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: $_ENV['SMTP_FROM_EMAIL'] ?? $smtpUser;
    $fromName = 'OralSync';

    if (!$smtpHost || !$smtpPort || !$smtpUser || !$smtpPass) {
        return false;
    }

    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) return false;
    require_once $autoloadPath;

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)$smtpPort;

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Welcome to OralSync | Your Super Admin Account Details";
        
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safePass = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');
        
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $loginUrl = $scheme . '://' . $host . '/superadmin_login.php';

        $mail->Body = <<<HTML
<div style="font-family: sans-serif; max-width: 600px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
    <div style="background: #0d3b66; color: white; padding: 24px; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">OralSync Super Admin</h1>
        <p style="margin: 8px 0 0; opacity: 0.8;">A new super administrator account has been created for you</p>
    </div>
    <div style="padding: 24px; color: #334155; line-height: 1.6;">
        <p>Hello,</p>
        <p>You have been granted super administrator access to OralSync. You can log in using the details below:</p>
        
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 20px 0;">
            <p style="margin: 0 0 8px;"><strong>Login URL:</strong> <a href="{$loginUrl}" style="color: #0d3b66;">{$loginUrl}</a></p>
            <p style="margin: 0 0 8px;"><strong>Email:</strong> {$safeEmail}</p>
            <p style="margin: 0;"><strong>Temporary Password:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">{$safePass}</code></p>
        </div>
        
        <p style="font-size: 14px; color: #64748b;">For security, please change your password immediately after your first login.</p>
        
        <div style="text-align: center; margin-top: 32px;">
            <a href="{$loginUrl}" style="background: #0d3b66; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;">Login to Super Admin Portal</a>
        </div>
    </div>
    <div style="background: #f1f5f9; color: #94a3b8; padding: 16px; text-align: center; font-size: 12px;">
        &copy; OralSync - Advanced Dental Management System
    </div>
</div>
HTML;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $e->getMessage());
        return false;
    }
}

// Handle Account Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_sa'])) {
    $email = trim((string)($_POST['email'] ?? ''));
    $username = $email; // Default username to email

    if ($email === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Generate a random temporary password
        $password = bin2hex(random_bytes(6)); // 12 character random hex string

        // Check if email already exists
        $stmt2 = mysqli_prepare($conn, "SELECT id FROM super_admins WHERE email = ? OR username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt2, "ss", $email, $username);
        mysqli_stmt_execute($stmt2);
        $result2 = mysqli_stmt_get_result($stmt2);

        if (mysqli_fetch_assoc($result2)) {
            $error = 'A super admin with this email or username already exists.';
        } else {
            // Create new superadmin account
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = mysqli_prepare($conn, "INSERT INTO super_admins (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())");
            mysqli_stmt_bind_param($insertStmt, "sss", $username, $email, $hashedPassword);

            if (mysqli_stmt_execute($insertStmt)) {
                $success = 'Super admin account created successfully! An email has been sent.';
                sendSuperAdminWelcomeEmail($email, $password);
            } else {
                $error = 'Failed to create account. Please try again.';
            }
            mysqli_stmt_close($insertStmt);
        }
        mysqli_stmt_close($stmt2);
    }
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newUsername = trim((string)($_POST['new_username'] ?? ''));
    $currentPass = (string)($_POST['current_password'] ?? '');
    $newPass = (string)($_POST['new_password'] ?? '');
    $confirmPass = (string)($_POST['confirm_new_password'] ?? '');
    $currentAdminId = $_SESSION['superadmin_id'] ?? null;

    if (!$currentAdminId) {
        $sessUser = $_SESSION['superadmin_username'] ?? '';
        $idStmt = mysqli_prepare($conn, "SELECT id FROM super_admins WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($idStmt, "s", $sessUser);
        mysqli_stmt_execute($idStmt);
        $idRes = mysqli_stmt_get_result($idStmt);
        if ($row = mysqli_fetch_assoc($idRes)) {
            $currentAdminId = $row['id'];
            $_SESSION['superadmin_id'] = $currentAdminId;
        }
        mysqli_stmt_close($idStmt);
    }

    if ($newUsername === '') {
        $error = 'Username cannot be empty.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT password_hash FROM super_admins WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $currentAdminId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$user || !password_verify($currentPass, $user['password_hash'])) {
            $error = 'Invalid current password.';
        } else {
            $updateFields = ["username = ?"];
            $params = [$newUsername];
            $types = "s";

            if ($newPass !== '') {
                if (strlen($newPass) < 8) {
                    $error = 'New password must be at least 8 characters long.';
                } elseif ($newPass !== $confirmPass) {
                    $error = 'New passwords do not match.';
                } else {
                    $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                    $updateFields[] = "password_hash = ?";
                    $params[] = $hashed;
                    $types .= "s";
                }
            }

            if (!$error) {
                $params[] = $currentAdminId;
                $types .= "i";
                $sql = "UPDATE super_admins SET " . implode(", ", $updateFields) . " WHERE id = ?";
                $updateStmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($updateStmt, $types, ...$params);
                if (mysqli_stmt_execute($updateStmt)) {
                    $success = 'Account settings updated successfully.';
                    $_SESSION['superadmin_username'] = $newUsername;
                } else {
                    $error = 'Failed to update settings. Username might already be taken.';
                }
                mysqli_stmt_close($updateStmt);
            }
        }
    }
}

// Fetch current user data
$currentAdminId = $_SESSION['superadmin_id'] ?? null;
$currentUser = ['username' => ''];
if ($currentAdminId) {
    $stmt = mysqli_prepare($conn, "SELECT username, email FROM super_admins WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $currentAdminId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $currentUser = mysqli_fetch_assoc($res) ?: ['username' => ''];
    mysqli_stmt_close($stmt);
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
            <form method="POST">
                <div class="sa-form-grid">
                    <div class="sa-form-group">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" required placeholder="Will also be used as username" />
                        <p style="font-size: 0.75rem; color: var(--sa-muted); margin-top: 4px;">A temporary password will be auto-generated and sent to this email.</p>
                    </div>

                </div>
                <div class="sa-form-actions">
                    <button class="sa-btn" type="submit" name="create_sa">Create Account</button>
                </div>
            </form>
        </div>

        <div class="sa-card" style="margin-top: 40px;">
            <h2 class="sa-card-title">Account Settings</h2>
            <p style="font-size: 0.85rem; color: var(--sa-muted); margin-bottom: 20px;">Change your current account credentials</p>
            <form method="POST">
                <div class="sa-form-grid">
                    <div class="sa-form-group">
                        <label for="new_username">Username</label>
                        <input id="new_username" name="new_username" type="text" value="<?php echo h($currentUser['username'] ?? ''); ?>" required />
                    </div>
                    <div class="sa-form-group">
                        <label for="current_password">Current Password</label>
                        <input id="current_password" name="current_password" type="password" required placeholder="Required to save changes" />
                    </div>
                </div>
                <div class="sa-form-grid" style="margin-top: 15px;">
                    <div class="sa-form-group">
                        <label for="new_password">New Password (Optional)</label>
                        <input id="new_password" name="new_password" type="password" minlength="8" placeholder="Leave blank to keep current" />
                    </div>
                    <div class="sa-form-group">
                        <label for="confirm_new_password">Confirm New Password</label>
                        <input id="confirm_new_password" name="confirm_new_password" type="password" minlength="8" />
                    </div>
                </div>
                <div class="sa-form-actions">
                    <button class="sa-btn" type="submit" name="update_profile">Update Settings</button>
                </div>
            </form>
        </div>

    </main>
</div>

</body>
</html>