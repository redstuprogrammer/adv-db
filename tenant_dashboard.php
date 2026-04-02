<?php
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
error_log("tenant_dashboard.php accessed with tenant: " . $tenantSlug);
requireTenantLogin($tenantSlug);

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();

// Fetch metrics for dashboard
$patientCount = getTenantPatientCount($tenantId) ?? 0;
$appointmentCount = getTenantUpcomingAppointmentCount($tenantId) ?? 0;
$outstandingInvoices = getTenantOutstandingInvoiceCount($tenantId) ?? 0;
$todayRevenue = getTenantTodayRevenue($tenantId) ?? 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Dashboard</title>
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
        color: var(--dashboard-accent);
        font-weight: 600;
        font-size: 13px;
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

      .modules-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
      }

      .module-card {
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        padding: 20px;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s ease;
      }

      .module-card:hover {
        border-color: var(--dashboard-accent);
        box-shadow: 0 8px 20px rgba(13, 59, 102, 0.12);
      }

      .module-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--dashboard-accent);
        margin-bottom: 6px;
      }

      .module-desc {
        font-size: 12px;
        color: #64748b;
        line-height: 1.5;
      }

      .footer-action {
        color: var(--dashboard-accent);
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
      }

      .footer-action:hover {
        text-decoration: underline;
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

        .modules-grid {
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
          <div class="sidebar-section-title">Main</div>
          <a href="tenant_dashboard.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item active">
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
        </div>

        <div class="sidebar-section">
          <div class="sidebar-section-title">Management</div>
          <a href="manage_users.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">👤</span>
            <span>Staff & Users</span>
          </a>
          <a href="tenant_reports.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">📈</span>
            <span>Reports</span>
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
      <!-- Header Bar -->
      <div class="tenant-header-bar">
        <div class="tenant-header-title"><?php echo h($tenantName); ?> Dashboard</div>
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
      </div>

      <!-- Dashboard Content -->

      <!-- Stats Cards -->
      <div class="dashboard-stats">
        <div class="stat-card">
          <div class="stat-icon icon-blue">👥</div>
          <div class="stat-label">Total Patients</div>
          <div class="stat-value"><?php echo $patientCount; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon icon-green">📅</div>
          <div class="stat-label">Upcoming Appointments</div>
          <div class="stat-value"><?php echo $appointmentCount; ?></div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="actions-grid">
          <a href="patients.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="action-btn">
            <span class="action-icon">➕</span>
            <span>Add Patient</span>
          </a>
          <a href="appointments.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="action-btn">
            <span class="action-icon">📅</span>
            <span>Schedule Appointment</span>
          </a>
          <a href="manage_users.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="action-btn">
            <span class="action-icon">👤</span>
            <span>Manage Staff & Users</span>
          </a>
        </div>
      </div>

      <!-- Calendar and Today's Schedule -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
        <!-- Calendar -->
        <div style="background: white; border: 1px solid var(--dashboard-border); border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
            <button onclick="prevMonth()" style="background: none; border: none; font-size: 20px; cursor: pointer;">←</button>
            <h3 id="monthLabel" style="margin: 0; font-size: 16px; font-weight: 700; color: var(--dashboard-accent); flex: 1; text-align: center;">March 2026</h3>
            <button onclick="nextMonth()" style="background: none; border: none; font-size: 20px; cursor: pointer;">→</button>
          </div>
          <div id="calendar" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px;"></div>
        </div>

        <!-- Today's Schedule -->
        <div style="background: white; border: 1px solid var(--dashboard-border); border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);">
          <h3 style="margin-top: 0; margin-bottom: 12px; font-size: 15px; font-weight: 700; color: var(--dashboard-accent);">Today's Schedule</h3>
          <div id="todayDate" style="font-size: 12px; color: #64748b; margin-bottom: 16px;">Saturday, March 06, 2026</div>
          <div id="todayAppointments" style="display: flex; flex-direction: column; gap: 12px;">
            <div style="padding: 12px; background: var(--dashboard-bg); border-radius: 8px; border-left: 4px solid var(--dashboard-accent); font-size: 13px; color: #64748b;">
              No appointments for today
            </div>
          </div>
        </div>
      </div>

      <!-- Core Modules -->
      <div style="margin-bottom: 32px;">
        <h2 style="font-size: 16px; font-weight: 700; color: var(--dashboard-accent); margin-bottom: 16px;">Core Modules</h2>
        <div class="modules-grid">
lass="footer-action" href="tenant_logout.php?tenant=<?php echo urlencode($tenantSlug); ?>">Sign out</a>
      </div>

    </div>
    </div>
  </div>

  <script>
    let currentDate = new Date(2026, 2, 6); // March 6, 2026

    function renderCalendar() {
      const year = currentDate.getFullYear();
      const month = currentDate.getMonth();
      
      // Update month label
      const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                         'July', 'August', 'September', 'October', 'November', 'December'];
      document.getElementById('monthLabel').textContent = monthNames[month] + ' ' + year;
      
      // Clear calendar
      const calendarDiv = document.getElementById('calendar');
      calendarDiv.innerHTML = '';
      
      // Add day headers
      const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
      dayHeaders.forEach(day => {
        const header = document.createElement('div');
        header.textContent = day;
        header.style.cssText = 'text-align: center; font-weight: 700; color: var(--dashboard-accent); font-size: 12px; padding: 8px; border-bottom: 1px solid var(--dashboard-border); grid-column: 1 / -1;';
        calendarDiv.appendChild(header);
      });
      
      // Get first day of month and number of days
      const firstDay = new Date(year, month, 1).getDay();
      const daysInMonth = new Date(year, month + 1, 0).getDate();
      const today = new Date(2026, 2, 6);
      
      // Add empty cells for days before month starts
      for (let i = 0; i < firstDay; i++) {
        const emptyCell = document.createElement('div');
        emptyCell.style.cssText = 'padding: 8px; text-align: center; color: #cbd5e1; font-size: 12px;';
        calendarDiv.appendChild(emptyCell);
      }
      
      // Add day cells
      for (let day = 1; day <= daysInMonth; day++) {
        const dayCell = document.createElement('div');
        const cellDate = new Date(year, month, day);
        const isToday = cellDate.toDateString() === today.toDateString();
        
        dayCell.textContent = day;
        dayCell.style.cssText = 'padding: 8px; text-align: center; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; ' +
                               (isToday ? 'background: var(--dashboard-accent); color: white;' : 'color: var(--dashboard-accent); border: 1px solid var(--dashboard-border);');
        calendarDiv.appendChild(dayCell);
      }
      
      // Update today's date display
      const today = new Date(2026, 2, 6);
      const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
      const monthNames2 = ['January', 'February', 'March', 'April', 'May', 'June',
                          'July', 'August', 'September', 'October', 'November', 'December'];
      const dateStr = dayNames[today.getDay()] + ', ' + monthNames2[today.getMonth()] + ' ' + String(today.getDate()).padStart(2, '0') + ', ' + today.getFullYear();
      document.getElementById('todayDate').textContent = dateStr;
    }

    function prevMonth() {
      currentDate.setMonth(currentDate.getMonth() - 1);
      renderCalendar();
    }

    function nextMonth() {
      currentDate.setMonth(currentDate.getMonth() + 1);
      renderCalendar();
    }

    // Initialize calendar on page load
    renderCalendar();
  </script>
</body>
</html>

