<?php
require_once __DIR__ . '/includes/session_config.php';
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

// 1. Waiting/Pending (Pending appointments from today onwards)
$pendingCount = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM appointment WHERE tenant_id = ? AND appointment_date >= ? AND status = 'Pending'");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "is", $tenantId, $todayDate);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $pendingCount = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

// 2. Check-outs Done (Paid invoices today)
$completedCount = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM billing WHERE tenant_id = ? AND DATE(billing_date) = ? AND payment_status = 'paid'");
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
   ARRIVAL LIST (QUEUE) - Upcoming
   ========================= */
$queueResult = null;
$stmt = mysqli_prepare($conn, "SELECT a.appointment_id, p.first_name, p.last_name, d.last_name AS d_last, a.status, a.appointment_date, a.appointment_time 
               FROM appointment a 
               JOIN patient p ON a.patient_id = p.patient_id 
               JOIN users d ON a.dentist_id = d.user_id
               WHERE a.tenant_id = ? AND a.appointment_date = ? AND a.status = 'Pending'
               ORDER BY a.appointment_time ASC, a.appointment_id ASC
               LIMIT 8");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "is", $tenantId, $todayDate);
    mysqli_stmt_execute($stmt);
    $queueResult = mysqli_stmt_get_result($stmt);
}

/* =========================
   CALENDAR LOGIC (Clinic Wide)
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
                  WHERE a.tenant_id = ? AND MONTH(a.appointment_date) = ? AND YEAR(a.appointment_date) = ? AND a.status != 'Cancelled'
                  ORDER BY a.appointment_time ASC");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "iii", $tenantId, $month, $year);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $date = $row['appointment_date'];
            if (!isset($calendarDetails[$date])) {
                $calendarDetails[$date] = [];
            }
            $calendarDetails[$date][] = [
                'time' => date('h:i A', strtotime($row['appointment_time'])),
                'patient' => ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''),
                'service' => $row['service_name'] ?? '', // Removed General Service
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

      .status-pill.pending { background: #fff3cd; color: #856404; font-size: 10px; padding: 2px 8px; border-radius: 12px; font-weight: bold; text-transform: uppercase; }
      .status-pill.in-progress { background: #e0f2fe; color: #0369a1; font-size: 10px; padding: 2px 8px; border-radius: 12px; font-weight: bold; text-transform: uppercase; }
      .status-pill.completed { background: #dcfce7; color: #166534; font-size: 10px; padding: 2px 8px; border-radius: 12px; font-weight: bold; text-transform: uppercase; }
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

      .calendar-section {
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

      .cal-nav { display: flex; justify-content: space-between; align-items: center; padding: 10px; background: var(--dashboard-bg); border-radius: 8px; margin-bottom: 10px; }
      .cal-nav a { text-decoration: none; color: var(--dashboard-accent); font-weight: bold; font-size: 18px; }

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
        <div class="tenant-header-title">
            <?php echo h($tenantName); ?> Front Desk
            <span style="margin-left: 10px; font-size: 14px; background: #e2e8f0; color: #475569; padding: 4px 12px; border-radius: 20px; font-weight: 700; letter-spacing: 0.5px;">
                Code: <?php echo h($sessionManager->getTenantData()['tenant_code'] ?? 'N/A'); ?>
            </span>
        </div>
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

      <!-- Announcements Feed Widget -->
      <?php
      $announcements = [];
      $stmt = mysqli_prepare($conn, "SELECT tenant_id, title, content, category, publish_date FROM announcements WHERE (tenant_id = ? OR tenant_id IS NULL) AND status = 'active' AND publish_date <= NOW() ORDER BY publish_date DESC, id DESC LIMIT 5");
      if ($stmt) {
          mysqli_stmt_bind_param($stmt, "i", $tenantId);
          mysqli_stmt_execute($stmt);
          $res = mysqli_stmt_get_result($stmt);
          if ($res) {
              while ($row = mysqli_fetch_assoc($res)) {
                  $announcements[] = $row;
              }
          }
          mysqli_stmt_close($stmt);
      }
      ?>
      <?php if (!empty($announcements)): ?>
        <div class="announcements-widget" style="background: white; border: 1px solid var(--dashboard-border); border-radius: 12px; padding: 24px; margin-bottom: 32px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);">
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; border-bottom: 1.5px solid var(--dashboard-border); padding-bottom: 12px;">
            <span style="font-size: 20px;">📢</span>
            <h2 style="margin: 0; font-size: 18px; font-weight: 800; color: var(--dashboard-accent);">Clinic Announcements</h2>
          </div>
          <div style="display: flex; flex-direction: column; gap: 16px;">
            <?php foreach ($announcements as $ann): ?>
              <?php
              $isPlatform = is_null($ann['tenant_id']);
              $authorLabel = $isPlatform ? "Platform Update" : "Clinic Announcement";
              $authorBadgeBg = $isPlatform ? "#ffe4e6" : "#f1f5f9";
              $authorBadgeColor = $isPlatform ? "#be123c" : "#475569";
              ?>
              <div style="padding: 16px; border-radius: 10px; background: #f8fafc; border-left: 4px solid <?php
                $cat = strtolower($ann['category']);
                if (strpos($cat, 'clinical') !== false || strpos($cat, 'update') !== false) {
                    echo '#0369a1';
                } elseif (strpos($cat, 'patient') !== false || strpos($cat, 'care') !== false) {
                    echo '#166534';
                } elseif (strpos($cat, 'facility') !== false || strpos($cat, 'news') !== false || strpos($cat, 'maintenance') !== false) {
                    echo '#d97706';
                } elseif (strpos($cat, 'system') !== false || strpos($cat, 'platform') !== false || $isPlatform) {
                    echo '#be123c';
                } else {
                    echo '#7e22ce';
                }
              ?>; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: default;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)';" onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 8px;">
                  <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                    <!-- Category Badge -->
                    <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 3px 8px; border-radius: 6px; <?php
                      $cat = strtolower($ann['category']);
                      if (strpos($cat, 'clinical') !== false || strpos($cat, 'update') !== false) {
                          echo 'background: #e0f2fe; color: #0369a1;';
                      } elseif (strpos($cat, 'patient') !== false || strpos($cat, 'care') !== false) {
                          echo 'background: #dcfce7; color: #166534;';
                      } elseif (strpos($cat, 'facility') !== false || strpos($cat, 'news') !== false || strpos($cat, 'maintenance') !== false) {
                          echo 'background: #fef3c7; color: #d97706;';
                      } elseif (strpos($cat, 'system') !== false || strpos($cat, 'platform') !== false || $isPlatform) {
                          echo 'background: #ffe4e6; color: #be123c;';
                      } else {
                          echo 'background: #f3e8ff; color: #7e22ce;';
                      }
                    ?>">
                      <?php echo h($ann['category']); ?>
                    </span>
                    <!-- Origin Badge -->
                    <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 3px 8px; border-radius: 6px; background: <?php echo $authorBadgeBg; ?>; color: <?php echo $authorBadgeColor; ?>;">
                      <?php echo $authorLabel; ?>
                    </span>
                  </div>
                  <span style="font-size: 12px; color: #64748b; font-weight: 500;"><?php echo date('M d, Y g:i A', strtotime($ann['publish_date'])); ?></span>
                </div>
                <strong style="color: var(--dashboard-accent); font-size: 15px; display: block; margin-bottom: 6px;"><?php echo h($ann['title']); ?></strong>
                <p style="margin: 0; color: #475569; font-size: 13.5px; line-height: 1.5; white-space: pre-line;"><?php echo h($ann['content']); ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

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

      <!-- Calendar Section -->
      <div class="calendar-section">
        <h2 style="margin-bottom: 16px; color: var(--dashboard-accent);">Clinic Calendar Overview</h2>
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
              <th>Time</th>
              <th>Dentist</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($queueResult && $queueResult->num_rows > 0): ?>
              <?php while($row = $queueResult->fetch_assoc()): ?>
                <tr>
                  <td><strong><?php echo h($row['first_name'] . " " . $row['last_name']); ?></strong></td>
                  <td><span class="time-badge"><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></span></td>
                  <td>Dr. <?php echo h($row['d_last']); ?></td>
                  <td><span class="status-pill <?php echo str_replace(' ', '-', strtolower($row['status'])); ?>"><?php echo h($row['status']); ?></span></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5" style="text-align:center; padding:20px;">No pending patients scheduled for today.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div id="calendar-tooltip" class="calendar-tooltip"></div>

  <script>
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
                     ${appt.service ? `<span class="tooltip-service">${appt.service}</span>` : ''}
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

  // Verification logs
  console.log('Receptionist Dashboard Initialized with Calendar');
  </script>
</body>
</html>

