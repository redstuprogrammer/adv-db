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

function baseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Receptionist' || $_SESSION['tenant_slug'] !== $tenantSlug) {
    header("Location: receptionist_login.php?tenant=" . rawurlencode($tenantSlug));
    exit();
}

$tenantName = $_SESSION['tenant_name'];
$tenantId = $_SESSION['tenant_id'];
$receptionistName = $_SESSION['username'];

/* ============================================================
   DATA FETCHING
============================================================ */
$query = "SELECT 
            a.appointment_id, 
            a.appointment_date, 
            a.appointment_time, 
            p.first_name, 
            p.last_name, 
            COALESCE(s.service_name, 'Unassigned') AS service_name, 
            d.last_name AS d_last, 
            a.status 
          FROM appointments a 
          LEFT JOIN patients p ON a.patient_id = p.patient_id 
          LEFT JOIN services s ON a.service_id = s.service_id 
          LEFT JOIN dentists d ON a.dentist_id = d.dentist_id
          WHERE a.tenant_id = ?
          ORDER BY a.appointment_date DESC, a.appointment_time ASC";

$result = null;
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $tenantId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Front Desk Appointments</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
      :root {
        --dashboard-accent: #0d3b66;
        --dashboard-success: #10b981;
        --dashboard-warning: #f59e0b;
        --dashboard-danger: #ef4444;
        --dashboard-border: #e2e8f0;
        --dashboard-bg: #f8fafc;
      }

      .queue-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
      .queue-table th { background: var(--dashboard-bg); color: #64748b; padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; }
      .queue-table td { padding: 15px; border-bottom: 1px solid var(--dashboard-border); font-size: 14px; }

      .time-badge { background: rgba(13, 59, 102, 0.1); color: var(--dashboard-accent); padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
      .action-link { color: var(--dashboard-accent); text-decoration: none; font-weight: 600; font-size: 13px; border: 1px solid var(--dashboard-accent); padding: 5px 12px; border-radius: 6px; transition: 0.2s; }
      .action-link:hover { background: var(--dashboard-accent); color: white; }

      .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
      .status-pill.pending { background: #fff3cd; color: #856404; }
      .status-pill.completed { background: #dcfce7; color: #166534; }
      .status-pill.cancelled { background: #fee2e2; color: #991b1b; }

      .alert-box { padding: 15px; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }

      .content-section {
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 32px;
      }

      .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
      }

      .content-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--dashboard-accent);
      }

      .add-btn {
        background: var(--dashboard-accent);
        color: white;
        border: none;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 16px;
        cursor: pointer;
        text-decoration: none;
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
          <div class="sidebar-section-title">Front Desk</div>
          <a href="receptionist_dashboard.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">📊</span>
            <span>Dashboard</span>
          </a>
        </div>

        <div class="sidebar-section">
          <div class="sidebar-section-title">Core Features</div>
          <a href="receptionist_appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item active">
            <span class="sidebar-nav-icon">📅</span>
            <span>Appointments</span>
          </a>
          <a href="patients.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">👥</span>
            <span>Patients</span>
          </a>
          <a href="billing.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">💳</span>
            <span>Billing</span>
          </a>
        </div>
      </div>

      <div class="sidebar-footer">
        <a href="receptionist_logout.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-logout-btn">
          <span>🚪</span>
          <span>Sign Out</span>
        </a>
      </div>
    </nav>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <!-- Header Bar -->
      <div class="tenant-header-bar">
        <div class="tenant-header-title"><?php echo h($tenantName); ?> Front Desk</div>
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
      </div>

      <!-- Content -->
      <?php if (isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
        <div class="alert-box">✅ Appointment updated successfully!</div>
      <?php endif; ?>

      <div class="content-section">
        <div class="content-header">
          <h2 class="content-title">All Appointments Master List</h2>
          <a href="appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="add-btn">+</a>
        </div>

        <table class="queue-table">
          <thead>
            <tr>
              <th>Time & Date</th>
              <th>Patient</th>
              <th>Dentist</th>
              <th>Service</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
              <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                  <td>
                    <span class="time-badge"><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></span>
                    <div style="font-size: 11px; color: #64748b; margin-top:4px;"><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></div>
                  </td>
                  <td><strong><?php echo h($row['first_name'] . " " . $row['last_name']); ?></strong></td>
                  <td>Dr. <?php echo h($row['d_last']); ?></td>
                  <td><?php echo h($row['service_name']); ?></td>
                  <td><span class="status-pill <?php echo strtolower($row['status']); ?>"><?php echo h($row['status']); ?></span></td>
                  <td>
                    <a href="appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&id=<?php echo $row['appointment_id']; ?>" class="action-link">Manage</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align:center; padding:30px;">No appointments found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>