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
    header("Location: tenant_login.php?tenant=" . rawurlencode($tenantSlug));
    exit();
}

requireTenantLogin($tenantSlug);

$tenantName = $_SESSION['tenant_name'];
$tenantId = $_SESSION['tenant_id'];
$receptionistName = $_SESSION['username'];

$todayDate = date('Y-m-d');

/* =========================
   RECEPTIONIST METRICS
========================= */
// 1. Pending Appointments (Patients yet to arrive/be seen)
$pendingCount = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM appointment WHERE tenant_id = ? AND appointment_date = ? AND status = 'Pending'");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "is", $tenantId, $todayDate);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $pendingCount = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

// 2. Completed Today (Patients who finished their session)
$completedCount = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM appointment WHERE tenant_id = ? AND appointment_date = ? AND status = 'Completed'");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "is", $tenantId, $todayDate);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $completedCount = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

// 3. New Patients Added This Month
$newPatients = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM patient WHERE tenant_id = ? AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $tenantId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $newPatients = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

/* =========================
   ARRIVAL LIST (QUEUE)
========================= */
$queueResult = null;
$stmt = mysqli_prepare($conn, "SELECT a.appointment_id, p.first_name, p.last_name, d.last_name AS d_last, a.status, a.appointment_date 
               FROM appointment a 
               JOIN patient p ON a.patient_id = p.patient_id 
               JOIN dentist d ON a.dentist_id = d.dentist_id
               WHERE a.tenant_id = ? AND a.appointment_date = ? 
               ORDER BY a.appointment_id ASC");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "is", $tenantId, $todayDate);
    mysqli_stmt_execute($stmt);
    $queueResult = mysqli_stmt_get_result($stmt);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Front Desk Dashboard</title>
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

      .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        gap: 20px;
      }

      .dashboard-header h1 {
        font-size: 28px;
        font-weight: 900;
        color: var(--dashboard-accent);
        margin: 0;
      }

      .dashboard-header-meta {
        color: #64748b;
        font-size: 14px;
      }

      .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
      }

      .stat-card {
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        transition: all 0.2s ease;
      }

      .stat-card:hover {
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
        border-color: var(--dashboard-accent);
      }

      .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-bottom: 12px;
      }

      .stat-icon.icon-blue { background: rgba(13, 59, 102, 0.1); }
      .stat-icon.icon-green { background: rgba(16, 185, 129, 0.1); }
      .stat-icon.icon-amber { background: rgba(245, 158, 11, 0.1); }

      .stat-label {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
      }

      .stat-value {
        font-size: 28px;
        font-weight: 900;
        color: var(--dashboard-accent);
      }

      .queue-section {
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 32px;
      }

      .queue-table { width: 100%; border-collapse: collapse; }
      .queue-table th { background: var(--dashboard-bg); color: #64748b; padding: 15px; text-align: left; font-size: 12px; text-transform: uppercase; }
      .queue-table td { padding: 15px; border-bottom: 1px solid var(--dashboard-border); }

      .time-badge { background: rgba(13, 59, 102, 0.1); color: var(--dashboard-accent); padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
      .action-link { color: var(--dashboard-accent); text-decoration: none; font-weight: 600; font-size: 13px; }
      .action-link:hover { text-decoration: underline; }

      .quick-actions {
        margin-bottom: 32px;
      }

      .quick-actions h2 {
        font-size: 16px;
        font-weight: 700;
        color: var(--dashboard-accent);
        margin-bottom: 16px;
      }

      .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
      }

      .action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 16px;
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 10px;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s ease;
      }

      .action-btn:hover {
        background: var(--dashboard-bg);
        border-color: var(--dashboard-accent);
        box-shadow: 0 4px 12px rgba(13, 59, 102, 0.15);
      }

      .action-icon {
        font-size: 24px;
      }

      @media (max-width: 768px) {
        .dashboard-header {
          flex-direction: column;
          align-items: flex-start;
        }

        .dashboard-stats {
          grid-template-columns: 1fr;
        }

        .actions-grid {
          grid-template-columns: repeat(2, 1fr);
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
          <div class="sidebar-section-title">Front Desk</div>
          <a href="receptionist_dashboard.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item active">
            <span class="sidebar-nav-icon">📊</span>
            <span>Dashboard</span>
          </a>
        </div>

        <div class="sidebar-section">
          <div class="sidebar-section-title">Core Features</div>
          <a href="appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
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

      <!-- Dashboard Content -->
      <div class="dashboard-header">
        <h1>Front Desk Overview</h1>
        <div class="dashboard-header-meta">Welcome back, <?php echo h($receptionistName); ?></div>
      </div>

      <!-- Stats Cards -->
      <div class="dashboard-stats">
        <div class="stat-card">
          <div class="stat-icon icon-amber">⏳</div>
          <div class="stat-label">Waiting/Pending</div>
          <div class="stat-value"><?php echo $pendingCount; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon icon-green">✅</div>
          <div class="stat-label">Check-outs Done</div>
          <div class="stat-value"><?php echo $completedCount; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon icon-blue">👥</div>
          <div class="stat-label">New Patients (Month)</div>
          <div class="stat-value"><?php echo $newPatients; ?></div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="actions-grid">
          <a href="patients.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="action-btn">
            <span class="action-icon">➕</span>
            <span>Add Patient</span>
          </a>
          <a href="appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="action-btn">
            <span class="action-icon">📅</span>
            <span>Schedule Appointment</span>
          </a>
          <a href="billing.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="action-btn">
            <span class="action-icon">💳</span>
            <span>Process Payment</span>
          </a>
        </div>
      </div>

      <!-- Queue Section -->
      <div class="queue-section">
        <h2 style="margin-bottom: 16px; color: var(--dashboard-accent);">Today's Patient Appointment Queue</h2>
        <table class="queue-table">
          <thead>
            <tr>
              <th>Patient</th>
              <th>Dentist</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($queueResult && $queueResult->num_rows > 0): ?>
              <?php while($row = $queueResult->fetch_assoc()): ?>
                <tr>
                  <td><strong><?php echo h($row['first_name'] . " " . $row['last_name']); ?></strong></td>
                  <td>Dr. <?php echo h($row['d_last']); ?></td>
                  <td><span class="status-pill <?php echo strtolower($row['status']); ?>"><?php echo h($row['status']); ?></span></td>
                  <td><a href="appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="action-link">Manage</a></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align:center; padding:20px;">No patients scheduled for today.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>