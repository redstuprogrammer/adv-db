<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/security_headers.php';
require_once 'connect.php';
require_once 'tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            <span>Staff & Users</span>
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
        <h3>Login Customizer (Tenant Admin)</h3>
        <div class="customizer-grid">
          <div class="form-group">
            <label for="custom_logo">Clinic Logo</label>
            <input type="file" id="custom_logo" accept="image/png, image/jpeg">
          </div>
          <div class="form-group">
            <label for="custom_accent">Accent Color</label>
            <input type="color" id="custom_accent" value="#0d3b66">
          </div>
          <div class="form-group">
            <label for="custom_welcome">Welcome Message</label>
            <input type="text" id="custom_welcome" placeholder="Welcome to your clinic portal...">
          </div>
          <div class="form-group">
            <label for="custom_support">Support Details</label>
            <input type="text" id="custom_support" placeholder="support@clinic.com | (123) 456 7890">
          </div>
        </div>

        <div class="sa-form-actions" style="margin-top: 20px;">
          <button type="button" class="btn-primary" id="custom_apply">Apply Preview</button>
          <button type="button" class="btn-primary" id="custom_reset">Reset Preview</button>
        </div>

        <div class="login-preview" id="custom_preview">
          <div class="preview-logo" id="preview_logo"><span style="font-size: 32px;">🏥</span></div>
          <div style="font-weight: 700; margin-bottom: 10px;" id="preview_welcome">Welcome to Your Clinic</div>
          <a href="#" class="preview-button" id="preview_button">Login</a>
          <div style="margin-top: 12px; color: var(--border);" id="preview_support">Contact support team at support@oral-sync.com</div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function updateCustomizerPreview() {
      const accent = document.getElementById('custom_accent').value;
      document.getElementById('preview_button').style.backgroundColor = accent;
      document.getElementById('preview_button').style.borderColor = accent;
      document.getElementById('preview_welcome').textContent = document.getElementById('custom_welcome').value || 'Welcome to Your Clinic';
      document.getElementById('preview_support').textContent = document.getElementById('custom_support').value || 'Contact support team at support@oral-sync.com';
    }

    document.getElementById('custom_accent').addEventListener('input', updateCustomizerPreview);
    document.getElementById('custom_welcome').addEventListener('input', updateCustomizerPreview);
    document.getElementById('custom_support').addEventListener('input', updateCustomizerPreview);

    document.getElementById('custom_logo').addEventListener('change', function(e) {
      const file = e.target.files[0];
      const previewLogo = document.getElementById('preview_logo');
      if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
          previewLogo.innerHTML = '<img src="' + event.target.result + '" alt="Logo">';
        };
        reader.readAsDataURL(file);
      } else {
        previewLogo.innerHTML = '<span style="font-size: 32px;">🏥</span>';
      }
    });

    document.getElementById('custom_apply').addEventListener('click', function() {
      updateCustomizerPreview();
      alert('Preview applied. This is a tenant-side login preview only.');
    });

    document.getElementById('custom_reset').addEventListener('click', function() {
      document.getElementById('custom_logo').value = '';
      document.getElementById('custom_accent').value = '#0d3b66';
      document.getElementById('custom_welcome').value = '';
      document.getElementById('custom_support').value = '';
      document.getElementById('preview_logo').innerHTML = '<span style="font-size: 32px;">🏥</span>';
      updateCustomizerPreview();
    });
  </script>
</body>
</html>