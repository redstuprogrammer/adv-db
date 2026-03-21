<?php
session_start();
require_once __DIR__ . '/security_headers.php';
require_once 'connect.php';
require_once 'tenant_utils.php';

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

      .module-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        gap: 20px;
      }

      .module-header h1 {
        font-size: 24px;
        font-weight: 900;
        color: var(--accent);
        margin: 0;
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
        padding: 4px 8px;
        margin-right: 4px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-size: 12px;
        color: var(--accent);
        transition: all 0.2s ease;
      }

      .action-btn:hover {
        background: var(--accent);
        color: white;
      }

      .breadcrumb {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 16px;
      }

      .breadcrumb a {
        color: var(--accent);
        text-decoration: none;
      }

      .breadcrumb a:hover {
        text-decoration: underline;
      }
    </style>
</head>
<body>
  <div class="t-wrap">
    <div style="width:100%;max-width:1200px;margin:0 auto;padding:20px;">
      
      <div class="breadcrumb">
        <a href="tenant_dashboard.php?tenant=<?php echo urlencode($tenantSlug); ?>">Dashboard</a> / Appointments
      </div>

      <div class="module-header">
        <div>
          <h1>📅 Appointments</h1>
          <p style="color: #64748b; margin: 8px 0 0 0; font-size: 14px;">Schedule and manage patient appointments</p>
        </div>
        <a href="#" class="btn-primary">+ Schedule Appointment</a>
      </div>

      <div class="module-card">
        <h2 style="margin-top: 0; color: var(--accent); font-size: 16px;">Upcoming Appointments</h2>
        
        <div class="filters">
          <input type="date" />
          <select>
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
                <a href="#" class="action-btn">Edit</a>
                <a href="#" class="action-btn">Cancel</a>
              </td>
            </tr>
            <tr>
              <td>Maria Santos</td>
              <td>Dr. John Smith</td>
              <td>2026-03-26 02:00 PM</td>
              <td><span class="badge badge-pending">Pending</span></td>
              <td>Cleaning</td>
              <td>
                <a href="#" class="action-btn">Edit</a>
                <a href="#" class="action-btn">Cancel</a>
              </td>
            </tr>
            <tr>
              <td>Pedro Reyes</td>
              <td>Dr. John Smith</td>
              <td>2026-03-27 11:30 AM</td>
              <td><span class="badge badge-confirmed">Confirmed</span></td>
              <td>Root Canal</td>
              <td>
                <a href="#" class="action-btn">Edit</a>
                <a href="#" class="action-btn">Cancel</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div style="margin-top: 32px; padding-top: 20px; border-top: 1px solid var(--border); text-align: right;">
        <a href="tenant_logout.php?tenant=<?php echo urlencode($tenantSlug); ?>" style="color: var(--accent); text-decoration: none; font-weight: 600; font-size: 13px;">Sign out</a>
      </div>

    </div>
  </div>
</body>
</html>
