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
$receptionistName = $_SESSION['username'] ?? 'Receptionist';

/* ============================================================
   DATA FETCHING
============================================================ */

$query = "SELECT 
            a.appointment_id, 
            a.appointment_date, 
            p.first_name, 
            p.last_name, 
            d.last_name AS d_last, 
            a.status 
          FROM appointment a 
          LEFT JOIN patient p ON a.patient_id = p.patient_id 
          LEFT JOIN dentist d ON a.dentist_id = d.dentist_id
          WHERE a.tenant_id = ?
          ORDER BY a.appointment_date DESC, a.appointment_id ASC";

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

      .live-clock-badge {
        background: linear-gradient(135deg, rgba(13, 59, 102, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
        border: 2px solid var(--dashboard-accent);
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 16px;
        font-weight: 700;
        color: var(--dashboard-accent);
        font-family: 'Courier New', monospace;
        letter-spacing: 1px;
        white-space: nowrap;
      }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <!-- Sidebar Navigation -->
    <nav class="tenant-sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo">
          <img src="oral logo.png" alt="OralSync" class="sidebar-logo-icon">
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
          <a href="receptionist_patients.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">👥</span>
            <span>Patients</span>
          </a>
          <a href="receptionist_billing.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
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
        <div style="display: flex; align-items: center; gap: 16px;">
          <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
          <div id="liveClock" class="live-clock-badge">00:00:00 AM</div>
        </div>
      </div>

      <!-- Content -->
      <?php if (isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
        <div class="alert-box">✅ Appointment updated successfully!</div>
      <?php endif; ?>

      <div class="content-section">
        <div class="content-header">
          <h2 class="content-title">All Appointments Master List</h2>
        </div>

        <table class="queue-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Patient</th>
              <th>Dentist</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
              <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></td>
                  <td><strong><?php echo h(($row['first_name'] ?? '') . " " . ($row['last_name'] ?? '')); ?></strong></td>
                  <td>Dr. <?php echo h($row['d_last'] ?? ''); ?></td>
                  <td><span class="status-pill <?php echo strtolower($row['status'] ?? ''); ?>"><?php echo h($row['status'] ?? ''); ?></span></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5" style="text-align:center; padding:30px;">No appointments found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    // Live Clock Update Function
    function updateClock() {
      const now = new Date();
      const timeString = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
      });
      const clockElement = document.getElementById('liveClock');
      if (clockElement) {
        clockElement.textContent = timeString;
      }
    }
    updateClock();
    setInterval(updateClock, 1000);
    
    // Verification logs
    console.log('UI Parity Active - Version 2.0');
    console.log('Receptionist Appointments Page Initialized');
    console.log('FINAL UI SYNC COMPLETE');
    console.log('Anti-Crash System Active - V2');
  </script>
</body>
</html>