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
    // Ensure user is logged in as Admin or Superadmin
    $sessionManager = SessionManager::getInstance();
    $userRole = strtolower($sessionManager->getRole() ?? '');
    
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
              <a href="{$safeUrl}" style="display:inline-block;background:#0d3b66;color:#ffffff;text-decoration:none;font-weight:800;padding:10px 14px;border-radius:999px;">Verify Password Change</a>
            </div>
          <div style="margin-top:16px;font-size:13px;color:#0f172a;line-height:1.6;">
            If you did not request this change, please contact your clinic administrator immediately.
          </div>
        <div style="padding:14px 22px;border-top:1px solid #e2e8f0;background:#f9fafb;color:#64748b;font-size:12px;line-height:1.4;">This message was sent by OralSync.</div>
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

    // Self-healing database check: ensure announcements table exists
    $conn->query("CREATE TABLE IF NOT EXISTS `announcements` (
      `id` int NOT NULL AUTO_INCREMENT,
      `tenant_id` int NULL,
      `title` varchar(255) NOT NULL,
      `content` text NOT NULL,
      `category` varchar(255) DEFAULT 'General',
      `image_path` varchar(511) DEFAULT NULL,
      `status` enum('active','archived') DEFAULT 'active',
      `publish_date` datetime NOT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `fk_tenant_announcement` (`tenant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("ALTER TABLE announcements MODIFY COLUMN tenant_id int NULL");
    $conn->query("ALTER TABLE announcements MODIFY COLUMN category varchar(255) DEFAULT 'General'");
    $conn->query("ALTER TABLE announcements MODIFY COLUMN publish_date datetime NOT NULL");

    $message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['change_account_settings'])) {
            $newUsername = trim($_POST['username'] ?? '');
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Fetch current tenant data
            $stmt = $conn->prepare("SELECT password, contact_email, company_name, username FROM tenants WHERE tenant_id = ?");
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            $tenant = $result->fetch_assoc();
            $stmt->close();

            if (!$tenant) {
                $message = 'Error fetching account details.';
            } else {
                $updatesMade = false;
                $passwordChangeRequested = !empty($newPassword);

                // Handle Username Update
                if ($newUsername !== $tenant['username']) {
                    if (empty($newUsername)) {
                        $message = 'Username cannot be empty.';
                    } else {
                        $updateUsernameStmt = $conn->prepare("UPDATE tenants SET username = ? WHERE tenant_id = ?");
                        $updateUsernameStmt->bind_param('si', $newUsername, $tenantId);
                        if ($updateUsernameStmt->execute()) {
                            $updatesMade = true;
                            $message = 'Username updated successfully. ';
                            // Sync session with new username
                            $tenantData = $sessionManager->getTenantData();
                            if ($tenantData) {
                                $tenantData['username'] = $newUsername;
                                $sessionManager->loginTenantUser($tenantSlug, $tenantData);
                            }
                        } else {
                            $message = 'Error updating username. ';
                        }
                        $updateUsernameStmt->close();
                    }
                }

                // Handle Password Update
                if ($passwordChangeRequested) {
                    if ($newPassword !== $confirmPassword) {
                        $message .= 'New passwords do not match.';
                    } elseif (strlen($newPassword) < 8) {
                        $message .= 'Password must be at least 8 characters long.';
                    } elseif (!password_verify($currentPassword, $tenant['password'])) {
                        $message .= 'Current password is incorrect.';
                    } else {
                        // Verify current password and send a verification email
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
                                    $message .= 'A verification email has been sent to your clinic email. Follow the instructions there to complete the password change.';
                                } else {
                                    $message .= 'Unable to send verification email. Please try again later.';
                                    $clearStmt = $conn->prepare("UPDATE tenants SET password_reset_token = NULL, password_reset_expires = NULL WHERE tenant_id = ?");
                                    if ($clearStmt) {
                                        $clearStmt->bind_param('i', $tenantId);
                                        $clearStmt->execute();
                                        $clearStmt->close();
                                    }
                                }
                            } else {
                                $message .= 'Unable to initialize password verification. Please try again later.';
                            }
                            $updateTokenStmt->close();
                        }
                    }
                } elseif ($updatesMade && empty($message)) {
                    $message = 'Account settings updated successfully.';
                } elseif (!$updatesMade && !$passwordChangeRequested) {
                    $message = 'No changes were made.';
                }
            }
        } elseif (isset($_POST['save_login_settings'])) {
            // Check if this is a full reset to defaults
            if (!empty($_POST['reset_to_default'])) {
                $resetDefaults = [
                    'brand_bg_color'      => '#001f3f',
                    'brand_text_color'    => '#ffffff',
                    'primary_btn_color'   => '#0d3b66',
                    'link_color'          => '#2563eb',
                    'card_bg_color'       => '#ffffff',
                    'brand_logo_path'     => '',
                    'brand_bg_image_path' => '',
                ];
                if (saveTenantConfig($tenantId, $resetDefaults)) {
                    $message = 'Login customization settings reset to defaults successfully!';
                } else {
                    $message = 'Unable to reset settings. Please try again.';
                }
            } else {
                // Save login customization settings into tenant_configs
                $primaryBtnColor = trim($_POST['primary_btn_color'] ?? '#0d3b66');
                $linkColor = trim($_POST['link_color'] ?? '#2563eb');
                $cardBgColor = trim($_POST['card_bg_color'] ?? '#ffffff');

                // Validate uploaded images
                $errors = [];
                if (isset($_FILES['brand_logo_image']) && $_FILES['brand_logo_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['brand_logo_image'];
                    if ($file['size'] > 5 * 1024 * 1024) { // 5MB
                        $errors[] = 'Brand logo image must be smaller than 5MB.';
                    }
                    $imageInfo = getimagesize($file['tmp_name']);
                    if ($imageInfo === false) {
                        $errors[] = 'Brand logo image must be a valid image file.';
                    }
                }
                if (isset($_FILES['brand_bg_image']) && $_FILES['brand_bg_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['brand_bg_image'];
                    if ($file['size'] > 5 * 1024 * 1024) { // 5MB
                        $errors[] = 'Brand background image must be smaller than 5MB.';
                    }
                    $imageInfo = getimagesize($file['tmp_name']);
                    if ($imageInfo === false) {
                        $errors[] = 'Brand background image must be a valid image file.';
                    }
                }

                if (!empty($errors)) {
                    $message = implode(' ', $errors);
                } else {
                    $brandLogoPath = saveTenantUploadImage($tenantId, 'brand_logo_image', 'brand_logo') ?: null;
                    $brandBgImagePath = saveTenantUploadImage($tenantId, 'brand_bg_image', 'brand_bg_image') ?: null;
                    $configValues = [
                        'primary_btn_color' => $primaryBtnColor,
                        'link_color' => $linkColor,
                        'card_bg_color' => $cardBgColor,
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
            } // end else (not reset_to_default)
        } elseif (isset($_POST['announcement_action'])) {
            $action = $_POST['announcement_action'];
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $category = trim($_POST['category'] ?? 'Clinical Update');
            $rawPublishDate = trim($_POST['publish_date'] ?? date('Y-m-d H:i:s'));
            $publishDate = date('Y-m-d H:i:s', strtotime($rawPublishDate));
            $status = trim($_POST['status'] ?? 'active');
            $annId = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;

            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO announcements (tenant_id, title, content, category, publish_date, status) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('isssss', $tenantId, $title, $content, $category, $publishDate, $status);
                    if ($stmt->execute()) {
                        $message = 'Announcement added successfully!';
                    } else {
                        $message = 'Error adding announcement.';
                    }
                    $stmt->close();
                }
            } elseif ($action === 'edit' && $annId > 0) {
                $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, category = ?, publish_date = ?, status = ? WHERE id = ? AND tenant_id = ?");
                if ($stmt) {
                    $stmt->bind_param('sssssii', $title, $content, $category, $publishDate, $status, $annId, $tenantId);
                    if ($stmt->execute()) {
                        $message = 'Announcement updated successfully!';
                    } else {
                        $message = 'Error updating announcement.';
                    }
                    $stmt->close();
                }
            } elseif ($action === 'delete' && $annId > 0) {
                $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND tenant_id = ?");
                if ($stmt) {
                    $stmt->bind_param('ii', $annId, $tenantId);
                    if ($stmt->execute()) {
                        $message = 'Announcement deleted successfully!';
                    } else {
                        $message = 'Error deleting announcement.';
                    }
                    $stmt->close();
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
        <title><?php echo h($tenantName); ?> | Settings</title>
        <link rel="stylesheet" href="tenant_style.css">
        <style>
          html, body {
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden; /* Prevent double scrollbars */
          }

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
            width: 100%;
            position: relative;
          }

          .color-swatch {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 16px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #ffffff;
            cursor: pointer;
            height: 48px;
            box-sizing: border-box;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            min-width: 0; /* Prevent flex-item from overflowing */
          }

          .color-swatch:hover {
            border-color: #94a3b8;
            background: #f8fafc;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
          }

          .swatch-box {
            width: 24px;
            height: 24px;
            min-width: 24px;
            min-height: 24px;
            border-radius: 8px;
            border: 1.5px solid rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
            display: block;
            background-color: transparent; /* Fallback */
          }

          .swatch-label {
            font-size: 14px;
            color: #334155;
            font-weight: 700;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            letter-spacing: -0.2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
          }

          .color-input {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 1px;
            height: 1px;
            opacity: 0;
            cursor: pointer;
            pointer-events: none;
            border: none;
            padding: 0;
            margin: 0;
            z-index: -1;
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

      .theme-preset-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 10px;
        margin-top: 10px;
      }

      .theme-preset-btn {
        border: 1px solid var(--border);
        border-radius: 10px;
        background: #fff;
        padding: 10px 12px;
        text-align: left;
        cursor: pointer;
        font-weight: 600;
        color: #334155;
      }

      .theme-preset-btn:hover {
        border-color: #94a3b8;
      }

      .theme-preset-btn.is-active {
        border-color: var(--accent);
        box-shadow: 0 0 0 2px rgba(13, 59, 102, 0.14);
      }

      .theme-preview-dots {
        display: flex;
        gap: 6px;
        margin-top: 8px;
      }

      .theme-dot {
        width: 14px;
        height: 14px;
        border-radius: 999px;
        border: 1px solid rgba(15, 23, 42, 0.12);
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
        background: #e8ecf0;
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

      /* Full-page chrome simulating the actual login page background */
      .preview-page-chrome {
        background: linear-gradient(135deg, #e8ecf0 0%, #d1dbe8 50%, #c8e6d0 100%);
        padding: 30px 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 420px;
        position: relative;
      }

      /* Top-left logo that appears on the full page (not inside the card) */
      .preview-topleft-logo {
        display: none;
        align-items: center;
        gap: 10px;
        position: absolute;
        top: 18px;
        left: 18px;
        z-index: 5;
        padding: 10px 14px;
        border-radius: 12px;
        background: rgba(15, 23, 42, 0.55);
        border: 1px solid rgba(255, 255, 255, 0.28);
        backdrop-filter: blur(6px);
        color: #fff;
      }

      .preview-page-chrome.has-custom-bg {
        align-items: center;
        justify-content: center;
      }

      .preview-page-chrome.has-custom-bg .preview-card-wrap {
        max-width: 400px;
      }

      .preview-page-chrome.has-custom-bg .preview-split-layout {
        grid-template-columns: 1fr;
      }

      .preview-page-chrome.has-custom-bg .preview-topleft-logo {
        display: inline-flex;
      }

      /* The two-panel card wrapper */
      .preview-card-wrap {
        width: 100%;
        max-width: 680px;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(15,23,42,0.18), 0 4px 16px rgba(15,23,42,0.08);
      }

      .preview-split-layout {
        display: grid;
        grid-template-columns: 55% 45%;
        min-height: 340px;
      }

      /* Left dark navy panel */
      .preview-left-panel {
        background-color: #0d2340;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        position: relative;
        padding: 24px 20px;
        color: white;
        display: flex;
        flex-direction: column;
        image-rendering: high-quality;
      }

      .preview-left-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.35);
        z-index: 1;
      }

      .preview-left-content {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        height: 100%;
      }

      /* Brand row: logo box + clinic name + subtitle */
      .preview-left-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 18px;
      }

      .preview-left-logo-box {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
      }

      .preview-left-logo-box img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        image-rendering: high-quality;
      }

      .preview-clinic-name {
        font-size: 14px;
        font-weight: 700;
        line-height: 1.2;
      }

      .preview-subtitle {
        font-size: 10px;
        opacity: 0.75;
      }

      .preview-left-body {
        flex: 1;
      }

      .preview-coming-soon-box {
        border: 1px dashed rgba(255,255,255,0.35);
        border-radius: 8px;
        padding: 10px 12px;
        margin-top: 8px;
      }

      /* Right white login panel */
      .preview-right-panel {
        background: white;
        padding: 28px 24px;
        display: flex;
        flex-direction: column;
        justify-content: center;
      }

      .preview-login-title {
        font-size: 18px;
        font-weight: 900;
        color: #0d2340;
        margin-bottom: 4px;
      }

      .preview-description {
        font-size: 11px;
        color: #64748b;
        margin-bottom: 16px;
        line-height: 1.5;
      }

      .preview-field-group {
        margin-bottom: 10px;
      }

      .preview-field-label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 4px;
      }

      .preview-signin-btn {
        display: block;
        width: 100%;
        padding: 10px 20px;
        border-radius: 8px;
        color: white;
        text-decoration: none;
        font-weight: 700;
        font-size: 13px;
        border: none;
        cursor: pointer;
        margin-bottom: 12px;
        transition: opacity 0.2s ease;
        text-align: center;
      }

      .preview-signin-btn:hover {
        opacity: 0.9;
      }

      .preview-forgot-link {
        text-decoration: none;
        font-size: 11px;
        font-weight: 500;
        cursor: pointer;
        display: block;
        margin-bottom: 8px;
      }

      .preview-forgot-link:hover {
        text-decoration: underline;
      }

      .preview-no-account {
        font-size: 10px;
        color: #94a3b8;
      }

      .preview-input {
        width: 100%;
        padding: 8px 10px;
        margin-bottom: 0;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 12px;
        box-sizing: border-box;
        background: #fafafa;
      }

      .preview-clinic-logo {
        /* legacy - kept for reset function reference */
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

      /* Announcements styles */
      .ann-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
      }
      .ann-table th {
        text-align: left;
        padding: 12px;
        background: #f8fafc;
        color: #64748b;
        font-weight: 700;
        font-size: 12px;
        border-bottom: 2px solid #e2e8f0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      .ann-table td {
        padding: 14px 12px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        vertical-align: middle;
      }
      .ann-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        background: #f3e8ff;
        color: #7e22ce;
      }
      .ann-badge.clinical-update { background: #e0f2fe; color: #0369a1; }
      .ann-badge.patient-care { background: #dcfce7; color: #166534; }
      .ann-badge.facility-news { background: #fef3c7; color: #d97706; }
       .ann-badge.staff-training { background: #f3e8ff; color: #7e22ce; }
      .ann-badge.system-announcement { background: #ffe4e6; color: #be123c; }

      .status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
      }
      .status-badge.active { background: #dcfce7; color: #166534; }
      .status-badge.archived { background: #f1f5f9; color: #64748b; }
      
      .btn-ann-action {
        background: transparent;
        border: 1px solid #cbd5e1;
        color: #475569;
        padding: 6px 10px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.2s ease;
      }
      .btn-ann-action:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
        color: var(--accent);
      }
      .btn-ann-danger {
        border-color: #fee2e2;
        color: #ef4444;
      }
      .btn-ann-danger:hover {
        background: #fef2f2;
        border-color: #fca5a5;
        color: #b91c1c;
      }
      .ann-preview-split-layout {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 24px;
        align-items: start;
      }
      @media (max-width: 991px) {
        .ann-preview-split-layout {
          grid-template-columns: 1fr;
        }
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

      <!-- Announcements Section -->
      <div class="module-card" style="margin-bottom: 32px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <div>
            <h2 style="margin: 0; color: var(--accent);">📢 Clinic Announcements</h2>
            <p style="color: #64748b; margin: 4px 0 0 0; font-size: 14px;">Create and manage announcements shown to your dentists and receptionists on their dashboards.</p>
          </div>
          <button type="button" class="btn-primary" onclick="showAddAnnouncementForm()">+ Add Announcement</button>
        </div>

        <!-- Announcement Form Section (hidden by default) -->
        <div id="announcementFormSection" style="display:none; border: 1.5px solid var(--border); border-radius: 8px; padding: 20px; margin-bottom: 20px; background: #f8fafc;">
          <h3 id="formTitle" style="margin-top:0; color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 20px;">Add Announcement</h3>
          
          <div class="ann-preview-split-layout">
            <!-- Left Column: Edit Form -->
            <form method="POST" style="margin: 0;">
              <input type="hidden" name="announcement_action" id="announcementAction" value="add">
              <input type="hidden" name="announcement_id" id="announcementId" value="">
              
              <div class="form-group">
                <label for="ann_title" style="font-weight:600;">Title</label>
                <input type="text" id="ann_title" name="title" required style="width:100%; padding:10px; border: 1px solid var(--border); border-radius: 8px; font-size:14px; box-sizing:border-box; background:white;" onkeyup="updateAnnouncementPreview()">
              </div>
              
              <div class="form-group">
                <label for="ann_category" style="font-weight:600;">Category / Tag</label>
                <select id="ann_category" name="category" required style="width:100%; padding:10px; border: 1px solid var(--border); border-radius: 8px; font-size:14px; box-sizing:border-box; background:white; height:42px;" onchange="updateAnnouncementPreview()">
                  <option value="System Maintenance">System Maintenance</option>
                  <option value="System Update">System Update</option>
                  <option value="System Alert">System Alert</option>
                </select>
              </div>
              
              <div class="form-group">
                <label for="ann_content" style="font-weight:600;">Content</label>
                <textarea id="ann_content" name="content" required style="width:100%; height:120px; padding:10px; border: 1px solid var(--border); border-radius: 8px; font-family:inherit; font-size:14px; box-sizing:border-box; background:white; resize:vertical;" onkeyup="updateAnnouncementPreview()"></textarea>
              </div>
              
              <div class="form-group">
                <label for="ann_publish_date" style="font-weight:600;">Publish Date & Time</label>
                <input type="datetime-local" id="ann_publish_date" name="publish_date" required value="<?php echo date('Y-m-d\TH:i'); ?>" style="width:100%; padding:10px; border: 1px solid var(--border); border-radius: 8px; font-size:14px; box-sizing:border-box; background:white;" onchange="updateAnnouncementPreview()">
              </div>
              
              <div class="form-group">
                <label for="ann_status" style="font-weight:600;">Status</label>
                <select id="ann_status" name="status" style="width:100%; padding:10px; border: 1px solid var(--border); border-radius: 8px; font-size:14px; box-sizing:border-box; background:white; height:42px;" onchange="updateAnnouncementPreview()">
                  <option value="active">Active</option>
                  <option value="archived">Archived</option>
                </select>
              </div>
              
              <div style="display:flex; gap:10px; margin-top:25px;">
                <button type="submit" class="btn-primary">Save Announcement</button>
                <button type="button" class="btn-primary btn-secondary" onclick="hideAnnouncementForm()">Cancel</button>
              </div>
            </form>

            <!-- Right Column: Live Real-Time Card Preview -->
            <div style="background: white; border: 1px solid var(--border); border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(15,23,42,0.06); position: sticky; top: 20px;">
              <h4 style="margin: 0 0 16px 0; font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                <span style="display:inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--accent);"></span> Live Dashboard Preview
              </h4>
              
              <div id="previewCardContainer" style="padding: 16px; border-radius: 10px; background: #f8fafc; border-left: 4px solid #d97706; transition: all 0.2s;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 8px;">
                  <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                    <span id="previewCategoryBadge" style="font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 3px 8px; border-radius: 6px; background: #fef3c7; color: #d97706;">
                      System Maintenance
                    </span>
                    <span id="previewOriginBadge" style="font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 3px 8px; border-radius: 6px; background: #ffe4e6; color: #be123c;">
                      Clinic Announcement
                    </span>
                  </div>
                  <span id="previewDate" style="font-size: 12px; color: #64748b; font-weight: 500;">Today</span>
                </div>
                <strong id="previewTitle" style="color: var(--accent); font-size: 15px; display: block; margin-bottom: 6px; word-break: break-word;">Untitled Announcement</strong>
                <p id="previewContent" style="margin: 0; color: #475569; font-size: 13.5px; line-height: 1.5; white-space: pre-line; word-break: break-word;">Announcement details will appear here...</p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Announcements Table/List -->
        <?php
        $allAnnouncements = [];
        $stmt = $conn->prepare("SELECT * FROM announcements WHERE tenant_id = ? ORDER BY publish_date DESC, id DESC");
        if ($stmt) {
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $allAnnouncements[] = $row;
                }
            }
            $stmt->close();
        }
        ?>

        <?php if (empty($allAnnouncements)): ?>
          <div style="text-align:center; padding: 40px 20px; border: 1px dashed var(--border); border-radius: 8px;">
            <p style="color:#64748b; margin:0;">No announcements created yet. Click "+ Add Announcement" to publish one.</p>
          </div>
        <?php else: ?>
          <div style="overflow-x:auto;">
            <table class="ann-table">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Title & Description</th>
                  <th>Published</th>
                  <th>Status</th>
                  <th style="text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($allAnnouncements as $ann): ?>
                  <tr>
                    <td>
                      <span class="ann-badge <?php echo str_replace(' ', '-', strtolower($ann['category'])); ?>">
                        <?php echo h($ann['category']); ?>
                      </span>
                    </td>
                    <td>
                      <strong style="color:var(--accent); font-size:15px;"><?php echo h($ann['title']); ?></strong>
                      <p style="margin: 6px 0 0 0; color:#475569; font-size:13px; line-height:1.4; white-space:pre-line;"><?php echo h($ann['content']); ?></p>
                    </td>
                    <td>
                      <span style="color:#64748b; font-size:13px; font-weight:500;"><?php echo date('M d, Y g:i A', strtotime($ann['publish_date'])); ?></span>
                    </td>
                    <td>
                      <span class="status-badge <?php echo h($ann['status']); ?>">
                        <?php echo h(ucfirst($ann['status'])); ?>
                      </span>
                    </td>
                    <td style="text-align:right; white-space:nowrap;">
                      <button type="button" class="btn-ann-action" onclick="showEditAnnouncementForm(<?php echo h(json_encode($ann)); ?>)">Edit</button>
                      <button type="button" class="btn-ann-action btn-ann-danger" onclick="confirmDeleteAnnouncement(<?php echo (int)$ann['id']; ?>)">Delete</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="module-card">
        <h2 style="margin-bottom: 20px; color: var(--accent);">Change Password</h2>

        <?php if ($message): ?>
          <div class="message <?php echo strpos($message, 'success') !== false ? 'success' : 'error'; ?>">
            <?php echo h($message); ?>
          </div>
        <?php endif; ?>

        <?php
        // Fetch current username for pre-filling
        $stmt = $conn->prepare("SELECT username FROM tenants WHERE tenant_id = ?");
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $usernameResult = $stmt->get_result();
        $currentUsername = $usernameResult->fetch_assoc()['username'] ?? '';
        $stmt->close();
        ?>

        <form method="POST">
          <input type="hidden" name="change_account_settings" value="1">
          
          <div class="form-group">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" placeholder="Enter username to update">
            <div class="helper">Your unique login name within this clinic.</div>
          </div>

          <div style="margin: 20px 0; padding: 16px; background: #f1f5f9; border-radius: 8px; border-left: 4px solid var(--accent);">
            <p style="margin: 0; font-size: 13px; color: #475569;">To change your password, fill out the fields below. Leave them empty if you only want to change your username.</p>
          </div>

          <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password">
          </div>

          <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password">
          </div>

          <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password">
          </div>

          <button type="submit" class="btn-primary">Save Account Settings</button>
        </form>
      </div>

      <div class="module-card" style="margin-bottom: 32px;">
        <h2 style="margin-bottom: 10px; color: var(--accent);">Public Landing Page</h2>
        <p style="color: #64748b; margin-bottom: 20px; font-size: 14px;">Manage the clinic information displayed on your public landing page, including hero titles, contact details, and clinic description.</p>
        <a href="Landing Page/edit_tenant_homepage.php?tenant=<?php echo h($tenantSlug); ?>" class="btn-primary" style="display: inline-block;" target="_blank">Edit Landing Page Content</a>
      </div>

      <div class="login-customizer">
        <h3>Login Page Customization</h3>
        <p style="color: #64748b; margin-bottom: 20px;">Customize your clinic's login page appearance. Changes will be visible to all users logging into your clinic.</p>

        <?php
        // Load current login customization settings from tenant_configs
        $tenantSettings = array_merge([
            'primary_btn_color' => '#0d3b66',
            'link_color' => '#2563eb',
            'card_bg_color' => '#ffffff',
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
            <div class="form-group" style="grid-column: 1 / -1;">
              <label>Color Theme Presets</label>
              <div class="theme-preset-grid">
                <button type="button" class="theme-preset-btn" data-theme-btn="#0d3b66" data-theme-link="#2563eb" data-theme-card="#ffffff">
                  Classic OralSync
                  <div class="theme-preview-dots">
                    <span class="theme-dot" style="background:#001f3f;"></span>
                    <span class="theme-dot" style="background:#0d3b66;"></span>
                    <span class="theme-dot" style="background:#2563eb;"></span>
                  </div>
                </button>
                <button type="button" class="theme-preset-btn" data-theme-btn="#3b82f6" data-theme-link="#38bdf8" data-theme-card="#ffffff">
                  Midnight Blue
                  <div class="theme-preview-dots">
                    <span class="theme-dot" style="background:#0f172a;"></span>
                    <span class="theme-dot" style="background:#3b82f6;"></span>
                    <span class="theme-dot" style="background:#38bdf8;"></span>
                  </div>
                </button>
                <button type="button" class="theme-preset-btn" data-theme-btn="#22c55e" data-theme-link="#16a34a" data-theme-card="#ffffff">
                  Fresh Green
                  <div class="theme-preview-dots">
                    <span class="theme-dot" style="background:#14532d;"></span>
                    <span class="theme-dot" style="background:#22c55e;"></span>
                    <span class="theme-dot" style="background:#16a34a;"></span>
                  </div>
                </button>
                <button type="button" class="theme-preset-btn" data-theme-btn="#8b5cf6" data-theme-link="#a78bfa" data-theme-card="#ffffff">
                  Purple Care
                  <div class="theme-preview-dots">
                    <span class="theme-dot" style="background:#4c1d95;"></span>
                    <span class="theme-dot" style="background:#8b5cf6;"></span>
                    <span class="theme-dot" style="background:#a78bfa;"></span>
                  </div>
                </button>
              </div>
              <div class="hint-text">Pick a preset or fully customize every color below.</div>
            </div>

            <div class="form-group">
              <label for="card_bg_color">Login Card Background Color</label>
              <div class="color-swatch-wrap">
                <label for="card_bg_color" class="color-swatch">
                  <span class="swatch-box" id="swatch-card-bg-color" style="background: <?php echo h($tenantSettings['card_bg_color']); ?>;"></span>
                  <span class="swatch-label" id="label-card-bg-color"><?php echo h($tenantSettings['card_bg_color']); ?></span>
                </label>
                <input type="color" id="card_bg_color" name="card_bg_color" class="live-update color-input" data-target="preview-right-panel" data-style="backgroundColor" value="<?php echo h($tenantSettings['card_bg_color']); ?>">
              </div>
            </div>

            <div class="form-group">
              <label for="primary_btn_color">Sign In Button Color</label>
              <div class="color-swatch-wrap">
                <label for="primary_btn_color" class="color-swatch">
                  <span class="swatch-box" id="swatch-primary-btn-color" style="background: <?php echo h($tenantSettings['primary_btn_color']); ?>;"></span>
                  <span class="swatch-label" id="label-primary-btn-color"><?php echo h($tenantSettings['primary_btn_color']); ?></span>
                </label>
                <input type="color" id="primary_btn_color" name="primary_btn_color" class="live-update color-input" data-target="preview-signin-btn" data-style="backgroundColor" value="<?php echo h($tenantSettings['primary_btn_color']); ?>">
              </div>
            </div>

            <div class="form-group">
              <label for="link_color">Text Link Color</label>
              <div class="color-swatch-wrap">
                <label for="link_color" class="color-swatch">
                  <span class="swatch-box" id="swatch-link-color" style="background: <?php echo h($tenantSettings['link_color']); ?>;"></span>
                  <span class="swatch-label" id="label-link-color"><?php echo h($tenantSettings['link_color']); ?></span>
                </label>
                <input type="color" id="link_color" name="link_color" class="live-update color-input" data-target="preview-forgot-link" data-style="color" value="<?php echo h($tenantSettings['link_color']); ?>">
              </div>
            </div>
          </div>

          <div class="customizer-grid">
            <div class="form-group">
              <label for="brand_bg_image">Background Image Upload</label>
              <input type="file" id="brand_bg_image" name="brand_bg_image" accept=".jpg,.jpeg,.png" class="file-input" data-target="preview-left-panel" data-style="backgroundImage">
              <div class="hint-text">Any image dimension is allowed. Best quality: landscape images (e.g. 1600x900+).</div>
              <?php if (!empty($tenantSettings['brand_bg_image_path'])): ?>
                <div class="hint-text">Current image: <?php echo h($tenantSettings['brand_bg_image_path']); ?></div>
              <?php endif; ?>
            </div>
            <div class="form-group">
              <label for="brand_logo_image">Clinic Logo Upload</label>
              <input type="file" id="brand_logo_image" name="brand_logo_image" accept=".jpg,.jpeg,.png" class="file-input" data-target="preview-clinic-logo" data-property="logoPreview">
              <div class="hint-text">Any image dimension is allowed. Transparent PNG logos look best.</div>
              <?php if (!empty($tenantSettings['brand_logo_path'])): ?>
                <div class="hint-text">Current logo: <?php echo h($tenantSettings['brand_logo_path']); ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-primary">Save Changes</button>
            <button type="button" class="btn-primary btn-secondary" onclick="openResetModal()">Reset to Default</button>
          </div>
        </form>

        <!-- WYSIWYG Login Preview -->
        <div class="login-preview-container">
          <div class="preview-label">📱 Live Preview — How Your Login Page Will Look</div>
          <!-- Outer chrome simulating browser/full page -->
          <div class="preview-page-chrome <?php echo (!empty($tenantSettings['brand_bg_image_path']) || !empty($tenantSettings['brand_logo_path'])) ? 'has-custom-bg' : ''; ?>" id="preview-page-chrome" style="background-image: <?php echo !empty($tenantSettings['brand_bg_image_path']) ? "linear-gradient(rgba(15, 23, 42, 0.45), rgba(15, 23, 42, 0.45)), url('" . h($tenantSettings['brand_bg_image_path']) . "')" : 'none'; ?>; background-size: cover; background-position: center;">
            <!-- Top-left corner logo overlay (outside the card) -->
            <div class="preview-topleft-logo" id="preview-topleft-logo" style="display: <?php echo !empty($tenantSettings['brand_logo_path']) ? 'inline-flex' : 'none'; ?>; background: transparent; border: none; backdrop-filter: none;">
              <?php if (!empty($tenantSettings['brand_logo_path'])): ?>
                <img id="preview-logo-img" src="<?php echo h($tenantSettings['brand_logo_path']); ?>" alt="Clinic Logo" style="height:38px;width:auto;object-fit:contain;display:block;">
                <div class="preview-clinic-name" id="preview-top-name" style="color: #fff; margin-left: 10px;"><?php echo h($tenantName); ?></div>
              <?php else: ?>
                <span id="preview-logo-initials" style="font-weight:800;font-size:15px;color:#fff;letter-spacing:0.5px;">OS</span>
              <?php endif; ?>
            </div>

            <!-- Centered two-panel card (matches actual tenant_login.php layout) -->
            <div class="preview-card-wrap" id="preview-card-wrap">
              <div class="preview-split-layout">
                <!-- Left dark panel -->
                <div class="preview-left-panel" id="preview-left-panel" style="display: <?php echo (!empty($tenantSettings['brand_bg_image_path']) || !empty($tenantSettings['brand_logo_path'])) ? 'none' : 'flex'; ?>;">
                  <div class="preview-left-overlay"></div>
                  <div class="preview-left-content" id="preview-left-content">
                    <div class="preview-left-brand">
                      <div class="preview-left-logo-box" id="preview-clinic-logo">
                        <?php if (!empty($tenantSettings['brand_logo_path'])): ?>
                          <img src="<?php echo h($tenantSettings['brand_logo_path']); ?>" alt="Clinic Logo" style="max-height:38px;max-width:60px;object-fit:contain;">
                        <?php else: ?>
                          <span style="font-weight:800;font-size:13px;color:#fff;">OS</span>
                        <?php endif; ?>
                      </div>
                      <div>
                        <div class="preview-clinic-name"><?php echo h($tenantName); ?></div>
                        <div class="preview-subtitle">Powered by OralSync</div>
                      </div>
                    </div>
                    <div class="preview-left-body">
                      <p style="margin:0 0 10px;font-size:12px;opacity:0.9;line-height:1.5;">Sign in to manage appointments, patients, and clinic operations. Your clinic can customize this portal soon.</p>
                      <div class="preview-coming-soon-box">
                        <div style="font-size:10px;font-weight:700;margin-bottom:5px;opacity:0.8;">Customization spots (coming soon)</div>
                        <div style="font-size:10px;opacity:0.7;line-height:1.6;">- Clinic logo upload<br>- Accent color / theme</div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Right white panel (login form) -->
                <div class="preview-right-panel" id="preview-right-panel" style="background-color: <?php echo h($tenantSettings['card_bg_color']); ?>;">
                  <div class="preview-login-title">Clinic Login</div>
                  <div class="preview-description">Please sign in to access your clinic portal.</div>
                  <div class="preview-field-group">
                    <label class="preview-field-label">Email / Username</label>
                    <input type="text" class="preview-input" placeholder="" readonly>
                  </div>
                  <div class="preview-field-group">
                    <label class="preview-field-label">Password</label>
                    <input type="password" class="preview-input" placeholder="" readonly>
                  </div>
                  <button type="button" class="preview-signin-btn" id="preview-signin-btn" style="background-color: <?php echo h($tenantSettings['primary_btn_color']); ?>;">Sign in</button>
                  <a href="#" class="preview-forgot-link" id="preview-forgot-link" style="color: <?php echo h($tenantSettings['link_color']); ?>;">Forgot password?</a>
                  <div class="preview-no-account">Don't have an account? Contact your clinic for access.</div>
                </div>
              </div>
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
      if (!input || !input.id) return;
      const swatchId = input.id.replace(/_/g, '-');
      const label = document.getElementById(`label-${swatchId}`);
      const swatch = document.getElementById(`swatch-${swatchId}`);
      
      if (label) {
        label.textContent = input.value;
      }
      if (swatch) {
        swatch.style.backgroundColor = input.value;
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

    function applyThemePreset(theme) {
      const bgColorInput = document.querySelector('input[name="brand_bg_color"]');
      const textColorInput = document.querySelector('input[name="brand_text_color"]');
      const btnInput = document.getElementById('primary_btn_color');
      const linkInput = document.getElementById('link_color');
      const cardInput = document.getElementById('card_bg_color');

      if (bgColorInput) {
        bgColorInput.value = theme.bg;
        bgColorInput.dispatchEvent(new Event('input'));
      }
      if (textColorInput) {
        textColorInput.value = theme.text;
        textColorInput.dispatchEvent(new Event('input'));
      }
      if (btnInput) {
        btnInput.value = theme.btn;
        btnInput.dispatchEvent(new Event('input'));
      }
      if (linkInput) {
        linkInput.value = theme.link;
        linkInput.dispatchEvent(new Event('input'));
      }
      if (cardInput) {
        cardInput.value = theme.card || '#ffffff';
        cardInput.dispatchEvent(new Event('input'));
      }
    }

    document.querySelectorAll('.theme-preset-btn').forEach(button => {
      button.addEventListener('click', function () {
        document.querySelectorAll('.theme-preset-btn').forEach(btn => btn.classList.remove('is-active'));
        this.classList.add('is-active');
        applyThemePreset({
          btn: this.dataset.themeBtn,
          link: this.dataset.themeLink,
          card: this.dataset.themeCard
        });
      });
    });

    function syncPreviewLayoutBasedOnBackground(hasCustom) {
      const pageChrome = document.getElementById('preview-page-chrome');
      if (!pageChrome) return;
      pageChrome.classList.toggle('has-custom-bg', !!hasCustom);
      
      const leftPanel = document.getElementById('preview-left-panel');
      if (leftPanel) {
        leftPanel.style.display = hasCustom ? 'none' : 'flex';
      }

      const topLeftLogo = document.getElementById('preview-topleft-logo');
      if (topLeftLogo) {
        topLeftLogo.style.display = hasCustom ? 'inline-flex' : 'none';
      }
    }

    document.querySelectorAll('.live-update').forEach(input => {
      input.addEventListener('input', function(e) {
        const targetId = this.dataset.target;
        const target = document.getElementById(targetId);
        if (!target) return;

        const style = this.dataset.style;
        const value = this.value;

        if (style && style !== 'backgroundImage') {
          target.style[style] = value;
        }

        if (this.type === 'color') {
          updateSwatch(this);
        }
      });

      // Avoid focus shifting on color picker trigger
      if (input.type === 'color') {
        input.addEventListener('click', (e) => {
          // If the browser tries to focus the hidden input, prevent defaults that might cause scroll
          // However, we want the color picker to open, so we don't preventDefault() here.
        });
      }

      // Trigger initial state for visible color pickers only
      if (input.type === 'color' && document.getElementById(input.id)) {
        updateSwatch(input);
      }
    });



    document.querySelectorAll('.file-input').forEach(input => {
      input.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(event) {
          const dataUrl = event.target.result;

          if (input.id === 'brand_bg_image') {
            // Update background of full chrome
            const pageChrome = document.getElementById('preview-page-chrome');
            if (pageChrome) {
              pageChrome.style.backgroundImage = `linear-gradient(rgba(15, 23, 42, 0.45), rgba(15, 23, 42, 0.45)), url('${dataUrl}')`;
              pageChrome.style.backgroundSize = 'cover';
              pageChrome.style.backgroundPosition = 'center';
            }
            syncPreviewLayoutBasedOnBackground(true);
          } else if (input.id === 'brand_logo_image') {
            // Update logo inside the left panel card brand area
            const logoBox = document.getElementById('preview-clinic-logo');
            if (logoBox) {
              logoBox.innerHTML = `<img src="${dataUrl}" alt="Logo" style="max-height:38px;max-width:60px;object-fit:contain;image-rendering:high-quality;">`;
            }
            const topLeftLogoImage = document.getElementById('preview-logo-img');
            if (topLeftLogoImage) {
              topLeftLogoImage.src = dataUrl;
            } else {
              const topLeftLogo = document.getElementById('preview-topleft-logo');
              if (topLeftLogo) {
                const clinicName = document.querySelector('.preview-clinic-name')?.textContent || '';
                topLeftLogo.innerHTML = `
                  <img id="preview-logo-img" src="${dataUrl}" alt="Clinic Logo" style="height:38px;width:auto;object-fit:contain;display:block;">
                  <div class="preview-clinic-name" id="preview-top-name" style="color: #fff; margin-left: 10px;">${clinicName}</div>
                `;
              }
            }
            syncPreviewLayoutBasedOnBackground(true);
          }
        };
        reader.readAsDataURL(file);
      });
    });

    function resetLoginPreview() {
      const defaults = {
        primary_btn_color: '#0d3b66',
        link_color: '#2563eb',
      };

      // Reset color pickers and swatches
      Object.keys(defaults).forEach(key => {
        const input = document.getElementById(key);
        if (input) {
          input.value = defaults[key];
          input.dispatchEvent(new Event('input'));
          const swatchId = key.replace(/_/g, '-');
          const label = document.getElementById(`label-${swatchId}`);
          const swatch = document.getElementById(`swatch-${swatchId}`);
          if (label) label.textContent = defaults[key];
          if (swatch) swatch.style.backgroundColor = defaults[key];
        }
      });

      const cardBgInput = document.getElementById('card_bg_color');
      if (cardBgInput) {
        cardBgInput.value = '#ffffff';
        cardBgInput.dispatchEvent(new Event('input'));
      }

      // Reset file inputs
      const bgFileInput = document.getElementById('brand_bg_image');
      if (bgFileInput) bgFileInput.value = '';
      const logoFileInput = document.getElementById('brand_logo_image');
      if (logoFileInput) logoFileInput.value = '';

      // Reset preview background
      const pageChrome = document.getElementById('preview-page-chrome');
      if (pageChrome) {
        pageChrome.style.backgroundImage = 'none';
      }
      syncPreviewLayoutBasedOnBackground(false);

      // Reset logo inside the card brand area
      const logoBox = document.getElementById('preview-clinic-logo');
      if (logoBox) {
        logoBox.innerHTML = '<span style="font-weight:800;font-size:13px;color:#fff;">OS</span>';
      }
      const topLeftLogo = document.getElementById('preview-topleft-logo');
      if (topLeftLogo) {
        topLeftLogo.innerHTML = '<span id="preview-logo-initials" style="font-weight:800;font-size:15px;color:#fff;letter-spacing:0.5px;">OS</span>';
      }

      // Reset sign-in button and forgot link colors in preview
      const signinBtn = document.getElementById('preview-signin-btn');
      if (signinBtn) signinBtn.style.backgroundColor = '#0d3b66';
      const forgotLink = document.getElementById('preview-forgot-link');
      if (forgotLink) forgotLink.style.color = '#2563eb';
    }

    function openResetModal() {
      document.getElementById('resetConfirmModal').style.display = 'flex';
    }

    function closeResetModal() {
      document.getElementById('resetConfirmModal').style.display = 'none';
    }

    function confirmReset() {
      resetLoginPreview();
      closeResetModal();
      // Add a hidden field to tell the server this is a full reset
      const form = document.getElementById('loginSettingsForm');
      if (form) {
        let resetFlag = form.querySelector('input[name="reset_to_default"]');
        if (!resetFlag) {
          resetFlag = document.createElement('input');
          resetFlag.type = 'hidden';
          resetFlag.name = 'reset_to_default';
          form.appendChild(resetFlag);
        }
        resetFlag.value = '1';
        form.submit();
      }
    }


    function validateForm() {
      return true;
    }

    syncPreviewLayoutBasedOnBackground(<?php echo (!empty($tenantSettings['brand_bg_image_path']) || !empty($tenantSettings['brand_logo_path'])) ? 'true' : 'false'; ?>);
  </script>

  <!-- Reset Confirmation Modal -->
  <style>
    .reset-modal {
      display: none;
      position: fixed;
      z-index: 1100;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
      align-items: center;
      justify-content: center;
      animation: fadeIn 0.3s ease;
    }

    .reset-modal-content {
      background: white;
      margin: 0;
      padding: 0;
      border-radius: 16px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
      width: 90%;
      max-width: 450px;
      animation: modalSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
      overflow: hidden;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes modalSlideIn {
      from { transform: translateY(-20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .reset-modal-header {
      background: linear-gradient(135deg, #0d3b66, #0a2f52);
      color: white;
      padding: 20px;
      border-radius: 12px 12px 0 0;
      font-size: 18px;
      font-weight: 700;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }


    .reset-modal-body {
      padding: 24px;
      color: #374151;
      font-size: 14px;
      line-height: 1.6;
    }

    .reset-modal-footer {
      padding: 20px 24px;
      border-top: 1px solid #e5e7eb;
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      background: #f9fafb;
      border-radius: 0 0 12px 12px;
    }

    .reset-modal-footer button {
      padding: 10px 16px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      font-size: 13px;
      transition: all 0.2s ease;
    }

    .reset-modal-footer .btn-confirm {
      background: linear-gradient(135deg, #0d3b66, #0a2f52);
      color: white;
    }

    .reset-modal-footer .btn-confirm:hover {
      background: #0a2f52;
    }


    .reset-modal-footer .btn-cancel {
      background: #e5e7eb;
      color: #374151;
    }

    .reset-modal-footer .btn-cancel:hover {
      background: #d1d5db;
    }

    .reset-modal-close {
      color: white;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
      border: none;
      background: none;
      padding: 0;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .reset-modal-close:hover {
      opacity: 0.8;
    }
  </style>

  <div id="resetConfirmModal" class="reset-modal">
    <div class="reset-modal-content">
      <div class="reset-modal-header">
        <span>Reset to Default Settings</span>
        <button class="reset-modal-close" onclick="closeResetModal()">&times;</button>
      </div>
      <div class="reset-modal-body">
        <p>Are you sure you want to reset all login customization settings to their default values?</p>
        <p><strong>This action will:</strong></p>
        <ul style="margin: 12px 0; padding-left: 20px;">
          <li>Restore all colors to defaults</li>
          <li>Remove any custom background image</li>
          <li>Remove any custom clinic logo</li>
        </ul>
        <p>This change will be automatically saved.</p>
      </div>
      <div class="reset-modal-footer">
        <button class="btn-cancel" onclick="closeResetModal()">Cancel</button>
        <button class="btn-confirm" onclick="confirmReset()">Reset Settings</button>
      </div>
    </div>
  </div>

  <!-- Custom Modal for Clinic Announcement Deletion -->
  <div id="deleteConfirmModal" class="reset-modal" style="display:none;">
    <div class="reset-modal-content">
      <div class="reset-modal-header" style="background: linear-gradient(135deg, #be123c, #9f1239); color: white;">
        <span>Delete Clinic Announcement</span>
        <button class="reset-modal-close" onclick="closeDeleteModal()">&times;</button>
      </div>
      <div class="reset-modal-body">
        <p>Are you sure you want to delete this clinic announcement?</p>
        <p style="color: #be123c; font-weight: 600; margin-top: 8px;">This will remove it from your staff dashboards immediately and cannot be undone.</p>
      </div>
      <div class="reset-modal-footer">
        <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
        <button class="btn-confirm" id="btn-confirm-delete" style="background: linear-gradient(135deg, #be123c, #9f1239);" onclick="executeDeleteAnnouncement()">Delete</button>
      </div>
    </div>
  </div>

    window.onclick = function(event) {
      const resetModal = document.getElementById('resetConfirmModal');
      const deleteModal = document.getElementById('deleteConfirmModal');
      if (event.target == resetModal) {
        resetModal.style.display = 'none';
      }
      if (event.target == deleteModal) {
        deleteModal.style.display = 'none';
      }
    }

    // Live Real-Time Announcement Preview Function
    function updateAnnouncementPreview() {
      const titleVal = document.getElementById('ann_title').value.trim() || 'Untitled Announcement';
      const contentVal = document.getElementById('ann_content').value.trim() || 'Announcement details will appear here...';
      const categoryVal = document.getElementById('ann_category').value;
      const dateVal = document.getElementById('ann_publish_date').value;
      
      // Format date and time nicely: "M d, Y g:i AM/PM"
      let formattedDate = 'Today';
      if (dateVal) {
        const d = new Date(dateVal);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        let hours = d.getHours();
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // convert 0 to 12
        formattedDate = months[d.getMonth()] + ' ' + String(d.getDate()).padStart(2, '0') + ', ' + d.getFullYear() + ' ' + hours + ':' + minutes + ' ' + ampm;
      }
      
      document.getElementById('previewTitle').innerText = titleVal;
      document.getElementById('previewContent').innerText = contentVal;
      document.getElementById('previewDate').innerText = formattedDate;
      document.getElementById('previewCategoryBadge').innerText = categoryVal;
      
      // Category color coding matching dashboard exactly
      const cat = categoryVal.toLowerCase();
      const badge = document.getElementById('previewCategoryBadge');
      const container = document.getElementById('previewCardContainer');
      
      if (cat.includes('maintenance')) {
        badge.style.background = '#fef3c7'; // Amber
        badge.style.color = '#b45309';
        container.style.borderLeftColor = '#d97706';
      } else if (cat.includes('update')) {
        badge.style.background = '#e0f2fe'; // Sky Blue
        badge.style.color = '#0369a1';
        container.style.borderLeftColor = '#0369a1';
      } else if (cat.includes('alert')) {
        badge.style.background = '#fee2e2'; // Light Crimson Red
        badge.style.color = '#b91c1c';
        container.style.borderLeftColor = '#be123c';
      } else {
        badge.style.background = '#f3e8ff'; // Default Purple
        badge.style.color = '#7e22ce';
        container.style.borderLeftColor = '#7e22ce';
      }
    }

    // Announcement Management Functions
    function showAddAnnouncementForm() {
      document.getElementById('announcementFormSection').style.display = 'block';
      document.getElementById('formTitle').innerText = 'Add New Announcement';
      document.getElementById('announcementAction').value = 'add';
      document.getElementById('announcementId').value = '';
      document.getElementById('ann_title').value = '';
      document.getElementById('ann_category').value = 'System Maintenance';
      document.getElementById('ann_content').value = '';
      
      // Set default selection to the current date and time in local timezone
      const now = new Date();
      const offset = now.getTimezoneOffset() * 60000;
      const localISOTime = (new Date(now - offset)).toISOString().slice(0, 16);
      document.getElementById('ann_publish_date').value = localISOTime;
      
      document.getElementById('ann_status').value = 'active';
      
      // Trigger Live Preview Update
      updateAnnouncementPreview();
      
      document.getElementById('announcementFormSection').scrollIntoView({ behavior: 'smooth' });
    }

    function showEditAnnouncementForm(ann) {
      document.getElementById('announcementFormSection').style.display = 'block';
      document.getElementById('formTitle').innerText = 'Edit Announcement';
      document.getElementById('announcementAction').value = 'edit';
      document.getElementById('announcementId').value = ann.id;
      document.getElementById('ann_title').value = ann.title;
      document.getElementById('ann_category').value = ann.category;
      document.getElementById('ann_content').value = ann.content;
      
      // Format "YYYY-MM-DD HH:MM:SS" database string into "YYYY-MM-DDTHH:MM" format
      if (ann.publish_date) {
        const formattedDate = ann.publish_date.replace(' ', 'T').substring(0, 16);
        document.getElementById('ann_publish_date').value = formattedDate;
      }
      
      document.getElementById('ann_status').value = ann.status;
      
      // Trigger Live Preview Update
      updateAnnouncementPreview();
      
      document.getElementById('announcementFormSection').scrollIntoView({ behavior: 'smooth' });
    }

    function hideAnnouncementForm() {
      document.getElementById('announcementFormSection').style.display = 'none';
    }

    // Custom Modal Confirmation for Deletion
    let deleteAnnouncementId = null;

    function confirmDeleteAnnouncement(id) {
      deleteAnnouncementId = id;
      document.getElementById('deleteConfirmModal').style.display = 'flex';
    }

    function closeDeleteModal() {
      document.getElementById('deleteConfirmModal').style.display = 'none';
      deleteAnnouncementId = null;
    }

    // Execution deletion
    function executeDeleteAnnouncement() {
      if (deleteAnnouncementId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="announcement_action" value="delete">
          <input type="hidden" name="announcement_id" value="${deleteAnnouncementId}">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }
  </script>
</body>
</html>
<?php
}
