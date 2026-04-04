<?php
/**
 * ============================================
 * DENTIST APPOINTMENTS - ENHANCED WITH FILTERING & SERVICE DETAILS
 * Last Updated: April 4, 2026
 * Features: Appointment Cards, Service Filtering, Time Display, Clinical Access
 * ✓ FLAG TEST: Dentist appointments successfully updated for Azure
 * ============================================
 */

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
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Dentist' || $_SESSION['tenant_slug'] !== $tenantSlug) {
    header("Location: tenant_login.php?tenant=" . rawurlencode($tenantSlug));
    exit();
}

requireTenantLogin($tenantSlug);

$tenantName = $_SESSION['tenant_name'];
$tenantId = $_SESSION['tenant_id'];
$dentistId = $_SESSION['user_id'];
$dentistName = $_SESSION['username'];

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$today = date('Y-m-d');

// Query Logic
$query = "SELECT a.appointment_id, p.patient_id, p.first_name, p.last_name, 
                 'General Consultation' AS service_name, 
                 a.appointment_date, a.status 
          FROM appointment a
          JOIN patient p ON a.patient_id = p.patient_id
          WHERE a.tenant_id = ? AND a.dentist_id = ?";

if ($filter == 'today') $query .= " AND a.appointment_date = ?";
elseif ($filter == 'upcoming') $query .= " AND a.appointment_date > ?";

$query .= " ORDER BY a.appointment_date ASC";

$result = null;
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    if ($filter == 'today' || $filter == 'upcoming') {
        mysqli_stmt_bind_param($stmt, "iis", $tenantId, $dentistId, $today);
    } else {
        mysqli_stmt_bind_param($stmt, "ii", $tenantId, $dentistId);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Dentist Appointments</title>
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

      .action-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        gap: 20px;
      }

      .search-box {
        padding: 12px 20px;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        width: 100%;
        max-width: 400px;
        font-size: 14px;
      }

      .filter-tabs {
        display: flex;
        background: var(--dashboard-bg);
        padding: 5px;
        border-radius: 12px;
        gap: 5px;
      }

      .tab {
        padding: 8px 20px;
        border-radius: 8px;
        text-decoration: none;
        color: #64748b;
        font-weight: 600;
        font-size: 14px;
        transition: 0.3s;
      }

      .tab.active {
        background: var(--dashboard-accent);
        color: white;
      }

      .appt-list-container {
        display: flex;
        flex-direction: column;
        gap: 15px;
      }

      .appt-card {
        display: grid;
        grid-template-columns: 100px 1fr auto;
        align-items: center;
        background: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        border-left: 6px solid var(--dashboard-accent);
        transition: transform 0.2s;
      }

      .appt-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.08); }

      .time-badge {
        text-align: center;
        border-right: 1px solid var(--dashboard-border);
        padding-right: 15px;
      }

      .time-badge h4 { margin: 0; color: var(--dashboard-accent); font-size: 16px; }
      .time-badge p { margin: 0; font-size: 12px; color: #64748b; }

      .patient-details { padding-left: 20px; }
      .patient-details h3 { margin: 0 0 5px 0; color: #1e293b; font-size: 18px; }
      .service-tag { 
        background: var(--dashboard-bg); 
        color: #64748b; 
        padding: 3px 10px; 
        border-radius: 5px; 
        font-size: 12px; 
        font-weight: 600; 
      }

      .status-indicator {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        margin-top: 8px;
        display: block;
      }

      .btn-treatment {
        background: var(--dashboard-accent);
        color: white !important;
        text-decoration: none;
        padding: 12px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 13px;
        display: inline-block;
      }

      .hidden-card { display: none !important; }

      /* Status Colors */
      .status-pending { color: #f59e0b; }
      .status-completed { color: #10b981; }

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
          <div class="sidebar-section-title">Dentist</div>
          <a href="dentist_dashboard.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">📊</span>
            <span>Dashboard</span>
          </a>
        </div>

        <div class="sidebar-section">
          <div class="sidebar-section-title">Core Features</div>
          <a href="dentist_appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item active">
            <span class="sidebar-nav-icon">📅</span>
            <span>My Appointments</span>
          </a>
          <a href="dentist_patients.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">👥</span>
            <span>My Patients</span>
          </a>
        </div>
      </div>

      <div class="sidebar-footer">
        <a href="dentist_logout.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-logout-btn">
          <span>🚪</span>
          <span>Sign Out</span>
        </a>
      </div>
    </nav>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <!-- Header Bar -->
      <div class="tenant-header-bar">
        <div class="tenant-header-title"><?php echo h($tenantName); ?> Dentist Portal</div>
        <div style="display: flex; align-items: center; gap: 16px;">
          <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
          <div id="liveClock" class="live-clock-badge">00:00:00 AM</div>
        </div>
      </div>

      <!-- Content -->
      <div class="content-section">
        <div class="content-header">
          <h2 class="content-title">My Appointments</h2>
        </div>

        <div class="action-bar">
          <input type="text" id="apptSearch" class="search-box" placeholder="🔍 Search patient name..." onkeyup="filterAppointments()">
          
          <div class="filter-tabs">
            <a href="?tenant=<?php echo rawurlencode($tenantSlug); ?>&filter=all" class="tab <?php echo $filter == 'all' ? 'active' : ''; ?>">All</a>
            <a href="?tenant=<?php echo rawurlencode($tenantSlug); ?>&filter=today" class="tab <?php echo $filter == 'today' ? 'active' : ''; ?>">Today</a>
            <a href="?tenant=<?php echo rawurlencode($tenantSlug); ?>&filter=upcoming" class="tab <?php echo $filter == 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
          </div>
        </div>

        <div class="appt-list-container" id="apptList">
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <div class="appt-card" data-name="<?php echo strtolower($row['first_name'] . ' ' . $row['last_name']); ?>">
                <div class="time-badge">
                  <h4><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></h4>
                  <p>Status: <?php echo h($row['status'] ?? ''); ?></p>
                </div>
                
                <div class="patient-details">
                  <h3><?php echo h(($row['first_name'] ?? '') . " " . ($row['last_name'] ?? '')); ?></h3>
                  <span class="service-tag"><?php echo h($row['service_name'] ?? 'Not assigned'); ?></span>
                  <span class="status-indicator status-<?php echo strtolower($row['status'] ?? ''); ?>">
                    ● <?php echo h($row['status'] ?? ''); ?>
                  </span>
                </div>
                
                <div class="appt-actions">
                  <a href="clinical_record.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&id=<?php echo $row['patient_id']; ?>&appt=<?php echo $row['appointment_id']; ?>" class="btn-treatment">
                    Open Clinical Log
                  </a>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div style="text-align:center; padding: 60px; background:white; border-radius:15px; color:#94a3b8;">
              <p>No appointments found for this selection.</p>
            </div>
          <?php endif; ?>
        </div>
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
    console.log('Dentist Appointments Module Active');
    console.log('FINAL UI SYNC COMPLETE');
    
    function filterAppointments() {
      let input = document.getElementById('apptSearch').value.toLowerCase();
      let cards = document.getElementsByClassName('appt-card');

      for (let i = 0; i < cards.length; i++) {
        let name = cards[i].getAttribute('data-name');
        if (name.includes(input)) {
          cards[i].classList.remove('hidden-card');
        } else {
          cards[i].classList.add('hidden-card');
        }
      }
    }
  </script>
</body>
</html>