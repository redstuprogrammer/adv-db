<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/security_headers.php';
require_once 'connect.php';
require_once 'tenant_utils.php';
require_once 'tenant_settings_functions.php';

// Role Check Implementation - Ensure user is logged in
if (!isset($_SESSION['role'])) {
    header("Location: tenant_login.php");
    exit();
}

// Role Check Implementation - Ensure user is an Admin
if ($_SESSION['role'] !== 'Admin') {
    header("Location: tenant_login.php");
    exit();
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();

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
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM tenants WHERE tenant_id = ?");
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            $tenant = $result->fetch_assoc();

            if ($tenant && password_verify($currentPassword, $tenant['password'])) {
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $updateStmt = $conn->prepare("UPDATE tenants SET password = ? WHERE tenant_id = ?");
                $updateStmt->bind_param('si', $hashedPassword, $tenantId);
                if ($updateStmt->execute()) {
                    $message = 'Password changed successfully!';
                } else {
                    $message = 'Error updating password.';
                }
                $updateStmt->close();
            } else {
                $message = 'Current password is incorrect.';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['save_login_settings'])) {
        // Save login customization settings
        $loginBrandBg = $_POST['login_brand_bg'] ?? '#0d3b66';
        $loginBrandingSubtitle = $_POST['login_branding_subtitle'] ?? 'Powered by OralSync';
        $loginTitle = $_POST['login_title'] ?? 'Clinic Login';
        $loginButtonColor = $_POST['login_button_color'] ?? '#0d3b66';
        $loginTextLinkColor = $_POST['login_text_link_color'] ?? '#2563eb';

        setTenantSetting($tenantId, 'login_brand_bg', $loginBrandBg);
        setTenantSetting($tenantId, 'login_branding_subtitle', $loginBrandingSubtitle);
        setTenantSetting($tenantId, 'login_title', $loginTitle);
        setTenantSetting($tenantId, 'login_button_color', $loginButtonColor);
        setTenantSetting($tenantId, 'login_text_link_color', $loginTextLinkColor);

        $message = 'Login customization settings saved successfully!';
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

      .preview-logo {
        width: 80px;
        height: 80px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #e2e8f0;
        margin-bottom: 14px;
        overflow: hidden;
      }

      .preview-logo img {
        max-width: 100%;
        max-height: 100%;
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
    </style>
</head>
<body>
  <div class="tenant-layout">
    <!-- Sidebar Navigation -->
    <nav class="tenant-sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo">
          <div class="sidebar-logo-icon">🏥</div>
          <div>
            <div class="sidebar-logo-text">OralSync</div>
            <div class="sidebar-clinic-name"><?php echo h($tenantName); ?></div>
          </div>
        </div>
      </div>

      <div class="sidebar-nav">
        <div class="sidebar-section">
          <div class="sidebar-section-title">Main</div>
          <a href="tenant_dashboard.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">📊</span>
            <span>Dashboard</span>
          </a>
        </div>

        <div class="sidebar-section">
          <div class="sidebar-section-title">Core Features</div>
          <a href="patients.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">👥</span>
            <span>Patients</span>
          </a>
          <a href="appointments.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">📅</span>
            <span>Appointments</span>
          </a>
          <a href="billing.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">💳</span>
            <span>Billing</span>
          </a>
        </div>

        <div class="sidebar-section">
          <div class="sidebar-section-title">Management</div>
          <a href="manage_users.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">👤</span>
            <span>Users</span>
          </a>
          <a href="services.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">🦷</span>
            <span>Services</span>
          </a>
          <a href="tenant_reports.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">📈</span>
            <span>Reports</span>
          </a>
          <a href="tenant_settings.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item active">
            <span class="sidebar-nav-icon">⚙️</span>
            <span>Settings</span>
          </a>
        </div>
      </div>

      <div class="sidebar-footer">
        <a href="tenant_logout.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-logout-btn">
          <span>🚪</span>
          <span>Sign Out</span>
        </a>
      </div>
    </nav>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">⚙️ Settings</div>
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
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
        // Load current tenant settings
        $tenantSettings = getAllTenantSettings($tenantId);
        ?>

        <form method="POST" id="loginSettingsForm">
          <input type="hidden" name="save_login_settings" value="1">
          <div class="customizer-grid">
            <div class="form-group">
              <label for="login_brand_bg">Brand Card Background Color</label>
              <input type="color" id="login_brand_bg" name="login_brand_bg" class="preview-input" data-target="#preview-brand-card" data-style="backgroundColor" value="<?php echo htmlspecialchars($tenantSettings['login_brand_bg'] ?? '#0d3b66'); ?>">
            </div>
            <div class="form-group">
              <label for="login_branding_subtitle">Branding Subtitle</label>
              <input type="text" id="login_branding_subtitle" name="login_branding_subtitle" class="preview-input" data-target="#preview-brand-subtitle" data-property="textContent" value="<?php echo htmlspecialchars($tenantSettings['login_branding_subtitle'] ?? 'Powered by OralSync'); ?>" placeholder="Powered by OralSync">
            </div>
            <div class="form-group">
              <label for="login_title">Login Title</label>
              <input type="text" id="login_title" name="login_title" class="preview-input" data-target="#preview-login-title" data-property="textContent" value="<?php echo htmlspecialchars($tenantSettings['login_title'] ?? 'Clinic Login'); ?>" placeholder="Clinic Login">
            </div>
            <div class="form-group">
              <label for="login_button_color">Primary Button Color</label>
              <input type="color" id="login_button_color" name="login_button_color" class="preview-input" data-target="#preview-signin-btn" data-style="backgroundColor" value="<?php echo htmlspecialchars($tenantSettings['login_button_color'] ?? '#0d3b66'); ?>">
            </div>
            <div class="form-group">
              <label for="login_text_link_color">Text Link Color</label>
              <input type="color" id="login_text_link_color" name="login_text_link_color" class="preview-input" data-target="#preview-forgot-link" data-style="color" value="<?php echo htmlspecialchars($tenantSettings['login_text_link_color'] ?? '#2563eb'); ?>">
            </div>
          </div>

          <div class="sa-form-actions" style="margin-top: 20px;">
            <button type="submit" class="btn-primary">Save Login Settings</button>
            <button type="button" class="btn-primary" style="background: #6b7280;" onclick="resetLoginPreview()">Reset to Defaults</button>
          </div>
        </form>

        <div class="login-preview" id="login-preview-container">
          <div class="preview-title">Live Login Preview</div>
          <div class="login-preview-frame">
            <div class="login-preview-split">
              <div class="preview-left" id="preview-brand-card">
                <div class="preview-logo-spot"><?php echo h($tenantName); ?></div>
                <div class="preview-subtitle" id="preview-brand-subtitle"><?php echo htmlspecialchars($tenantSettings['login_branding_subtitle'] ?? 'Powered by OralSync'); ?></div>
              </div>
              <div class="preview-right">
                <div class="preview-welcome" id="preview-login-title"><?php echo htmlspecialchars($tenantSettings['login_title'] ?? 'Clinic Login'); ?></div>
                <div class="preview-description">Use the login button and forgot password link to preview styling instantly.</div>
                <button type="button" class="preview-button" id="preview-signin-btn">Sign in</button>
                <div class="preview-link-row"><a href="#" id="preview-forgot-link">Forgot password?</a></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function applyPreviewChange(input) {
      const targetSelector = input.dataset.target;
      const target = document.querySelector(targetSelector);
      if (!target) return;

      const style = input.dataset.style;
      const property = input.dataset.property;
      const value = input.value;

      if (style) {
        target.style[style] = value;
      } else if (property) {
        target[property] = value || target.dataset.default || '';
      }
    }

    function initializeLoginPreview() {
      const previewInputs = document.querySelectorAll('.preview-input');
      previewInputs.forEach(input => {
        input.addEventListener('input', () => applyPreviewChange(input));
        input.addEventListener('change', () => applyPreviewChange(input));
        applyPreviewChange(input);
      });
    }

    function resetLoginPreview() {
      document.getElementById('login_brand_bg').value = '#0d3b66';
      document.getElementById('login_branding_subtitle').value = 'Powered by OralSync';
      document.getElementById('login_title').value = 'Clinic Login';
      document.getElementById('login_button_color').value = '#0d3b66';
      document.getElementById('login_text_link_color').value = '#2563eb';

      const previewInputs = document.querySelectorAll('.preview-input');
      previewInputs.forEach(input => applyPreviewChange(input));
    }

    initializeLoginPreview();
  </script>
</body>
</html>