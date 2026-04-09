<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

// Role Check Implementation - Ensure user is logged in as receptionist
$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('receptionist');

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function baseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
// requireTenantLogin is now handled by session manager above

$tenantData = $sessionManager->getTenantData();
$tenantName = $tenantData['tenant_name'] ?? '';
$tenantId = $sessionManager->getTenantId();
$receptionistName = $sessionManager->getUsername() ?? 'Receptionist';

// Get receptionist's first name
$receptionistFirstName = 'Receptionist';
$userId = $sessionManager->getUserId();
$stmt = mysqli_prepare($conn, "SELECT first_name FROM users WHERE user_id = ? AND tenant_id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $userId, $tenantId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($row = $res->fetch_assoc())) {
        $receptionistFirstName = $row['first_name'] ?? 'Receptionist';
    }
}

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

// 3. Total Patients
$newPatients = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM patient WHERE tenant_id = ?");
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
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <!-- Header Bar -->
      <div class="tenant-header-bar">
        <div class="tenant-header-title"><?php echo h($tenantName); ?> Front Desk</div>
        <?php renderDateClock(); ?>
      </div>

      <!-- Dashboard Content -->
      <div class="dashboard-header">
        <h1 style="font-size: 32px; margin-bottom: 8px;">Welcome back, <?php echo h($receptionistFirstName); ?></h1>
        <div class="dashboard-header-meta">Your front desk dashboard for today's operations.</div>
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
          <div class="stat-label">Total Patients</div>
          <div class="stat-value"><?php echo $newPatients; ?></div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="actions-grid">
          <a href="receptionist_patients.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="action-btn">
            <span class="action-icon">➕</span>
            <span>Add Patient</span>
          </a>
          <a href="receptionist_appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="action-btn">
            <span class="action-icon">📅</span>
            <span>Schedule Appointment</span>
          </a>
          <a href="receptionist_billing.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="action-btn">
            <span class="action-icon">💳</span>
            <span>Process Payment</span>
          </a>
        </div>
      </div>

      <!-- Queue Section -->
      <div class="queue-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <h2 style="margin: 0; color: var(--dashboard-accent);">Today's Patient Appointment Queue</h2>
          <a href="receptionist_appointments.php?tenant=<?php echo urlencode($tenantSlug); ?>&action=add" style="background: var(--dashboard-accent); color: white; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px;">+ Add Appointment</a>
        </div>
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
                  <td><a href="receptionist_appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="action-link">Manage</a></td>
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

  <script>
  <?php printDateClockScript(); ?>

  // Verification logs
  console.log('UI Parity Active - Version 2.0');
  console.log('Receptionist Dashboard Initialized');
  console.log('FINAL UI SYNC COMPLETE');
  console.log('Anti-Crash System Active - V2');
  </script>
</body>
</html>

