<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Appointments</title>
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

      .module-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
      }

      .filters {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
      }

      .filters input, .filters select {
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 13px;
      }

      .filters select {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

      .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
      }

      .badge-confirmed { background: rgba(16, 185, 129, 0.1); color: #10b981; }
      .badge-pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
      .badge-cancelled { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

      .action-btn {
        display: inline-block;
        padding: 8px 12px;
        margin-right: 4px;
        background: var(--accent);
        border: 1px solid var(--accent);
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-size: 12px;
        color: white;
        font-weight: 600;
        transition: all 0.2s ease;
      }

      .action-btn:hover {
        background: #0a2d4f;
        border-color: #0a2d4f;
      }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">📅 Appointments</div>
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
      </div>

      <div class="module-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Upcoming Appointments</h2>
          <a href="#" class="btn-primary" onclick="alert('Schedule Appointment functionality coming soon!'); return false;">+ Schedule Appointment</a>
        </div>
        
        <div class="filters">
          <input type="date" onchange="alert('Filter functionality coming soon!');" />
          <select onchange="alert('Filter functionality coming soon!');">
            <option>All Status</option>
            <option>Confirmed</option>
            <option>Pending</option>
            <option>Cancelled</option>
          </select>
        </div>

        <table class="module-table">
          <thead>
            <tr>
              <th>Patient</th>
              <th>Dentist</th>
              <th>Date & Time</th>
              <th>Status</th>
              <th>Service</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Juan Dela Cruz</td>
              <td>Dr. John Smith</td>
              <td>2026-03-25 10:00 AM</td>
              <td><span class="badge badge-confirmed">Confirmed</span></td>
              <td>Regular Checkup</td>
              <td>
                <a href="#" class="action-btn" onclick="alert('Edit appointment - coming soon'); return false;">Edit</a>
                <a href="#" class="action-btn" onclick="alert('Cancel appointment - coming soon'); return false;">Cancel</a>
              </td>
            </tr>
            <tr>
              <td>Maria Santos</td>
              <td>Dr. John Smith</td>
              <td>2026-03-26 02:00 PM</td>
              <td><span class="badge badge-pending">Pending</span></td>
              <td>Cleaning</td>
              <td>
                <a href="#" class="action-btn" onclick="alert('Edit appointment - coming soon'); return false;">Edit</a>
                <a href="#" class="action-btn" onclick="alert('Cancel appointment - coming soon'); return false;">Cancel</a>
              </td>
            </tr>
            <tr>
              <td>Pedro Reyes</td>
              <td>Dr. John Smith</td>
              <td>2026-03-27 11:30 AM</td>
              <td><span class="badge badge-confirmed">Confirmed</span></td>
              <td>Root Canal</td>
              <td>
                <a href="#" class="action-btn" onclick="alert('Edit appointment - coming soon'); return false;">Edit</a>
                <a href="#" class="action-btn" onclick="alert('Cancel appointment - coming soon'); return false;">Cancel</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
