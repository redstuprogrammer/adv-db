<?php
/**
 * ============================================
 * DENTIST DASHBOARD - ENHANCED WITH CALENDAR & APPOINTMENT TRACKING
 * Last Updated: April 4, 2026
 * Features: Calendar View, Today's Schedule, Appointment Metrics, Service Details
 * ✓ FLAG TEST: Dentist dashboard successfully updated for Azure
 * ============================================
 */

require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

// Role Check Implementation - Ensure user is logged in as dentist
$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('dentist');

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

function formatTime12Hour($time) {
    if (empty($time)) return 'TBD';
    $parts = explode(':', $time);
    if (count($parts) < 2) return $time;
    $hour = (int)$parts[0];
    $minute = $parts[1];
    $ampm = $hour >= 12 ? 'PM' : 'AM';
    if ($hour > 12) $hour -= 12;
    if ($hour === 0) $hour = 12;
    return $hour . ':' . $minute . ' ' . $ampm;
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
// requireTenantLogin is now handled by session manager above

$tenantData = $sessionManager->getTenantData();
$tenantName = $tenantData['tenant_name'] ?? '';
$tenantId = $sessionManager->getTenantId();
$dentistId = $sessionManager->getUserId();
$dentistName = $sessionManager->getUsername() ?? 'Doctor';

// Get dentist's first name
$dentistFirstName = 'Doctor';
$stmt = mysqli_prepare($conn, "SELECT first_name FROM users WHERE user_id = ? AND tenant_id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $dentistId, $tenantId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && $row = $res->fetch_assoc()) {
        $dentistFirstName = $row['first_name'] ?? 'Doctor';
    }
}

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
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM appointment WHERE tenant_id = ? AND dentist_id = ? AND appointment_date = ? AND status != 'Cancelled'");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "iis", $tenantId, $dentistId, $todayDate);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $todayAppt = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

$weekAppt = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM appointment WHERE tenant_id = ? AND dentist_id = ? AND YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1) AND status != 'Cancelled'");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $tenantId, $dentistId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $weekAppt = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

$monthAppt = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM appointment WHERE tenant_id = ? AND dentist_id = ? AND MONTH(appointment_date) = MONTH(CURRENT_DATE()) AND YEAR(appointment_date) = YEAR(CURRENT_DATE()) AND status != 'Cancelled'");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $tenantId, $dentistId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $monthAppt = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

/* =========================
   PENDING INVOICES
========================= */
$pendingInvoices = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM payment py
          JOIN appointment a ON py.appointment_id = a.appointment_id
          WHERE py.tenant_id = ? AND a.dentist_id = ? AND py.status != 'Paid'");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $tenantId, $dentistId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $pendingInvoices = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

$totalPatients = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(DISTINCT p.patient_id) AS total FROM patient p JOIN appointment a ON p.patient_id = a.patient_id WHERE a.tenant_id = ? AND a.dentist_id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $tenantId, $dentistId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $totalPatients = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

$monthlyRevenue = 0.00;
$stmt = mysqli_prepare($conn, "SELECT SUM(py.amount) AS total FROM payment py JOIN appointment a ON py.appointment_id = a.appointment_id WHERE py.tenant_id = ? AND a.dentist_id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $tenantId, $dentistId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $monthlyRevenue = $res ? (float)($res->fetch_assoc()['total'] ?? 0.00) : 0.00;
}

/* =========================
   TODAY'S SCHEDULE
========================= */

$scheduleResult = null;
$stmt = mysqli_prepare($conn, "SELECT a.appointment_id, a.appointment_date, p.first_name, p.last_name, a.status, s.service_name
                  FROM appointment a
                  JOIN patient p ON a.patient_id = p.patient_id
                  LEFT JOIN service s ON a.service_id = s.service_id
                  WHERE a.tenant_id = ? AND a.appointment_date = ? AND a.dentist_id = ? AND a.status != 'Cancelled'
                  ORDER BY a.appointment_date ASC");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "isi", $tenantId, $todayDate, $dentistId);
    mysqli_stmt_execute($stmt);
    $scheduleResult = mysqli_stmt_get_result($stmt);
}

/* =========================
   UPCOMING APPOINTMENTS
========================= */
$upcomingResult = null;
$stmt = mysqli_prepare($conn, "SELECT a.appointment_id, a.appointment_date, a.appointment_time, p.first_name, p.last_name, a.status, s.service_name
                  FROM appointment a
                  JOIN patient p ON a.patient_id = p.patient_id
                  LEFT JOIN service s ON a.service_id = s.service_id
                  WHERE a.tenant_id = ? AND a.appointment_date > ? AND a.dentist_id = ? AND a.status != 'Cancelled'
                  ORDER BY a.appointment_date ASC, a.appointment_time ASC
                  LIMIT 10");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "isi", $tenantId, $todayDate, $dentistId);
    mysqli_stmt_execute($stmt);
    $upcomingResult = mysqli_stmt_get_result($stmt);
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

$calendarDetails = [];
$stmt = mysqli_prepare($conn, "SELECT a.appointment_date, a.appointment_time, p.first_name, p.last_name, s.service_name, a.status 
                  FROM appointment a 
                  JOIN patient p ON a.patient_id = p.patient_id 
                  LEFT JOIN service s ON a.service_id = s.service_id 
                  WHERE a.tenant_id = ? AND a.dentist_id = ? AND MONTH(a.appointment_date) = ? AND YEAR(a.appointment_date) = ? AND a.status != 'Cancelled'
                  ORDER BY a.appointment_time ASC");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "iiii", $tenantId, $dentistId, $month, $year);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $date = $row['appointment_date'];
            if (!isset($calendarDetails[$date])) {
                $calendarDetails[$date] = [];
            }
            $calendarDetails[$date][] = [
                'time' => formatTime12Hour($row['appointment_time']),
                'patient' => ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''),
                'service' => $row['service_name'] ?? 'General Service',
                'status' => $row['status']
            ];
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

      .schedule-item-pop small { color: #64748b; font-weight: bold; }
      
      .status-indicator { font-size: 11px; font-weight: 700; text-transform: uppercase; margin-top: 5px; display: inline-block; }
      .status-pending { color: #f59e0b; }
      .status-completed { color: #10b981; }
      .status-in-progress { color: #0ea5e9; }

      .cal-nav { display: flex; justify-content: space-between; align-items: center; padding: 10px; background: var(--dashboard-bg); border-radius: 8px; margin-bottom: 10px; }
      .cal-nav a { text-decoration: none; color: var(--dashboard-accent); font-weight: bold; font-size: 18px; }

      .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
      }

      .live-clock-badge {
        background: linear-gradient(135deg, rgba(13, 59, 102, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
        border: 2px solid var(--dashboard-accent);
        padding: 10px 20px;
        border-radius: 20px;
        font-size: 19.2px;
        font-weight: 900;
        color: var(--dashboard-accent);
        font-family: 'Courier New', monospace;
        letter-spacing: 1px;
        white-space: nowrap;
      }

      @media (max-width: 768px) {
        .dashboard-stats {
          grid-template-columns: 1fr;
        }
        .dashboard-grid {
          grid-template-columns: 1fr;
        }
      }

      /* Calendar Tooltip Styles */
      .calendar-tooltip {
        position: absolute;
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        z-index: 9999;
        pointer-events: none;
        font-size: 13px;
        color: #1e293b;
        min-width: 260px;
        max-width: 320px;
        display: none;
        opacity: 0;
        transition: opacity 0.2s ease;
      }
      .tooltip-header {
        font-weight: 800;
        color: var(--dashboard-accent);
        border-bottom: 1px solid #f1f5f9;
        margin-bottom: 12px;
        padding-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }
      .tooltip-appt-item {
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px dashed #e2e8f0;
      }
      .tooltip-appt-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
      }
      .tooltip-time {
        font-weight: 700;
        color: #0369a1;
        background: #f0f9ff;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 11px;
      }
      .tooltip-patient {
        font-weight: 600;
        display: block;
        margin: 4px 0;
      }
      .tooltip-service {
        color: #64748b;
        font-size: 12px;
      }
      .calendar-table td.has-appt {
        cursor: pointer;
        background: #f0f9ff44;
      }
      .calendar-table td.has-appt:hover {
        background: #e0f2fe;
      }

      /* Upcoming Appointments Styles */
      .queue-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
      .queue-table th { background: var(--dashboard-bg); color: #64748b; padding: 12px 15px; text-align: left; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid var(--dashboard-border); }
      .queue-table td { padding: 12px 15px; border-bottom: 1px solid var(--dashboard-border); font-size: 13px; }
      .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: bold; text-transform: uppercase; white-space: nowrap; }
      .status-pill.pending { background: #fff3cd; color: #856404; }
      .status-pill.completed { background: #dcfce7; color: #166534; }
      .status-pill.in-progress { background: #e0f2fe; color: #0369a1; }
      .time-badge { background: rgba(13, 59, 102, 0.1); color: var(--dashboard-accent); padding: 4px 8px; border-radius: 6px; font-weight: bold; font-size: 11px; }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <!-- Header Bar -->
      <div class="tenant-header-bar">
        <div class="tenant-header-title">
            <?php echo h($tenantName); ?> Dentist Portal
            <span style="margin-left: 10px; font-size: 14px; background: #e2e8f0; color: #475569; padding: 4px 12px; border-radius: 20px; font-weight: 700; letter-spacing: 0.5px;">
                Code: <?php echo h($sessionManager->getTenantData()['tenant_code'] ?? 'N/A'); ?>
            </span>
        </div>
        <?php renderDateClock(); ?>
      </div>

      <!-- Dashboard Content -->
      <div class="dashboard-header">
        <h1>Welcome, Dr. <?php echo h($dentistFirstName); ?></h1>
        <div class="dashboard-header-meta">Here is your clinical overview for today.</div>
      </div>

      <!-- Stats Cards -->
      <div class="dashboard-stats">
        <div class="stat-card">
          <div class="stat-icon icon-blue">�</div>
          <div class="stat-label">Your Total Cases</div>
          <div class="stat-value"><?php echo $totalAppt; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon icon-green">👥</div>
          <div class="stat-label">Today's Patients</div>
          <div class="stat-value"><?php echo $todayAppt; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon icon-amber">📅</div>
          <div class="stat-label">Upcoming Appointments (Week)</div>
          <div class="stat-value"><?php echo $weekAppt; ?></div>
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
                  $dayAppts = $calendarDetails[$currentDate] ?? null;
                  $hasApptClass = $dayAppts ? 'has-appt' : '';
                  $apptDot = $dayAppts ? '<div class="appt-dot"></div>' : '';
                  $dataDetails = $dayAppts ? 'data-appts="'.h(json_encode($dayAppts)).'"' : '';
                  $dataDateLabel = $dayAppts ? 'data-date-label="'.date('M d, Y', strtotime($currentDate)).'"' : '';

                  echo "<td class='$isToday $hasApptClass' $dataDetails $dataDateLabel>
                          <div class='day-container'>
                            <span class='day-num'>$day</span>
                            $apptDot
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
                  <strong><?php echo h($row['service_name'] ?? ''); ?></strong><br>
                  <span>Patient: <?php echo h(($row['first_name'] ?? '') . " " . ($row['last_name'] ?? '')); ?></span><br>
                  <span class="status-indicator status-<?php echo str_replace(' ', '-', strtolower($row['status'] ?? 'pending')); ?>">
                    ● <?php echo h(ucwords(strtolower($row['status'] ?? 'pending'))); ?>
                  </span>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div style="text-align:center; padding: 40px 0;">
                <p style="color:#94a3b8;">You have no appointments scheduled for today.</p>
              </div>
            <?php endif; ?>
        </div>
      </div>

      <!-- Upcoming Appointments Section -->
      <div style="background: white; border: 1px solid var(--dashboard-border); border-radius: 12px; padding: 24px; margin-bottom: 32px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2 style="margin: 0; color: var(--dashboard-accent); font-size: 18px; font-weight: 800;">Upcoming Appointments</h2>
          <span style="font-size: 12px; color: #64748b; background: #f1f5f9; padding: 4px 12px; border-radius: 20px; font-weight: 600;">Next 10 Bookings</span>
        </div>
        <table class="queue-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Time</th>
              <th>Patient</th>
              <th>Service</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($upcomingResult && $upcomingResult->num_rows > 0): ?>
              <?php while($row = $upcomingResult->fetch_assoc()): ?>
                <tr>
                  <td><strong><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></strong></td>
                  <td><span class="time-badge"><?php echo h(formatTime12Hour($row['appointment_time'])); ?></span></td>
                  <td><span style="font-weight: 600; color: #1e293b;"><?php echo h(($row['first_name'] ?? '') . " " . ($row['last_name'] ?? '')); ?></span></td>
                  <td style="color: #64748b;"><?php echo h($row['service_name'] ?? 'General Service'); ?></td>
                  <td><span class="status-pill <?php echo str_replace(' ', '-', strtolower($row['status'] ?? 'pending')); ?>"><?php echo h($row['status'] ?? 'Pending'); ?></span></td>
                  <td><a href="dentist_patient_view.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&id=<?php echo (int)$row['appointment_id']; ?>" style="color: var(--dashboard-accent); text-decoration: none; font-weight: 700; font-size: 12px; border: 1px solid var(--dashboard-accent); padding: 4px 10px; border-radius: 6px; transition: 0.2s;">View Case</a></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align:center; padding:40px; color: #94a3b8;">No upcoming appointments found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div id="calendar-tooltip" class="calendar-tooltip"></div>

  <script>
  <?php printDateClockScript(); ?>

  // Calendar Hover Interaction
  document.addEventListener('DOMContentLoaded', function() {
    const tooltip = document.getElementById('calendar-tooltip');
    const calendarDays = document.querySelectorAll('.calendar-table td.has-appt');

    calendarDays.forEach(day => {
      day.addEventListener('mouseenter', function(e) {
        const appts = JSON.parse(this.getAttribute('data-appts'));
        const dateLabel = this.getAttribute('data-date-label');
        
        let html = `<div class="tooltip-header">
                      <span>${dateLabel}</span>
                      <span style="background: var(--dashboard-accent); color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px;">${appts.length} Appts</span>
                    </div>`;
        
        appts.forEach(appt => {
          html += `<div class="tooltip-appt-item">
                     <span class="tooltip-time">${appt.time}</span>
                     <span class="tooltip-patient">${appt.patient}</span>
                     <span class="tooltip-service">${appt.service}</span>
                     <div style="margin-top: 4px; font-size: 10px; font-weight: 700; color: ${appt.status === 'Completed' ? '#10b981' : '#f59e0b'}">
                       ● ${appt.status}
                     </div>
                   </div>`;
        });

        tooltip.innerHTML = html;
        tooltip.style.display = 'block';
        
        // Positioning
        const rect = this.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        let top = rect.top + window.scrollY - tooltipRect.height - 10;
        let left = rect.left + window.scrollX + (rect.width / 2) - (tooltipRect.width / 2);

        // Boundary checks
        if (top < window.scrollY) {
          top = rect.bottom + window.scrollY + 10;
        }
        if (left < 10) left = 10;
        if (left + tooltipRect.width > window.innerWidth - 10) {
          left = window.innerWidth - tooltipRect.width - 10;
        }

        tooltip.style.top = `${top}px`;
        tooltip.style.left = `${left}px`;
        
        // Fade in
        setTimeout(() => tooltip.style.opacity = '1', 10);
      });

      day.addEventListener('mouseleave', function() {
        tooltip.style.opacity = '0';
        setTimeout(() => {
          if (tooltip.style.opacity === '0') {
            tooltip.style.display = 'none';
          }
        }, 200);
      });
    });
  });

  // Verification log
  console.log('UI Parity Active - Version 2.0');
  console.log('Dentist Dashboard Initialized');
  console.log('Calendar Tooltips Enabled');
  console.log('FINAL UI SYNC COMPLETE');
  console.log('Anti-Crash System Active - V2');
  </script>
</body>
</html>

