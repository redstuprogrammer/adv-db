<?php
/**
 * ============================================
 * DENTIST DASHBOARD - ENHANCED WITH CALENDAR & APPOINTMENT TRACKING
 * Last Updated: April 4, 2026
 * Features: Calendar View, Today's Schedule, Appointment Metrics, Service Details
 * ✓ FLAG TEST: Dentist dashboard successfully updated for Azure
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

$todayDate = date('Y-m-d');

/* =========================
   DENTIST METRICS
========================= */
$totalAppt = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM appointment WHERE tenant_id = ? AND dentist_id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $tenantId, $dentistId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $totalAppt = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

$todayAppt = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM appointment WHERE tenant_id = ? AND dentist_id = ? AND appointment_date = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "iis", $tenantId, $dentistId, $todayDate);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $todayAppt = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

$weekAppt = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM appointment WHERE tenant_id = ? AND dentist_id = ? AND YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1)");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $tenantId, $dentistId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $weekAppt = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

$monthAppt = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM appointment WHERE tenant_id = ? AND dentist_id = ? AND MONTH(appointment_date) = MONTH(CURRENT_DATE()) AND YEAR(appointment_date) = YEAR(CURRENT_DATE())");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $tenantId, $dentistId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $monthAppt = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

/* =========================
   TODAY'S SCHEDULE
========================= */
$scheduleResult = null;
$stmt = mysqli_prepare($conn, "SELECT a.appointment_id, a.appointment_date, p.first_name, p.last_name, a.status 
                  FROM appointment a 
                  JOIN patient p ON a.patient_id = p.patient_id 
                  WHERE a.tenant_id = ? AND a.appointment_date = ? AND a.dentist_id = ? 
                  ORDER BY a.appointment_date ASC");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "isi", $tenantId, $todayDate, $dentistId);
    mysqli_stmt_execute($stmt);
    $scheduleResult = mysqli_stmt_get_result($stmt);
}

/* =========================
   CALENDAR LOGIC
========================= */
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$numberDays = date('t', $firstDayOfMonth);
$dayOfWeek = date('w', $firstDayOfMonth);

$prevMonth = ($month == 1) ? 12 : $month - 1;
$prevYear = ($month == 1) ? $year - 1 : $year;
$nextMonth = ($month == 12) ? 1 : $month + 1;
$nextYear = ($month == 12) ? $year + 1 : $year;

// Get appointment dates for this dentist
$calendarAppts = [];
$stmt = mysqli_prepare($conn, "SELECT DISTINCT appointment_date FROM appointment WHERE tenant_id = ? AND dentist_id = ? AND MONTH(appointment_date) = ? AND YEAR(appointment_date) = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "iiii", $tenantId, $dentistId, $month, $year);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while($row = $res->fetch_assoc()){
            $calendarAppts[] = $row['appointment_date'];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Dentist Dashboard</title>
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
      .stat-icon.icon-red { background: rgba(239, 68, 68, 0.1); }

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

      .calendar-section, .schedule-section {
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 32px;
      }

      .calendar-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
      .calendar-table th { color: #64748b; font-size: 12px; padding: 10px 0; font-weight: 600; text-transform: uppercase; }
      .calendar-table td { height: 60px; vertical-align: top; padding: 8px; border: 1px solid var(--dashboard-border); width: 14.28%; }

      .day-container { display: flex; flex-direction: column; align-items: center; justify-content: flex-start; height: 100%; }
      .day-num { font-size: 14px; font-weight: 500; color: #334155; display: inline-block; width: 24px; height: 24px; line-height: 24px; text-align: center; }

      .today-highlight .day-num { background: var(--dashboard-accent); color: white; border-radius: 50%; }
      .appt-dot { width: 6px; height: 6px; background-color: #38bdf8; border-radius: 50%; margin-top: 5px; }

      .schedule-item-pop { background: var(--dashboard-bg); border-left: 4px solid var(--dashboard-accent); padding: 12px; margin-bottom: 10px; border-radius: 4px; }
      .schedule-item-pop strong { color: var(--dashboard-accent); font-size: 14px; }
      .schedule-item-pop span { font-size: 13px; color: #475569; }
      .schedule-item-pop small { color: #64748b; font-weight: bold; }

      .cal-nav { display: flex; justify-content: space-between; align-items: center; padding: 10px; background: var(--dashboard-bg); border-radius: 8px; margin-bottom: 10px; }
      .cal-nav a { text-decoration: none; color: var(--dashboard-accent); font-weight: bold; font-size: 18px; }

      .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
      }

      @media (max-width: 768px) {
        .dashboard-stats {
          grid-template-columns: 1fr;
        }
        .dashboard-grid {
          grid-template-columns: 1fr;
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
          <div class="sidebar-section-title">Dentist</div>
          <a href="dentist_dashboard.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item active">
            <span class="sidebar-nav-icon">📊</span>
            <span>Dashboard</span>
          </a>
        </div>

        <div class="sidebar-section">
          <div class="sidebar-section-title">Core Features</div>
          <a href="dentist_appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
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
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
      </div>

      <!-- Dashboard Content -->
      <div class="dashboard-header">
        <h1>Welcome, Dr. <?php echo h($dentistName); ?></h1>
        <div class="dashboard-header-meta">Here is your clinical overview for today.</div>
      </div>

      <!-- Stats Cards -->
      <div class="dashboard-stats">
        <div class="stat-card">
          <div class="stat-icon icon-blue">📋</div>
          <div class="stat-label">Total Cases</div>
          <div class="stat-value"><?php echo $totalAppt; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon icon-green">📅</div>
          <div class="stat-label">Today's Patients</div>
          <div class="stat-value"><?php echo $todayAppt; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon icon-amber">📆</div>
          <div class="stat-label">Upcoming (Week)</div>
          <div class="stat-value"><?php echo $weekAppt; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon icon-red">📈</div>
          <div class="stat-label">Monthly Volume</div>
          <div class="stat-value"><?php echo $monthAppt; ?></div>
        </div>
      </div>

      <!-- Dashboard Grid -->
      <div class="dashboard-grid">
        <div class="calendar-section">
          <h2 style="margin-bottom: 16px; color: var(--dashboard-accent);">Your Calendar</h2>
          <div class="cal-nav">
            <a href="?tenant=<?php echo rawurlencode($tenantSlug); ?>&m=<?php echo $prevMonth; ?>&y=<?php echo $prevYear; ?>">❮</a>
            <span style="font-weight: 700; color: var(--dashboard-accent);"><?php echo date('F Y', $firstDayOfMonth); ?></span>
            <a href="?tenant=<?php echo rawurlencode($tenantSlug); ?>&m=<?php echo $nextMonth; ?>&y=<?php echo $nextYear; ?>">❯</a>
          </div>

          <table class="calendar-table">
            <thead>
              <tr>
                <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <?php
                // Empty slots before first day
                for ($i = 0; $i < $dayOfWeek; $i++) {
                  echo "<td></td>";
                }

                for ($day = 1; $day <= $numberDays; $day++) {
                  // Start new row every 7 days
                  if (($i + $day - 1) % 7 == 0 && $day != 1) {
                    echo "</tr><tr>";
                  }

                  $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                  $isToday = ($currentDate == date('Y-m-d')) ? 'today-highlight' : '';
                  $hasAppt = in_array($currentDate, $calendarAppts) ? '<div class="appt-dot"></div>' : '';

                  echo "<td class='$isToday'>
                          <div class='day-container'>
                            <span class='day-num'>$day</span>
                            $hasAppt
                          </div>
                        </td>";
                }

                // Empty slots after last day
                while (($i + $day - 1) % 7 != 0) {
                  echo "<td></td>";
                  $day++;
                }
                ?>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="schedule-section">
          <h2 style="margin-bottom: 16px; color: var(--dashboard-accent);">Your Schedule for Today</h2>
          <p class="schedule-date" style="font-weight:bold; color:#64748b; margin-bottom:15px;">
            📅 <?php echo date('D, M d, Y'); ?>
          </p>

          <?php if ($scheduleResult && $scheduleResult->num_rows > 0): ?>
            <?php while($row = $scheduleResult->fetch_assoc()): ?>
              <div class="schedule-item-pop">
                <small>📅 <?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></small><br>
                <strong>General Consultation</strong><br>
                <span>Patient: <?php echo h($row['first_name'] . " " . $row['last_name']); ?></span><br>
                <small>Status: <?php echo h($row['status']); ?></small>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div style="text-align:center; padding: 40px 0;">
              <p style="color:#94a3b8;">You have no appointments scheduled for today.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>