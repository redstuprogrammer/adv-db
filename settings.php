<?php
// settings.php - LIBRARY FILE - NO OUTPUT WHEN INCLUDED
// This file is included by other pages and contains shared configuration
// DO NOT output anything here - only when accessed directly

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 86400 * 7);
    session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);
    session_start();
}

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__ . '/');
}

require_once ROOT_PATH . 'includes/security_headers.php';
require_once ROOT_PATH . 'includes/connect.php';
require_once ROOT_PATH . 'includes/tenant_utils.php';
require_once ROOT_PATH . 'includes/tenant_settings_functions.php';

function redirect($path) {
    header("Location: " . $path);
    exit();
}

// Only execute settings page functionality when accessed directly
$isSettingsPage = (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'settings.php');

if ($isSettingsPage) {
    // Role Check Implementation - Ensure user is logged in
    if (!isset($_SESSION['role'])) {
        redirect('tenant_login.php');
    }

    // Role Check Implementation - Ensure user is an Admin or Superadmin
    $userRole = strtolower($_SESSION['role'] ?? '');
    if ($userRole !== 'admin' && $userRole !== 'superadmin') {
        redirect('tenant_login.php');
    }

    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    function saveTenantUploadImage(int $tenantId, string $fieldName, string $filenameBase): ?string {
        if (!isset($_FILES[$fieldName]) || !is_uploaded_file($_FILES[$fieldName]['tmp_name'])) {
            return null;
        }

        $file = $_FILES[$fieldName];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowed = ['jpg', 'jpeg', 'png'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed, true)) {
            return null;
        }

        $uploadDir = __DIR__ . '/assets/uploads/tenants/' . $tenantId . '/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return null;
        }

        $targetPath = $uploadDir . $filenameBase . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return null;
        }

        return 'assets/uploads/tenants/' . $tenantId . '/' . $filenameBase . '.' . $extension;
    }

    function sendTenantPasswordVerificationEmail(string $email, string $tenantName, string $resetUrl): array {
        $smtpHost = envOrNull('SMTP_HOST');
        $smtpPort = envOrNull('SMTP_PORT');
        $smtpUser = envOrNull('SMTP_USERNAME');
        $smtpPass = envOrNull('SMTP_PASSWORD');
        $fromEmail = envOrNull('SMTP_FROM_EMAIL') ?? $smtpUser;
        $fromName = envOrNull('SMTP_FROM_NAME') ?? 'OralSync';

        if (!$smtpHost || !$smtpPort || !$smtpUser || !$smtpPass || !$fromEmail) {
            return ['sent' => false, 'error' => 'SMTP settings are missing.'];
        }

        $autoloadPath = ROOT_PATH . 'vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            return ['sent' => false, 'error' => 'Email library is unavailable.'];
        }
        require_once $autoloadPath;

        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars($tenantName ?: 'Clinic Administrator', ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

        $subject = "Verify password change for {$tenantName}";
        $html = <<<HTML
<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Verify your password change</title>
  </head>
  <body style="margin:0;padding:0;background:#f8fafc;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;">
    <div style="padding:24px 12px;">
      <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <div style="padding:20px 22px;background:linear-gradient(135deg,#0d3b66,#0f172a);color:#fff;">
          <div style="font-weight:800;letter-spacing:0.2px;font-size:18px;">OralSync</div>
          <div style="opacity:0.9;margin-top:4px;font-size:13px;">Password change verification</div>
        </div>
        <div style="padding:22px;">
          <div style="font-size:14px;color:#0f172a;line-height:1.6;">
            Hi <strong>{$safeName}</strong>,<br />
            A password change request was made for your clinic account.
          </div>
          <div style="margin-top:16px;padding:14px 14px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;">
            <div style="font-size:12px;color:#64748b;margin-bottom:8px;">Confirm your password change</div>
            <div style="font-family:ui-monospace,Menlo,Monaco,Consolas,Liberation Mono,Courier New,monospace;font-size:13px;color:#0f172a;word-break:break-all;">{$safeUrl}</div>
            <div style="margin-top:8px;color:#64748b;font-size:12px;">Click the button below to verify and complete the password update.</div>
            <div style="margin-top:14px;">
              <a href="{$safeUrl}" style="display:inline-block;background:#22c55e;color:#0b1f13;text-decoration:none;font-weight:800;padding:10px 14px;border-radius:999px;">Verify Password Change</a>
            </div>
          </div>
          <div style="margin-top:16px;font-size:13px;color:#0f172a;line-height:1.6;">
            If you did not request this change, please contact your clinic administrator immediately.
          </div>
        </div>
        <div style="padding:14px 22px;border-top:1px solid #e2e8f0;background:#f9fafb;color:#64748b;font-size:12px;line-height:1.4;">This message was sent by OralSync.</div>
      </div>
    </div>
  </body>
</html>
HTML;

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
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = "Verify your password change: {$resetUrl}";
            $mail->send();
            return ['sent' => true];
        } catch (Throwable $e) {
            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }

    $tenantSlug = trim((string)($_GET['tenant'] ?? ''));
    requireTenantLogin($tenantSlug);

    $tenantName = getCurrentTenantName();
    $tenantId = getCurrentTenantId();

    // CRITICAL: Validate tenant_id matches the tenant_slug to prevent multi-tenancy confusion
    // Re-fetch from database to ensure we have the authoritative tenant_id for this slug
    if ($tenantSlug !== '') {
        $validationStmt = $conn->prepare("SELECT tenant_id FROM tenants WHERE subdomain_slug = ? LIMIT 1");
        if ($validationStmt) {
            $validationStmt->bind_param('s', $tenantSlug);
            $validationStmt->execute();
            $validationResult = $validationStmt->get_result();
            $validationRow = $validationResult->fetch_assoc();
            if ($validationRow && isset($validationRow['tenant_id'])) {
                $tenantId = (int)$validationRow['tenant_id'];
            }
            $validationStmt->close();
        }
    }

    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['change_password'])) {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($newPassword !== $confirmPassword) {
                $message = 'New passwords do not match.';
            } elseif (strlen($newPassword) < 8) {
                $message = 'Password must be at least 8 characters long.';
            } else {
                // Verify current password and send a verification email before applying the change
                $stmt = $conn->prepare("SELECT password, contact_email, company_name FROM tenants WHERE tenant_id = ?");
                $stmt->bind_param('i', $tenantId);
                $stmt->execute();
                $result = $stmt->get_result();
                $tenant = $result->fetch_assoc();

                if ($tenant && password_verify($currentPassword, $tenant['password'])) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = password_hash($token, PASSWORD_DEFAULT);
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $updateTokenStmt = $conn->prepare("UPDATE tenants SET password_reset_token = ?, password_reset_expires = ? WHERE tenant_id = ?");
                    if ($updateTokenStmt) {
                        $updateTokenStmt->bind_param('ssi', $tokenHash, $expiresAt, $tenantId);
                        if ($updateTokenStmt->execute()) {
                            $resetUrl = getAbsoluteBaseUrl() . '/reset_password_tenant.php?token=' . urlencode($token) . '&id=' . urlencode((string)$tenantId);
                            $emailResult = sendTenantPasswordVerificationEmail(
                                $tenant['contact_email'],
                                $tenant['company_name'],
                                $resetUrl
                            );

                            if ($emailResult['sent'] ?? false) {
                                $message = 'A verification email has been sent to your clinic email. Follow the instructions there to complete the password change.';
                            } else {
                                $message = 'Unable to send verification email. Please try again later.';
                                $clearStmt = $conn->prepare("UPDATE tenants SET password_reset_token = NULL, password_reset_expires = NULL WHERE tenant_id = ?");
                                if ($clearStmt) {
                                    $clearStmt->bind_param('i', $tenantId);
                                    $clearStmt->execute();
                                    $clearStmt->close();
                                }
                            }
                        } else {
                            $message = 'Unable to initialize password verification. Please try again later.';
                        }
                        $updateTokenStmt->close();
                    } else {
                        $message = 'Unable to initialize password verification. Please try again later.';
                    }
                } else {
                    $message = 'Current password is incorrect.';
                }
                $stmt->close();
            }
        } elseif (isset($_POST['save_login_settings'])) {
            // Save login customization settings into tenant_configs
            $brandBgColor = trim($_POST['brand_bg_color'] ?? '#001f3f');
            $brandTextColor = trim($_POST['brand_text_color'] ?? '#ffffff');
            $primaryBtnColor = trim($_POST['primary_btn_color'] ?? '#22c55e');
            $linkColor = trim($_POST['link_color'] ?? '#2563eb');

            $brandLogoPath = saveTenantUploadImage($tenantId, 'brand_logo_image', 'brand_logo') ?: null;
            $brandBgImagePath = saveTenantUploadImage($tenantId, 'brand_bg_image', 'brand_bg_image') ?: null;

            // Validate uploaded images
            $errors = [];
            if (isset($_FILES['brand_logo_image']) && $_FILES['brand_logo_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['brand_logo_image'];
                if ($file['size'] > 5 * 1024 * 1024) { // 5MB
                    $errors[] = 'Brand logo image must be smaller than 5MB.';
                }
                $imageInfo = getimagesize($file['tmp_name']);
                if ($imageInfo === false || $imageInfo[0] < 100 || $imageInfo[1] < 100) {
                    $errors[] = 'Brand logo image must be at least 100x100 pixels.';
                }
            }
            if (isset($_FILES['brand_bg_image']) && $_FILES['brand_bg_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['brand_bg_image'];
                if ($file['size'] > 5 * 1024 * 1024) { // 5MB
                    $errors[] = 'Brand background image must be smaller than 5MB.';
                }
                $imageInfo = getimagesize($file['tmp_name']);
                if ($imageInfo === false || $imageInfo[0] < 100 || $imageInfo[1] < 100) {
                    $errors[] = 'Brand background image must be at least 100x100 pixels.';
                }
            }

            if (!empty($errors)) {
                $message = implode(' ', $errors);
            } else {
                $configValues = [
                    'brand_bg_color' => $brandBgColor,
                    'brand_text_color' => $brandTextColor,
                    'primary_btn_color' => $primaryBtnColor,
                    'link_color' => $linkColor,
                ];

                if ($brandLogoPath !== null) {
                    $configValues['brand_logo_path'] = $brandLogoPath;
                }

                if ($brandBgImagePath !== null) {
                    $configValues['brand_bg_image_path'] = $brandBgImagePath;
                }

                if (saveTenantConfig($tenantId, $configValues)) {
                    $message = 'Login customization settings saved successfully!';
                } else {
                    $message = 'Unable to save login customization settings. Please try again.';
                }
            }
        } elseif (isset($_POST['change_password'])) {
            // Change password with email verification
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $message = 'All fields are required.';
            } elseif ($newPassword !== $confirmPassword) {
                $message = 'New passwords do not match.';
            } elseif (strlen($newPassword) < 8) {
                $message = 'New password must be at least 8 characters.';
            } else {
                // Get current tenant data
                $stmt = $conn->prepare("SELECT password_hash, email FROM tenants WHERE tenant_id = ?");
                $stmt->bind_param("i", $tenantId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $tenant = $result->fetch_assoc();
                    if (password_verify($currentPassword, $tenant['password_hash'])) {
                        // Generate token
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                        // Save token
                        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?");
                        $stmt->bind_param("sssss", $tenant['email'], $token, $expires, $token, $expires);
                        if ($stmt->execute()) {
                            // Send email
                            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
                            $subject = "Password Change Verification";
                            $body = "Click the link to confirm your password change: " . $resetLink;
                            if (mail($tenant['email'], $subject, $body)) {
                                $message = 'A verification email has been sent to your email address. Please check your email and click the link to confirm the password change.';
                            } else {
                                $message = 'Failed to send verification email. Please try again.';
                            }
                        } else {
                            $message = 'Failed to generate reset token. Please try again.';
                        }
                    } else {
                        $message = 'Current password is incorrect.';
                    }
                } else {
                    $message = 'Tenant not found.';
                }
                $stmt->close();
            }
        }
    }
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo h($tenantName); ?> | Settings</title>
        <link rel="stylesheet" href="tenant_style.css">
        <style>
          :root {
            --accent: #0d3b66;
            --border: #e2e8f0;
            --bg: #f8fafc;
          }

          .btn-primary {
            background: var(--accent);
            color: white;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: background 0.2s ease;
          }

          .btn-primary:hover {
            background: #0a2d4f;
          }

          .module-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
          }

          .form-group {
            margin-bottom: 16px;
          }

          .form-group label {
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
            color: var(--accent);
          }

          .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
          }

          .color-swatch-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
          }

          .color-swatch {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: white;
            cursor: pointer;
            width: 100%;
            text-align: left;
          }

          .swatch-box {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            border: 1px solid rgba(15, 23, 42, 0.12);
          }

          .swatch-label {
            font-size: 14px;
            color: #475569;
            font-weight: 600;
          }

          .color-input {
            width: 0;
            height: 0;
            opacity: 0;
            position: absolute;
            pointer-events: none;
          }

          .file-input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 12px;
            background: #ffffff;
            cursor: pointer;
          }

      .message {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 16px;
      }

      .message.success {
        background: rgba(34, 197, 94, 0.1);
        color: #16a34a;
        border: 1px solid rgba(34, 197, 94, 0.2);
      }

      .message.error {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
        border: 1px solid rgba(239, 68, 68, 0.2);
      }

      .login-customizer {
        background: white;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
      }

      .login-customizer h3 {
        margin-top: 0;
        color: var(--accent);
        font-size: 1.1rem;
      }

      .customizer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-top: 16px;
      }

      .login-preview {
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
      }

      .preview-clinic-logo {
        width: 80px;
        height: 80px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.18);
        margin-bottom: 14px;
        overflow: hidden;
        min-width: 80px;
      }

      .preview-clinic-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
      }

      .preview-button {
        display: inline-block;
        padding: 10px 16px;
        border-radius: 10px;
        background: var(--accent);
        color: white;
        text-decoration: none;
        margin-top: 14px;
        font-weight: 600;
      }

      /* High-Fidelity Preview Styles */
      .login-preview-container {
        background: #f8fafc;
        border: 2px solid var(--border);
        border-radius: 12px;
        padding: 0;
        margin-top: 20px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.1);
      }

      .preview-label {
        background: var(--accent);
        color: white;
        padding: 12px 16px;
        font-weight: 600;
        font-size: 13px;
      }

      .preview-split-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        min-height: 400px;
      }

      .preview-left-panel {
        background-size: cover;
        background-position: center;
        position: relative;
        padding: 40px 30px;
        color: white;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
      }

      .preview-left-panel::before {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.4);
        z-index: 1;
      }

      .preview-left-content {
        position: relative;
        z-index: 2;
      }

      .preview-clinic-logo {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 24px;
        margin-bottom: 20px;
      }

      .preview-clinic-name {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 8px;
      }

      .preview-subtitle {
        font-size: 13px;
        opacity: 0.9;
      }

      .preview-right-panel {
        background: white;
        padding: 40px 30px;
        display: flex;
        flex-direction: column;
        justify-content: center;
      }

      .preview-login-title {
        font-size: 24px;
        font-weight: 900;
        color: var(--accent);
        margin-bottom: 8px;
      }

      .preview-description {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 24px;
        line-height: 1.5;
      }

      .preview-signin-btn {
        display: inline-block;
        padding: 12px 24px;
        border-radius: 8px;
        background: var(--accent);
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        border: none;
        cursor: pointer;
        margin-bottom: 16px;
        transition: opacity 0.2s ease;
        width: 100%;
        text-align: center;
      }

      .preview-signin-btn:hover {
        opacity: 0.9;
      }

      .preview-forgot-link {
        color: #2563eb;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
      }

      .preview-forgot-link:hover {
        text-decoration: underline;
      }

      .preview-input {
        width: 100%;
        padding: 12px;
        margin-bottom: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
      }

      .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
      }

      .btn-secondary {
        background: #6b7280;
      }

      .btn-secondary:hover {
        background: #4b5563;
      }

      .hint-text {
        font-size: 12px;
        color: #64748b;
        margin-top: 6px;
        font-style: italic;
      }

      @media (max-width: 768px) {
        .preview-split-layout {
          grid-template-columns: 1fr;
          min-height: 500px;
        }
      }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">⚙️ Settings</div>
        <div style="display: flex; align-items: center; gap: 16px;">
          <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
          <div id="liveClock" class="live-clock-badge">00:00:00 AM</div>
        </div>
      </div>

      <div class="module-card">
        <h2 style="margin-bottom: 20px; color: var(--accent);">Change Password</h2>

        <?php if ($message): ?>
          <div class="message <?php echo strpos($message, 'success') !== false ? 'success' : 'error'; ?>">
            <?php echo h($message); ?>
          </div>
        <?php endif; ?>

        <form method="POST">
          <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" required>
          </div>

          <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required>
          </div>

          <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
          </div>

          <button type="submit" class="btn-primary">Change Password</button>
        </form>
      </div>

      <div class="login-customizer">
        <h3>Login Page Customization</h3>
        <p style="color: #64748b; margin-bottom: 20px;">Customize your clinic's login page appearance. Changes will be visible to all users logging into your clinic.</p>

        <?php
        // Load current login customization settings from tenant_configs
        $tenantSettings = array_merge([
            'brand_bg_color' => '#001f3f',
            'brand_text_color' => '#ffffff',
            'primary_btn_color' => '#22c55e',
            'link_color' => '#2563eb',
            'login_title' => 'Clinic Login',
            'login_description' => 'Please sign in to access your clinic portal.',
            'username_placeholder' => 'Username or Email',
            'password_placeholder' => 'Password',
            'brand_subtitle' => 'Powered by OralSync',
            'brand_logo_path' => '',
            'brand_bg_image_path' => ''
        ], getTenantConfig($tenantId));
        ?>

        <form method="POST" enctype="multipart/form-data" id="loginSettingsForm" onsubmit="return validateForm()">
          <input type="hidden" name="save_login_settings" value="1">
          
          <div class="customizer-grid">
            <div class="form-group">
              <label for="brand_bg_color">Brand Card Background</label>
              <div class="color-swatch-wrap">
                <button type="button" class="color-swatch" data-input="brand_bg_color">
                  <span class="swatch-box" id="swatch-brand-bg" style="background: <?php echo h($tenantSettings['brand_bg_color']); ?>;"></span>
                  <span class="swatch-label" id="label-brand-bg"><?php echo h($tenantSettings['brand_bg_color']); ?></span>
                </button>
                <input type="color" id="brand_bg_color" name="brand_bg_color" class="live-update color-input" data-target="preview-left-panel" data-style="backgroundColor" value="<?php echo h($tenantSettings['brand_bg_color']); ?>">
              </div>
              <div class="hint-text">Select the main brand panel background.</div>
            </div>

            <div class="form-group">
              <label for="brand_text_color">Brand Text Color</label>
              <div class="color-swatch-wrap">
                <button type="button" class="color-swatch" data-input="brand_text_color">
                  <span class="swatch-box" id="swatch-brand-text" style="background: <?php echo h($tenantSettings['brand_text_color']); ?>;"></span>
                  <span class="swatch-label" id="label-brand-text-color"><?php echo h($tenantSettings['brand_text_color']); ?></span>
                </button>
                <input type="color" id="brand_text_color" name="brand_text_color" class="live-update color-input" data-target="preview-left-panel" data-style="color" value="<?php echo h($tenantSettings['brand_text_color']); ?>">
              </div>
            </div>

            <div class="form-group">
              <label for="primary_btn_color">Sign In Button Color</label>
              <div class="color-swatch-wrap">
                <button type="button" class="color-swatch" data-input="primary_btn_color">
                  <span class="swatch-box" id="swatch-button-color" style="background: <?php echo h($tenantSettings['primary_btn_color']); ?>;"></span>
                  <span class="swatch-label" id="label-button-color"><?php echo h($tenantSettings['primary_btn_color']); ?></span>
                </button>
                <input type="color" id="primary_btn_color" name="primary_btn_color" class="live-update color-input" data-target="preview-signin-btn" data-style="backgroundColor" value="<?php echo h($tenantSettings['primary_btn_color']); ?>">
              </div>
            </div>

            <div class="form-group">
              <label for="link_color">Text Link Color</label>
              <div class="color-swatch-wrap">
                <button type="button" class="color-swatch" data-input="link_color">
                  <span class="swatch-box" id="swatch-link-color" style="background: <?php echo h($tenantSettings['link_color']); ?>;"></span>
                  <span class="swatch-label" id="label-link-color"><?php echo h($tenantSettings['link_color']); ?></span>
                </button>
                <input type="color" id="link_color" name="link_color" class="live-update color-input" data-target="preview-forgot-link" data-style="color" value="<?php echo h($tenantSettings['link_color']); ?>">
              </div>
            </div>
          </div>

          <div class="customizer-grid">
            <div class="form-group">
              <label for="brand_bg_image">Background Image Upload</label>
              <input type="file" id="brand_bg_image" name="brand_bg_image" accept=".jpg,.jpeg,.png" class="file-input" data-target="preview-left-panel" data-style="backgroundImage">
              <?php if (!empty($tenantSettings['brand_bg_image_path'])): ?>
                <div class="hint-text">Current image: <?php echo h($tenantSettings['brand_bg_image_path']); ?></div>
              <?php endif; ?>
            </div>
            <div class="form-group">
              <label for="brand_logo_image">Clinic Logo Upload</label>
              <input type="file" id="brand_logo_image" name="brand_logo_image" accept=".jpg,.jpeg,.png" class="file-input" data-target="preview-clinic-logo" data-property="logoPreview">
              <?php if (!empty($tenantSettings['brand_logo_path'])): ?>
                <div class="hint-text">Current logo: <?php echo h($tenantSettings['brand_logo_path']); ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-group">
            <label>Mobile App Download</label>
            <div style="padding: 20px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; max-width: 400px;">
              <p style="margin: 0 0 15px 0; color: #374151; font-weight: 500;">Download the OralSync Mobile App</p>
              <a href="https://drive.google.com/drive/folders/199ac2H14VbdUJSwrAsn3uJEL9Shbw_Xp?fbclid=IwY2xjawRFLrtleHRuA2FlbQIxMQBzcnRjBmFwcF9pZAEwAAEeOLp5Tv0f2lvvX684wbnpjO_n612da96L2LPI5fKjWbBH1LPqaR9--8jdfMQ_aem_CSWYBJ8xh9SJp0buAlJ17A" target="_blank" style="display: inline-block; background: #0d3b66; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: background 0.2s;">📱 Access Google Drive</a>
            </div>
            <div class="hint-text">Click to access the mobile app folder and download the APK for Android or iOS.</div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-primary">Save Login Settings</button>
            <button type="button" class="btn-primary btn-secondary" onclick="resetLoginPreview()">Reset to Default</button>
          </div>
        </form>

        <!-- WYSIWYG Login Preview -->
        <div class="login-preview-container">
          <div class="preview-label">📱 Live Preview - How Your Login Will Look</div>
          <div class="preview-split-layout">
            <div class="preview-left-panel" id="preview-left-panel" style="background-color: <?php echo h($tenantSettings['brand_bg_color']); ?>; color: <?php echo h($tenantSettings['brand_text_color']); ?>; background-image: <?php echo $tenantSettings['brand_bg_image_path'] ? "url('" . h($tenantSettings['brand_bg_image_path']) . "')" : 'none'; ?>;">
              <div class="preview-left-content">
                <div class="preview-clinic-logo" id="preview-clinic-logo">
                  <?php if (!empty($tenantSettings['brand_logo_path'])): ?>
                    <img src="<?php echo h($tenantSettings['brand_logo_path']); ?>" alt="Clinic Logo">
                  <?php else: ?>
                    OS
                  <?php endif; ?>
                </div>
                <div class="preview-clinic-name"><?php echo h($tenantName); ?></div>
              </div>
              <div class="preview-subtitle">Powered by OralSync</div>
            </div>

            <div class="preview-right-panel">
              <div class="preview-login-title">Clinic Login</div>
              <div class="preview-description">Please sign in to access your clinic portal.</div>
              <input type="text" class="preview-input" placeholder="Username or Email" readonly>
              <input type="password" class="preview-input" placeholder="Password" readonly>
              <button type="button" class="preview-signin-btn" id="preview-signin-btn" style="background-color: <?php echo h($tenantSettings['primary_btn_color']); ?>;">Sign in</button>
              <a href="#" class="preview-forgot-link" id="preview-forgot-link" style="color: <?php echo h($tenantSettings['link_color']); ?>;">Forgot password?</a>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <script>
    // Live Clock - Update every second
    function updateClock() {
      const clockElement = document.getElementById('liveClock');
      if (clockElement) {
        clockElement.textContent = new Date().toLocaleTimeString('en-US', { hour12: true });
      }
    }
    // Initialize clock immediately
    updateClock();
    // Update every second
    setInterval(updateClock, 1000);

    function updateSwatch(input) {
      const swatchId = input.id.replace(/_/g, '-');
      const label = document.getElementById(`label-${swatchId}`);
      const swatch = document.getElementById(`swatch-${swatchId}`);
      if (label) {
        label.textContent = input.value;
      }
      if (swatch) {
        swatch.style.background = input.value;
      }
    }

    function getContrastingColor(hex) {
      // Remove # if present
      hex = hex.replace('#', '');
      // Convert to RGB
      const r = parseInt(hex.substr(0, 2), 16);
      const g = parseInt(hex.substr(2, 2), 16);
      const b = parseInt(hex.substr(4, 2), 16);
      // Calculate luminance
      const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
      // Return black or white
      return luminance > 0.5 ? '#000000' : '#ffffff';
    }

    document.querySelectorAll('.live-update').forEach(input => {
      input.addEventListener('input', function() {
        const targetId = this.dataset.target;
        const target = document.getElementById(targetId);
        if (!target) return;

        const style = this.dataset.style;
        const property = this.dataset.property;
        const value = this.value;

        if (style === 'backgroundImage') {
          if (value.trim()) {
            target.style.backgroundImage = `url('${value}')`;
            target.style.backgroundSize = 'cover';
            target.style.backgroundPosition = 'center';
          } else {
            target.style.backgroundImage = 'none';
          }
        } else if (style) {
          target.style[style] = value;
          if (style === 'backgroundColor' && targetId === 'preview-left-panel') {
            target.style.color = getContrastingColor(value);
          }
        } else if (property) {
          target[property] = value;
        }

        if (this.type === 'color') {
          updateSwatch(this);
        }
      });

      input.dispatchEvent(new Event('input'));
    });

    document.querySelectorAll('.color-swatch').forEach(button => {
      button.addEventListener('click', function() {
        const inputId = this.dataset.input;
        const colorInput = document.getElementById(inputId);
        if (colorInput) {
          colorInput.click();
        }
      });
    });

    document.querySelectorAll('.file-input').forEach(input => {
      input.addEventListener('change', function() {
        const targetId = this.dataset.target;
        const target = document.getElementById(targetId);
        if (!target || !this.files.length) {
          return;
        }

        const file = this.files[0];
        const reader = new FileReader();
        reader.onload = function(event) {
          const value = event.target.result;
          if (input.dataset.property === 'logoPreview') {
            target.innerHTML = `<img src="${value}" alt="Logo preview">`;
          } else {
            target.style.backgroundImage = `url('${value}')`;
            target.style.backgroundSize = 'cover';
            target.style.backgroundPosition = 'center';
          }
        };
        reader.readAsDataURL(file);
      });
    });

    function resetLoginPreview() {
      if (!confirm('Reset all login settings to defaults?')) return;

      const defaults = {
        brand_bg_color: '#001f3f',
        brand_text_color: '#ffffff',
        primary_btn_color: '#22c55e',
        link_color: '#2563eb',
      };

      Object.keys(defaults).forEach(key => {
        const input = document.getElementById(key);
        if (input) {
          input.value = defaults[key];
          input.dispatchEvent(new Event('input'));
        }
      });

      document.getElementById('brand_bg_image').value = '';
      document.getElementById('brand_logo_image').value = '';
      const logoPreview = document.getElementById('preview-clinic-logo');
      if (logoPreview) {
        logoPreview.textContent = '🏥';
      }
    }

    function validateForm() {
      return true;
    }
  </script>
</body>
</html>
<?php
}
