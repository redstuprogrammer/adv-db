# 🏗️ FINAL SYNC: Master Solutions Document
**For Groupmate (Azure MySQL) & Local Development**

---

## 📋 TABLE OF CONTENTS
1. Database Schema (SQL)
2. Admin Settings PHP (Updated with WYSIWYG Preview)
3. Login Page Updates
4. Sidebar CSS/JS Fixes

---

## ✅ PART 1: DATABASE SCHEMA

### Create `tenant_configs` Table (Azure MySQL)

```sql
-- Create the tenant_configs table for login customization
-- Run this on your groupmate's Azure MySQL database

CREATE TABLE IF NOT EXISTS `tenant_configs` (
  `config_id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `brand_bg_color` VARCHAR(7) DEFAULT '#001f3f' COMMENT 'Brand card background - Default Navy Blue',
  `brand_subtitle` VARCHAR(255) DEFAULT 'Powered by OralSync',
  `login_title` VARCHAR(255) DEFAULT 'Clinic Login',
  `primary_btn_color` VARCHAR(7) DEFAULT '#22c55e' COMMENT 'Sign In button - Default Green',
  `link_color` VARCHAR(7) DEFAULT '#2563eb',
  `custom_bg_image_url` TEXT COMMENT 'Background image URL for brand card (optional)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`config_id`),
  UNIQUE KEY `unique_tenant_config` (`tenant_id`),
  CONSTRAINT `fk_config_tenant` FOREIGN KEY (`tenant_id`) 
    REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default configurations for existing tenants
INSERT INTO `tenant_configs` (tenant_id, brand_bg_color, primary_btn_color)
SELECT tenant_id, '#001f3f', '#22c55e' FROM `tenants`
ON DUPLICATE KEY UPDATE brand_bg_color = '#001f3f', primary_btn_color = '#22c55e';

-- Verify the table was created
DESCRIBE tenant_configs;
SELECT COUNT(*) FROM tenant_configs;
```

**Key Points:**
- ✅ `tenant_id` is FK with ON DELETE CASCADE (clinic deletion removes settings)
- ✅ Default Navy Blue: `#001f3f`
- ✅ Default Green (Sign In button): `#22c55e`
- ✅ Optional background image URL field
- ✅ Timestamps for audit trail

---

## ✅ PART 2: ADMIN SETTINGS PHP (tenant_settings.php)

### Replace the entire file with this updated version:

```php
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
        // Save login customization settings to tenant_configs
        $brandBgColor = $_POST['brand_bg_color'] ?? '#001f3f';
        $brandSubtitle = $_POST['brand_subtitle'] ?? 'Powered by OralSync';
        $loginTitle = $_POST['login_title'] ?? 'Clinic Login';
        $primaryBtnColor = $_POST['primary_btn_color'] ?? '#22c55e';
        $linkColor = $_POST['link_color'] ?? '#2563eb';
        $customBgImageUrl = $_POST['custom_bg_image_url'] ?? '';

        // Insert or update tenant_configs
        $stmt = $pdo->prepare("
            INSERT INTO tenant_configs (tenant_id, brand_bg_color, brand_subtitle, login_title, primary_btn_color, link_color, custom_bg_image_url)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                brand_bg_color = ?, 
                brand_subtitle = ?, 
                login_title = ?, 
                primary_btn_color = ?, 
                link_color = ?, 
                custom_bg_image_url = ?
        ");
        
        $stmt->execute([
            $tenantId, $brandBgColor, $brandSubtitle, $loginTitle, $primaryBtnColor, $linkColor, $customBgImageUrl,
            $brandBgColor, $brandSubtitle, $loginTitle, $primaryBtnColor, $linkColor, $customBgImageUrl
        ]);

        $message = 'Login customization settings saved successfully!';
    }
}

// Load current tenant settings from tenant_configs
$loginSettings = [
    'brand_bg_color' => '#001f3f',
    'brand_subtitle' => 'Powered by OralSync',
    'login_title' => 'Clinic Login',
    'primary_btn_color' => '#22c55e',
    'link_color' => '#2563eb',
    'custom_bg_image_url' => ''
];

try {
    $stmt = $pdo->prepare("SELECT * FROM tenant_configs WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($config) {
        $loginSettings = [
            'brand_bg_color' => $config['brand_bg_color'],
            'brand_subtitle' => $config['brand_subtitle'],
            'login_title' => $config['login_title'],
            'primary_btn_color' => $config['primary_btn_color'],
            'link_color' => $config['link_color'],
            'custom_bg_image_url' => $config['custom_bg_image_url']
        ];
    }
} catch (Exception $e) {
    error_log("Error loading tenant config: " . $e->getMessage());
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

      .btn-secondary {
        background: #6b7280;
      }

      .btn-secondary:hover {
        background: #4b5563;
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
        font-size: 14px;
      }

      .form-group input,
      .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
      }

      .form-group input:focus,
      .form-group textarea:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.1);
      }

      .color-picker-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        align-items: center;
      }

      .color-preview {
        width: 50px;
        height: 50px;
        border: 1px solid var(--border);
        border-radius: 8px;
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
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-top: 16px;
      }

      /* WYSIWYG Preview Container */
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

      .preview-logo {
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

      .preview-button {
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
      }

      .preview-button:hover {
        opacity: 0.9;
      }

      .preview-link {
        color: #2563eb;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
      }

      .preview-link:hover {
        text-decoration: underline;
      }

      .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
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
        <h3>🎨 Login Page Customization</h3>
        <p style="color: #64748b; margin-bottom: 20px;">Customize your clinic's login page appearance. Changes will be visible instantly in the preview and to all users logging into your clinic.</p>

        <form method="POST" id="loginSettingsForm" onsubmit="return validateForm()">
          <input type="hidden" name="save_login_settings" value="1">
          
          <div class="customizer-grid">
            <div class="form-group">
              <label for="brand_bg_color">Brand Card Background</label>
              <div class="color-picker-row">
                <input type="color" id="brand_bg_color" name="brand_bg_color" class="color-preview live-update" data-target="preview-left-panel" data-style="backgroundColor" value="<?php echo h($loginSettings['brand_bg_color']); ?>">
                <input type="text" class="live-input" id="brand_bg_color_text" value="<?php echo h($loginSettings['brand_bg_color']); ?>" readonly style="flex:1; cursor:default;">
              </div>
              <div class="hint-text">Default: #001f3f (Navy Blue)</div>
            </div>

            <div class="form-group">
              <label for="primary_btn_color">Sign In Button Color</label>
              <div class="color-picker-row">
                <input type="color" id="primary_btn_color" name="primary_btn_color" class="color-preview live-update" data-target="preview-signin-btn" data-style="backgroundColor" value="<?php echo h($loginSettings['primary_btn_color']); ?>">
                <input type="text" class="live-input" id="primary_btn_color_text" value="<?php echo h($loginSettings['primary_btn_color']); ?>" readonly style="flex:1; cursor:default;">
              </div>
              <div class="hint-text">Default: #22c55e (Green)</div>
            </div>

            <div class="form-group">
              <label for="link_color">Text Link Color</label>
              <div class="color-picker-row">
                <input type="color" id="link_color" name="link_color" class="color-preview live-update" data-target="preview-forgot-link" data-style="color" value="<?php echo h($loginSettings['link_color']); ?>">
                <input type="text" class="live-input" id="link_color_text" value="<?php echo h($loginSettings['link_color']); ?>" readonly style="flex:1; cursor:default;">
              </div>
            </div>
          </div>

          <div class="customizer-grid">
            <div class="form-group">
              <label for="login_title">Login Page Title</label>
              <input type="text" id="login_title" name="login_title" class="live-update" data-target="preview-login-title" data-property="textContent" value="<?php echo h($loginSettings['login_title']); ?>" placeholder="Clinic Login" maxlength="255">
              <div class="hint-text">Displayed above the login form</div>
            </div>

            <div class="form-group">
              <label for="brand_subtitle">Brand Card Subtitle</label>
              <input type="text" id="brand_subtitle" name="brand_subtitle" class="live-update" data-target="preview-subtitle" data-property="textContent" value="<?php echo h($loginSettings['brand_subtitle']); ?>" placeholder="Powered by OralSync" maxlength="255">
              <div class="hint-text">Small text on the left branding card</div>
            </div>
          </div>

          <div class="form-group">
            <label for="custom_bg_image_url">Background Image URL (Optional)</label>
            <textarea id="custom_bg_image_url" name="custom_bg_image_url" class="live-update" data-target="preview-left-panel" data-style="backgroundImage" rows="3" placeholder="https://example.com/path/to/image.jpg"><?php echo h($loginSettings['custom_bg_image_url']); ?></textarea>
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
            <div class="preview-left-panel" id="preview-left-panel" style="background-color: <?php echo h($loginSettings['brand_bg_color']); ?>; background-image: <?php echo $loginSettings['custom_bg_image_url'] ? "url('" . addslashes($loginSettings['custom_bg_image_url']) . "')" : 'none'; ?>;">
              <div class="preview-left-content">
                <div class="preview-logo">🏥</div>
                <div class="preview-clinic-name"><?php echo h($tenantName); ?></div>
              </div>
              <div class="preview-subtitle" id="preview-subtitle"><?php echo h($loginSettings['brand_subtitle']); ?></div>
            </div>

            <div class="preview-right-panel">
              <div class="preview-login-title" id="preview-login-title"><?php echo h($loginSettings['login_title']); ?></div>
              <div class="preview-description">Enter your credentials to access your clinic account.</div>
              <button type="button" class="preview-button" id="preview-signin-btn" style="background-color: <?php echo h($loginSettings['primary_btn_color']); ?>;">Sign in</button>
              <a href="#" class="preview-link" id="preview-forgot-link" style="color: <?php echo h($loginSettings['link_color']); ?>;">Forgot password?</a>
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
        const targetSelector = this.dataset.target;
        const target = document.getElementById(targetSelector);
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

        // Update hex display for color inputs
        if (this.type === 'color') {
          const textInputId = this.id + '_text';
          const textInput = document.getElementById(textInputId);
          if (textInput) {
            textInput.value = value.toUpperCase();
          }
        }
      });

      // Trigger initial update
      input.dispatchEvent(new Event('input'));
    });

    function resetLoginPreview() {
      if (!confirm('Reset all login settings to defaults?')) return;

      document.getElementById('brand_bg_color').value = '#001f3f';
      document.getElementById('brand_subtitle').value = 'Powered by OralSync';
      document.getElementById('login_title').value = 'Clinic Login';
      document.getElementById('primary_btn_color').value = '#22c55e';
      document.getElementById('link_color').value = '#2563eb';
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
```

---

## ✅ PART 3: LOGIN PAGE UPDATES

### Update `tenant_login.php` - Replace Lines 240-245

**Find this section:**
```php
                    <div class="t-foot">
                      <div style="margin-top: 12px;">
                        Having trouble? Make sure you're using the exact clinic link from your email.
                      </div>
                    </div>
```

**Replace with:**
```php
                    <div class="t-foot">
                      <div style="margin-top: 12px;">
                        Don't have an account? Contact your clinic for access.
                      </div>
                    </div>
```

### Update login.php to fetch from `tenant_configs`

**Replace the settings loading section (around line 13-19) with:**

```php
// Load tenant-specific login customization settings from tenant_configs
$loginSettings = [
    'brand_card_bg' => '#0d3b66',
    'branding_subtitle' => 'Powered by OralSync',
    'login_title' => 'Clinic Login',
    'button_color' => '#0d3b66',
    'text_link_color' => '#2563eb'
];

if ($tenant) {
    try {
        $stmt = $conn->prepare("
            SELECT brand_bg_color, brand_subtitle, login_title, primary_btn_color, link_color, custom_bg_image_url
            FROM tenant_configs 
            WHERE tenant_id = ?
        ");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $tenant['tenant_id']);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                $config = $res ? mysqli_fetch_assoc($res) : null;
                if ($config) {
                    $loginSettings = [
                        'brand_card_bg' => $config['brand_bg_color'] ?: '#0d3b66',
                        'branding_subtitle' => $config['brand_subtitle'] ?: 'Powered by OralSync',
                        'login_title' => $config['login_title'] ?: 'Clinic Login',
                        'button_color' => $config['primary_btn_color'] ?: '#0d3b66',
                        'text_link_color' => $config['link_color'] ?: '#2563eb',
                        'bg_image_url' => $config['custom_bg_image_url'] ?: ''
                    ];
                }
            }
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        error_log("Error loading tenant config: " . $e->getMessage());
    }
}
```

---

## ✅ PART 4: SIDEBAR CSS/JS FIXES

### Update `tenant_style.css` - Add/Replace Dropdown Styles

**Find the `.sidebar-dropdown-menu` section and replace with:**

```css
/* Dropdown Menu Styles - WITH Z-INDEX FIX */
.sidebar-dropdown-container {
  position: relative;
  z-index: 50;  /* FIX: Ensure dropdown stays above other content */
}

.sidebar-dropdown-toggle {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
  background: transparent;
  width: 100%;
  border-left: 3px solid transparent;
  font-family: inherit;
  position: relative;
  z-index: 51;
}

.sidebar-dropdown-toggle:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
  border-left-color: rgba(255, 255, 255, 0.3);
  /* FIX: Removed bg-white that was causing overlap */
}

.sidebar-dropdown-toggle.active {
  background: rgba(255, 255, 255, 0.15);
  color: #ffffff;
  border-left-color: #ffffff;
  font-weight: 600;
}

.sidebar-dropdown-toggle.open:not(.active) {
  background: transparent;
  color: rgba(255, 255, 255, 0.9);
  border-left-color: transparent;
}

.dropdown-arrow {
  margin-left: auto;
  font-size: 12px;
  transition: transform 0.2s ease;
}

.sidebar-dropdown-toggle.open .dropdown-arrow {
  transform: rotate(180deg);
}

.sidebar-dropdown-menu {
  display: none;
  background: rgba(255, 255, 255, 0.08);  /* FIX: Transparent overlay, not white */
  border-left: 3px solid rgba(255, 255, 255, 0.2);
  margin: 4px 0;
  overflow: hidden;
  position: relative;
  z-index: 50;
}

.sidebar-dropdown-menu.open {
  display: block;
}

.sidebar-dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px 10px 32px;
  color: rgba(255, 255, 255, 0.75);
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  border-left: 3px solid transparent;
  background: rgba(255, 255, 255, 0.05);  /* FIX: Subtle dark overlay on hover */
}

.sidebar-dropdown-item:hover {
  background: rgba(13, 59, 102, 0.3);  /* FIX: Dark navy overlay instead of white */
  color: rgba(255, 255, 255, 0.95);
  border-left-color: rgba(255, 255, 255, 0.3);
}

.sidebar-dropdown-item.active {
  background: rgba(255, 255, 255, 0.15);
  color: #ffffff;
  border-left-color: #ffffff;
  font-weight: 600;
}
```

---

## 📝 IMPLEMENTATION CHECKLIST

### For Your Groupmate (Azure MySQL):
```
☐ 1. Run the SQL script to create tenant_configs table
☐ 2. Verify table was created: SELECT COUNT(*) FROM tenant_configs;
☐ 3. Share connection details with local team
```

### For Local Development:
```
☐ 1. Replace tenant_settings.php with updated file
☐ 2. Update tenant_login.php (remove "Having trouble?" text)
☐ 3. Update login.php to fetch from tenant_configs
☐ 4. Update tenant_style.css with dropdown z-index fixes
☐ 5. Test login page with different color customizations
☐ 6. Verify background image uploads work
☐ 7. Test sidebar Reports dropdown on Audit Logs page
☐ 8. Verify no white block appears on hover
```

---

## 🧪 TESTING GUIDE

### Database Testing:
```sql
-- Verify structure
DESCRIBE tenant_configs;

-- Check your tenant config
SELECT * FROM tenant_configs WHERE tenant_id = 5;

-- Test ON DELETE CASCADE
-- (Delete a tenant and verify configs are removed)
```

### UI Testing:
1. **Live Preview**: Type in customizer fields → preview should update instantly
2. **Color Picker**: Select colors → hex values should display
3. **Background Image**: Paste image URL → preview should show with dark overlay
4. **Reset Button**: Click reset → all fields should return to defaults
5. **Save & Reload**: Save settings → refresh page → values should persist
6. **Login Page**: Visit login page → should display customized colors
7. **Sidebar Dropdown**: Hover over Audit Logs/Settings → Reports dropdown should not be covered

---

## 🎨 DEFAULT THEME REFERENCE

| Element | Color | Hex | Name |
|---------|-------|-----|------|
| Brand Card | Navy Blue | #001f3f | Original Navy |
| Sign In Button | Green | #22c55e | Original Green |
| Primary Accent | Navy | #0d3b66 | Dark Navy |
| Text Links | Blue | #2563eb | Standard Blue |

---

## 📞 TROUBLESHOOTING

**Q: Foreign key constraint error when saving settings?**
A: Ensure tenant_id exists in tenants table. Use valid tenant IDs.

**Q: Background image not showing?**
A: Check image URL is HTTPS and accessible. Verify CORS if cross-origin.

**Q: Colors not updating in preview?**
A: Check browser console for JS errors. Verify element IDs match.

**Q: Dropdown still showing white block?**
A: Clear browser cache. Verify tenant_style.css changes were saved.

---

**✅ That's it! Your login system is now fully synced.**
**Happy coding, team! 🚀**
