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
          <a href="tenant_dashboard.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
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
          <a href="tenant_reports.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item active">
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
      <div class="tenant-header-bar">
        <div class="tenant-header-title">📊 Reports & Analytics</div>
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
      </div>

      <div class="module-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Activity Report</h2>
          <div style="display: flex; gap: 8px;">
            <button class="btn-primary" id="exportCsvBtn" type="button" onclick="exportCSV()">Export CSV</button>
            <button class="btn-primary" id="exportPdfBtn" type="button" onclick="exportPDF()">Export PDF</button>
          </div>
        </div>
        
        <div class="filters">
          <input type="date" id="date_from" placeholder="From" />
          <input type="date" id="date_to" placeholder="To" />
          <select id="activity_type">
            <option value="">All Activities</option>
            <option value="Appointment Scheduled">Appointments</option>
            <option value="Payment Recorded">Payments</option>
            <option value="Patient Created">Patients</option>
            <option value="User Login">Users</option>
          </select>
          <button type="button" onclick="applyFilters();">Apply Filter</button>
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

  <script>
    let tenantReportData = [];

    function isValidTenantDateRange() {
      const dateFrom = document.getElementById('date_from').value;
      const dateTo = document.getElementById('date_to').value;
      if (dateFrom && dateTo && dateTo < dateFrom) {
        alert('End date cannot be earlier than start date.');
        return false;
      }
      return true;
    }

    function applyFilters() {
      if (!isValidTenantDateRange()) return;

      const dateFrom = document.getElementById('date_from').value;
      const dateTo = document.getElementById('date_to').value;
      const activityType = document.getElementById('activity_type').value;

      fetch(`get_filtered_reports.php?type=tenant_activity&date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}&activity_type=${encodeURIComponent(activityType)}`)
        .then(resp => resp.json())
        .then(data => {
          if (data.success) {
            tenantReportData = data.data;
            renderTenantReport(tenantReportData);
          } else {
            alert('Report failed: ' + data.error);
          }
        })
        .catch(err => {
          console.error(err);
          alert('Unable to run report at this time.');
        });
    }

    function renderTenantReport(rows) {
      const container = document.getElementById('report-results');
      if (!rows || rows.length === 0) {
        container.innerHTML = '<p style="color: #a855f7;">No matching records. Try a different date range or activity type.</p>';
        return;
      }

      let totalCount = rows.reduce((sum, r) => sum + (Number(r.Count) || 0), 0);
      const summaryHtml = `
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 12px; border-radius: 8px; margin-bottom: 12px; display:flex; gap:16px; flex-wrap: wrap;">
          <div><strong>Rows</strong><br>${rows.length}</div>
          <div><strong>Total Units</strong><br>${totalCount}</div>
          <div><strong>Filtered by</strong><br>${document.getElementById('activity_type').value || 'All Activities'}</div>
        </div>`;

      let html = summaryHtml + '<table class="module-table"><thead><tr>';
      Object.keys(rows[0]).forEach(k => html += `<th>${k}</th>`);
      html += '</tr></thead><tbody>';
      rows.forEach(row => {
        html += '<tr>';
        Object.values(row).forEach(val => html += `<td>${val}</td>`);
        html += '</tr>';
      });
      html += '</tbody></table>';
      container.innerHTML = html;
    }

    function exportCSV() {
      if (!tenantReportData || tenantReportData.length === 0) {
        alert('Please run a report first.');
        return;
      }

      const csv = [Object.keys(tenantReportData[0]).join(','), ...tenantReportData.map(r => Object.values(r).map(v => `"${v}"`).join(','))].join('\n');
      const blob = new Blob([csv], { type: 'text/csv' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'tenant_report.csv';
      a.click();
      URL.revokeObjectURL(url);
    }

    function exportPDF() {
      if (!tenantReportData || tenantReportData.length === 0) {
        alert('Please run a report first.');
        return;
      }

      fetch('generate_pdf.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ data: tenantReportData, title: 'Tenant Activity Report' })
      }).then(response => {
        if (!response.ok) throw new Error('PDF generation failed');
        return response.blob();
      }).then(blob => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'tenant_report.pdf';
        a.click();
        URL.revokeObjectURL(url);
      }).catch(error => {
        console.error(error);
        alert('Unable to export PDF.');
      });
    }

    document.addEventListener('DOMContentLoaded', function() {
      applyFilters();
    });
  </script>
</body>
</html>
