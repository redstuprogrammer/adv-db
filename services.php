<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/security_headers.php';
require_once 'connect.php';
require_once 'tenant_utils.php';

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

// Handle form submissions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_service'])) {
        $serviceName = trim($_POST['service_name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        
        if (!empty($serviceName) && $price > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO service (tenant_id, service_name, price) VALUES (?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "isd", $tenantId, $serviceName, $price);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Service added successfully!';
                } else {
                    $message = 'Error adding service.';
                }
            }
        } else {
            $message = 'Please provide valid service name and price.';
        }
    } elseif (isset($_POST['delete_service'])) {
        $serviceId = intval($_POST['service_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM service WHERE service_id = ? AND tenant_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $serviceId, $tenantId);
            mysqli_stmt_execute($stmt);
            $message = 'Service deleted successfully!';
        }
    }
}

// Fetch services
$services = [];
$stmt = mysqli_prepare($conn, "SELECT service_id, service_name, price FROM service WHERE tenant_id = ? ORDER BY service_name");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $tenantId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $services[] = $row;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Services</title>
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

      .btn-danger {
        background: #ef4444;
        color: white;
        padding: 6px 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        transition: background 0.2s ease;
      }

      .btn-danger:hover {
        background: #dc2626;
      }

      .module-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        margin-bottom: 24px;
      }

      .form-group {
        margin-bottom: 16px;
      }

      .form-group label {
        display: block;
        margin-bottom: 6px;
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

      .message {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-weight: 600;
      }

      .message.success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
      }

      .message.error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
      }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <nav class="tenant-sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo-icon">🏥</div>
        <div class="sidebar-logo-text">OralSync</div>
        <div class="sidebar-clinic-name"><?php echo h($tenantName); ?></div>
      </div>
      <div class="sidebar-nav">
        <a href="tenant_dashboard.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">📊</span>
          <span>Dashboard</span>
        </a>
        <a href="patients.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">👥</span>
          <span>Patients</span>
        </a>
        <a href="appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">📅</span>
          <span>Appointments</span>
        </a>
        <a href="billing.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">💳</span>
          <span>Billing</span>
        </a>
        <a href="manage_users.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">👤</span>
          <span>Users</span>
        </a>
        <a href="tenant_reports.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">📈</span>
          <span>Reports</span>
        </a>
        <a href="services.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item active">
          <span class="sidebar-nav-icon">🛠️</span>
          <span>Services</span>
        </a>
        <a href="tenant_settings.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">⚙️</span>
          <span>Settings</span>
        </a>
      </div>
    </nav>

    <main class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">Services Management</div>
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
      </div>

      <?php if (!empty($message)): ?>
      <div class="message <?php echo strpos($message, 'success') !== false ? 'success' : 'error'; ?>">
        <?php echo h($message); ?>
      </div>
      <?php endif; ?>

      <div class="module-card">
        <h2>Add New Service</h2>
        <form method="post">
          <div class="form-group">
            <label for="service_name">Service Name</label>
            <input type="text" id="service_name" name="service_name" required>
          </div>
          <div class="form-group">
            <label for="price">Price ($)</label>
            <input type="number" id="price" name="price" step="0.01" min="0" required>
          </div>
          <button type="submit" name="add_service" class="btn-primary">Add Service</button>
        </form>
      </div>

      <div class="module-card">
        <h2>Existing Services</h2>
        <?php if (empty($services)): ?>
        <p>No services added yet.</p>
        <?php else: ?>
        <table class="module-table">
          <thead>
            <tr>
              <th>Service Name</th>
              <th>Price</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($services as $service): ?>
            <tr>
              <td><?php echo h($service['service_name']); ?></td>
              <td>$<?php echo number_format($service['price'], 2); ?></td>
              <td>
                <form method="post" style="display: inline;">
                  <input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">
                  <button type="submit" name="delete_service" class="btn-danger" onclick="return confirm('Are you sure you want to delete this service?')">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </main>
  </div>
</body>
</html>
