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
    <title><?php echo h($tenantName); ?> | Reports</title>
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
        flex-wrap: wrap;
      }

      .filters input, .filters select, .filters button {
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 13px;
        cursor: pointer;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      }

      .filters button {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
        font-weight: 600;
        transition: background 0.2s ease;
      }

      .filters button:hover {
        background: #0a2d4f;
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
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">📊 Reports & Analytics</div>
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
      </div>

      <div class="module-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Activity Report</h2>
          <a href="#" class="btn-primary" onclick="alert('Export Report functionality coming soon!'); return false;">Export Report</a>
        </div>
        
        <div class="filters">
          <input type="date" placeholder="From" onchange="alert('Filter functionality coming soon!');" />
          <input type="date" placeholder="To" onchange="alert('Filter functionality coming soon!');" />
          <select onchange="alert('Filter functionality coming soon!');">
            <option>All Activities</option>
            <option>Appointments</option>
            <option>Payments</option>
            <option>Patients</option>
            <option>Users</option>
          </select>
          <button onclick="alert('Filter functionality coming soon!'); return false;">Apply Filter</button>
        </div>

        <table class="module-table">
          <thead>
            <tr>
              <th>Date & Time</th>
              <th>Activity Type</th>
              <th>Description</th>
              <th>User</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>2026-03-20 10:45 AM</td>
              <td>Appointment Created</td>
              <td>New appointment scheduled</td>
              <td>admin</td>
              <td>Patient: Juan Dela Cruz</td>
            </tr>
            <tr>
              <td>2026-03-20 09:30 AM</td>
              <td>Payment Recorded</td>
              <td>Payment collected</td>
              <td>admin</td>
              <td>Amount: ₱5,000</td>
            </tr>
            <tr>
              <td>2026-03-19 03:15 PM</td>
              <td>Patient Updated</td>
              <td>Contact information updated</td>
              <td>receptionist</td>
              <td>Patient: Maria Santos</td>
            </tr>
            <tr>
              <td>2026-03-19 02:00 PM</td>
              <td>Appointment Confirmed</td>
              <td>Appointment status changed</td>
              <td>admin</td>
              <td>Patient: Pedro Reyes</td>
            </tr>
            <tr>
              <td>2026-03-18 11:20 AM</td>
              <td>User Login</td>
              <td>Admin login</td>
              <td>admin</td>
              <td>IP: 192.168.1.100</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
