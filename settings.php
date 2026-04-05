<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/tenant_settings_functions.php';

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

        <form method="POST" id="loginSettingsForm" onsubmit="return validateForm()">
          <input type="hidden" name="save_login_settings" value="1">
          
          <div class="customizer-grid">
            <div class="form-group">
              <label for="login_brand_bg">Brand Card Background</label>
              <input type="color" id="login_brand_bg" name="login_brand_bg" class="live-update" data-target="preview-left-panel" data-style="backgroundColor" value="<?php echo h($tenantSettings['login_brand_bg'] ?? '#001f3f'); ?>">
              <div class="hint-text">Default: #001f3f (Navy Blue)</div>
            </div>

            <div class="form-group">
              <label for="login_button_color">Sign In Button Color</label>
              <input type="color" id="login_button_color" name="login_button_color" class="live-update" data-target="preview-signin-btn" data-style="backgroundColor" value="<?php echo h($tenantSettings['login_button_color'] ?? '#22c55e'); ?>">
              <div class="hint-text">Default: #22c55e (Green)</div>
            </div>

            <div class="form-group">
              <label for="login_text_link_color">Text Link Color</label>
              <input type="color" id="login_text_link_color" name="login_text_link_color" class="live-update" data-target="preview-forgot-link" data-style="color" value="<?php echo h($tenantSettings['login_text_link_color'] ?? '#2563eb'); ?>">
            </div>
          </div>

          <div class="customizer-grid">
            <div class="form-group">
              <label for="login_title">Login Page Title</label>
              <input type="text" id="login_title" name="login_title" class="live-update" data-target="preview-login-title" data-property="textContent" value="<?php echo h($tenantSettings['login_title'] ?? 'Clinic Login'); ?>" placeholder="Clinic Login" maxlength="255">
              <div class="hint-text">Displayed above the login form</div>
            </div>

            <div class="form-group">
              <label for="login_branding_subtitle">Brand Card Subtitle</label>
              <input type="text" id="login_branding_subtitle" name="login_branding_subtitle" class="live-update" data-target="preview-subtitle" data-property="textContent" value="<?php echo h($tenantSettings['login_branding_subtitle'] ?? 'Powered by OralSync'); ?>" placeholder="Powered by OralSync" maxlength="255">
              <div class="hint-text">Small text on the left branding card</div>
            </div>
          </div>

          <div class="form-group">
            <label for="custom_bg_image_url">Background Image URL (Optional)</label>
            <textarea id="custom_bg_image_url" name="custom_bg_image_url" class="live-update" data-target="preview-left-panel" data-style="backgroundImage" rows="3" placeholder="https://example.com/path/to/image.jpg"><?php echo h($tenantSettings['custom_bg_image_url'] ?? ''); ?></textarea>
            <div class="hint-text">Leave empty to use solid color. Full HTTPS URL required. Image will be applied to the left card with a dark overlay for text readability.</div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-primary">Save Login Settings</button>
            <button type="button" class="btn-primary btn-secondary" onclick="resetLoginPreview()">Reset to Defaults</button>
          </div>
        </form>

        <!-- WYSIWYG Login Preview -->
        <div class="login-preview-container">
          <div class="preview-label">📱 Live Preview - How Your Login Will Look</div>
          <div class="preview-split-layout">
            <div class="preview-left-panel" id="preview-left-panel" style="background-color: <?php echo h($tenantSettings['login_brand_bg'] ?? '#001f3f'); ?>; background-image: <?php echo $tenantSettings['custom_bg_image_url'] ? "url('" . h($tenantSettings['custom_bg_image_url']) . "')" : 'none'; ?>;">
              <div class="preview-left-content">
                <div class="preview-clinic-logo">🏥</div>
                <div class="preview-clinic-name"><?php echo h($tenantName); ?></div>
              </div>
              <div class="preview-subtitle" id="preview-subtitle"><?php echo h($tenantSettings['login_branding_subtitle'] ?? 'Powered by OralSync'); ?></div>
            </div>

            <div class="preview-right-panel">
              <div class="preview-login-title" id="preview-login-title"><?php echo h($tenantSettings['login_title'] ?? 'Clinic Login'); ?></div>
              <div class="preview-description">Enter your credentials to access your clinic account.</div>
              <button type="button" class="preview-signin-btn" id="preview-signin-btn" style="background-color: <?php echo h($tenantSettings['login_button_color'] ?? '#22c55e'); ?>;">Sign in</button>
              <a href="#" class="preview-forgot-link" id="preview-forgot-link" style="color: <?php echo h($tenantSettings['login_text_link_color'] ?? '#2563eb'); ?>;">Forgot password?</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Live update preview as user types/selects colors
    document.querySelectorAll('.live-update').forEach(input => {
      input.addEventListener('input', function() {
        const targetId = this.dataset.target;
        const target = document.getElementById(targetId);
        if (!target) return;

        const style = this.dataset.style;
        const property = this.dataset.property;
        const value = this.value;

        if (style === 'backgroundImage') {
          // Handle background image with overlay
          if (value.trim()) {
            target.style.backgroundImage = `url('${value}')`;
          } else {
            target.style.backgroundImage = 'none';
          }
        } else if (style) {
          target.style[style] = value;
        } else if (property) {
          target[property] = value;
        }
      });

      // Trigger initial update
      input.dispatchEvent(new Event('input'));
    });

    function resetLoginPreview() {
      if (!confirm('Reset all login settings to defaults?')) return;

      document.getElementById('login_brand_bg').value = '#001f3f';
      document.getElementById('login_branding_subtitle').value = 'Powered by OralSync';
      document.getElementById('login_title').value = 'Clinic Login';
      document.getElementById('login_button_color').value = '#22c55e';
      document.getElementById('login_text_link_color').value = '#2563eb';
      document.getElementById('custom_bg_image_url').value = '';

      // Trigger updates
      document.querySelectorAll('.live-update').forEach(input => {
        input.dispatchEvent(new Event('input'));
      });
    }

    function validateForm() {
      const bgImageUrl = document.getElementById('custom_bg_image_url').value.trim();
      if (bgImageUrl && !bgImageUrl.startsWith('http')) {
        alert('Background image URL must start with http:// or https://');
        return false;
      }
      return true;
    }
  </script>
</body>
</html>

