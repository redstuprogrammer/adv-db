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

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $rawPassword = isset($_POST['password']) ? trim($_POST['password']) : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : '';

    if ($username !== '' && $email !== '' && $rawPassword !== '' && $role !== '') {
        $password = password_hash($rawPassword, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('INSERT INTO users (tenant_id, username, email, password, role) VALUES (?, ?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('issss', $tenantId, $username, $email, $password, $role);
            if ($stmt->execute()) {
                header('Location: manage_users.php?tenant=' . urlencode($tenantSlug) . '&success=1');
                exit;
            } else {
                error_log("Error adding user: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}

// Fetch users for display
$users = [];
try {
    $stmt = $conn->prepare('SELECT user_id, username, email, role FROM users WHERE tenant_id = ? ORDER BY username');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Staff & Users</title>
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

      .module-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
      }

      .module-table th {
        background: var(--bg);
        border-bottom: 2px solid var(--border);
        padding: 12px;
        text-align: left;
        font-weight: 700;
        color: var(--accent);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .module-table td {
        padding: 12px;
        border-bottom: 1px solid var(--border);
      }

      .module-table tbody tr:hover {
        background: var(--bg);
      }

      .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
      }

      .badge-admin { background: rgba(13, 59, 102, 0.15); color: #0d3b66; }
      .badge-receptionist { background: rgba(245, 158, 11, 0.15); color: #d97706; }
      .badge-dentist { background: rgba(88, 28, 135, 0.15); color: #581c87; }

      .action-btn {
        display: inline-block;
        padding: 8px 12px;
        margin-right: 4px;
        background: var(--accent);
        border: 1px solid var(--accent);
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-size: 12px;
        color: white;
        font-weight: 600;
        transition: all 0.2s ease;
      }

      .action-btn:hover {
        background: #0a2d4f;
        border-color: #0a2d4f;
      }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <!-- Sidebar Navigation -->
    <nav class="tenant-sidebar">
      <div class="sidebar-header">
        <div class="logo-white-box">
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="main-logo">
            <rect width="32" height="32" rx="8" fill="#0d3b66"/>
            <text x="16" y="22" font-size="20" font-weight="bold" fill="white" text-anchor="middle">O</text>
          </svg>
        </div>
        <div>
          <div class="sidebar-logo-text">OralSync</div>
          <div class="sidebar-clinic-name"><?php echo h($tenantName); ?></div>
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
          <a href="manage_users.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item active">
            <span class="sidebar-nav-icon">👤</span>
            <span>Staff & Users</span>
          </a>
          <a href="tenant_reports.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">📈</span>
            <span>Reports</span>
          </a>
          <a href="tenant_settings.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
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
        <div class="tenant-header-title">👤 Staff & Users</div>
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
      </div>

      <div class="module-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Staff Members</h2>
          <a href="#" class="btn-primary" onclick="openAddUserModal()">+ Add User</a>
        </div>
        
        <table class="module-table">
          <thead>
            <tr>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr>
                <td colspan="4" style="text-align: center; color: rgb(100, 116, 139);">No users found. Click "Add User" to create one.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $user): 
                $role = $user['role'];
                $badgeClass = 'badge-admin';
                if ($role === 'Receptionist') $badgeClass = 'badge-receptionist';
                elseif ($role === 'Dentist') $badgeClass = 'badge-dentist';
              ?>
              <tr>
                <td><?php echo h($user['username']); ?></td>
                <td><?php echo h($user['email']); ?></td>
                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo h($role); ?></span></td>
                <td>
                  <button class="action-btn" onclick="toggleUserState(this)">Deactivate</button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <script>
    function toggleUserState(button) {
      if (!button) return;
      const isActive = button.textContent.trim().toLowerCase() === 'active';
      if (isActive) {
        button.textContent = 'Deactivate';
        button.style.background = '#0a2d4f';
      } else {
        button.textContent = 'Active';
        button.style.background = '#10b981';
      }
      alert('The user state has been ' + (isActive ? 'set to active' : 'set to inactive') + '.\nIf they try to log in, they will be asked to contact admin.');
    }

    function openAddUserModal() {
      document.getElementById('addUserModal').style.display = 'flex';
      // Generate temporary password
      const password = generateTempPassword();
      document.getElementById('userPassword').value = password;
    }

    function generateTempPassword() {
      const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
      let password = '';
      for (let i = 0; i < 8; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
      }
      return password;
    }

    function closeAddUserModal() {
      document.getElementById('addUserModal').style.display = 'none';
    }
  </script>

  <!-- Add User Modal -->
  <div id="addUserModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; flex-direction: column;">
    <div class="modal-content" style="background: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 400px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <span style="font-size: 20px; font-weight: bold;">Add New User</span>
        <button class="close" onclick="closeAddUserModal()" style="border: none; background: none; font-size: 20px; cursor: pointer;">&times;</button>
      </div>
      <form method="POST">
        <div style="margin-bottom: 10px;">
          <label style="display: block; margin-bottom: 4px;">Username</label>
          <input type="text" name="username" required style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;">
        </div>
        <div style="margin-bottom: 10px;">
          <label style="display: block; margin-bottom: 4px;">Email</label>
          <input type="email" name="email" required style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;">
        </div>
        <div style="margin-bottom: 10px;">
          <label style="display: block; margin-bottom: 4px;">Temporary Password</label>
          <input type="text" name="password" id="userPassword" readonly required style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;">
        </div>
        <div style="margin-bottom: 15px;">
          <label style="display: block; margin-bottom: 4px;">Role</label>
          <select name="role" required style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;">
            <option value="">Select Role</option>
            <option value="Admin">Admin</option>
            <option value="Receptionist">Receptionist</option>
            <option value="Dentist">Dentist</option>
          </select>
        </div>
        <div style="text-align: right;">
          <button type="button" onclick="closeAddUserModal()" style="padding: 8px 16px; margin-right: 10px; border: 1px solid var(--border); background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
          <button type="submit" name="add_user" style="padding: 8px 16px; background: var(--accent); color: white; border: none; border-radius: 4px; cursor: pointer;">Add User</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
