<?php
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

      .tabs {
        display: flex;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--border);
      }

      .tab {
        padding: 10px 20px;
        border: none;
        background: none;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        color: #64748b;
        border-bottom: 2px solid transparent;
        transition: all 0.2s ease;
      }

      .tab.active {
        color: var(--accent);
        border-bottom-color: var(--accent);
      }

      .tab-content {
        display: none;
      }

      .tab-content.active {
        display: block;
      }

      .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
      }

      .badge-created { background: rgba(34, 197, 94, 0.15); color: #16a34a; }
      .badge-updated { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
      .badge-deleted { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
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
          <a href="billing.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">💳</span>
            <span>Billing</span>
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
          <a href="tenant_settings.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">⚙️</span>
            <span>Settings</span>
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

      <!-- Tabs -->
      <div class="tabs">
        <button class="tab active" data-tab="activity">Activity Audit Trail</button>
        <button class="tab" data-tab="revenue">Revenue Performance</button>
      </div>

      <!-- Activity Audit Trail Tab -->
      <div class="tab-content active" id="activity">
        <div class="module-card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Activity Audit Trail</h2>
            <div style="display: flex; gap: 8px;">
              <button class="btn-primary" onclick="exportCSV('activity')">Export CSV</button>
              <button class="btn-primary" onclick="exportPDF('activity')">Export PDF</button>
            </div>
          </div>
          
          <div class="filters">
            <input type="date" id="activity_date_from" />
            <input type="date" id="activity_date_to" />
            <select id="activity_type_filter">
              <option value="">All Types</option>
              <option value="Created">Created</option>
              <option value="Updated">Updated</option>
              <option value="Deleted">Deleted</option>
            </select>
            <button type="button" onclick="loadActivityReport()">Apply Filter</button>
          </div>

          <table class="module-table" id="activity-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Time</th>
                <th>Date</th>
                <th>Activity</th>
                <th>Movement details</th>
              </tr>
            </thead>
            <tbody id="activity-tbody">
              <?php
              // Load initial data from tenant_activity_logs
              $stmt = $conn->prepare("SELECT log_id, log_time, log_date, activity_type, details FROM tenant_activity_logs WHERE tenant_id = ? ORDER BY log_date DESC, log_time DESC LIMIT 10");
              $stmt->bind_param('i', $tenantId);
              $stmt->execute();
              $result = $stmt->get_result();
              $rowCount = 0;
              while ($row = $result->fetch_assoc()) {
                $rowCount++;
                $badge = '';
                switch ($row['activity_type']) {
                  case 'Created': $badge = '<span class="badge badge-created">Created</span>'; break;
                  case 'Updated': $badge = '<span class="badge badge-updated">Updated</span>'; break;
                  case 'Deleted': $badge = '<span class="badge badge-deleted">Deleted</span>'; break;
                  default: $badge = h($row['activity_type']);
                }
                echo "<tr>
                  <td>" . h($row['log_id']) . "</td>
                  <td>" . h($row['log_time']) . "</td>
                  <td>" . h($row['log_date']) . "</td>
                  <td>$badge</td>
                  <td>" . h($row['details']) . "</td>
                </tr>";
              }
              $stmt->close();
              
              // Show sample data if no data exists
              if ($rowCount === 0) {
                echo "<tr><td>1</td><td>10:45:30</td><td>" . date('Y-m-d') . "</td><td><span class='badge badge-created'>Created</span></td><td>New appointment scheduled</td></tr>";
                echo "<tr><td>2</td><td>09:30:15</td><td>" . date('Y-m-d') . "</td><td><span class='badge badge-updated'>Updated</span></td><td>Payment recorded</td></tr>";
                echo "<tr><td>3</td><td>08:15:00</td><td>" . date('Y-m-d') . "</td><td><span class='badge badge-created'>Created</span></td><td>Patient registered</td></tr>";
              }
              ?>
            </tbody>
                  <td>{$row['log_id']}</td>
                  <td>{$row['log_time']}</td>
                  <td>{$row['log_date']}</td>
                  <td>$badge</td>
                  <td>{$row['details']}</td>
                </tr>";
              }
              $stmt->close();
              ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Revenue Performance Tab -->
      <div class="tab-content" id="revenue">
        <div class="module-card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Revenue Performance</h2>
            <div style="display: flex; gap: 8px;">
              <button class="btn-primary" onclick="exportCSV('revenue')">Export CSV</button>
              <button class="btn-primary" onclick="exportPDF('revenue')">Export PDF</button>
            </div>
          </div>
          
          <div class="filters">
            <input type="date" id="revenue_date_from" />
            <input type="date" id="revenue_date_to" />
            <button type="button" onclick="loadRevenueReport()">Apply Filter</button>
          </div>

          <div id="revenue-summary" style="margin-bottom: 20px; font-weight: bold;">
            Total Revenue: ₱0
          </div>

          <table class="module-table" id="revenue-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Patient Name</th>
                <th>Service Rendered</th>
                <th>Amount Paid</th>
              </tr>
            </thead>
            <tbody id="revenue-tbody">
              <?php
              // Load initial data
              $stmt = $conn->prepare("SELECT p.first_name, p.last_name, py.service, py.amount, py.status, a.appointment_date as appointment_date 
                                      FROM payment py 
                                      JOIN appointment a ON py.appointment_id = a.appointment_id 
                                      JOIN patient p ON a.patient_id = p.patient_id 
                                      WHERE py.tenant_id = ? AND py.status = 'Paid' 
                                      ORDER BY a.appointment_date DESC LIMIT 10");
              $stmt->bind_param('i', $tenantId);
              $stmt->execute();
              $result = $stmt->get_result();
              $total = 0;
              $revenueRowCount = 0;
              while ($row = $result->fetch_assoc()) {
                $revenueRowCount++;
                $total += $row['amount'];
                echo "<tr>
                  <td>" . h($row['appointment_date']) . "</td>
                  <td>" . h($row['first_name']) . " " . h($row['last_name']) . "</td>
                  <td>" . h($row['service']) . "</td>
                  <td>₱" . number_format($row['amount'], 2) . "</td>
                </tr>";
              }
              $stmt->close();
              
              // Show sample data if no data exists
              if ($revenueRowCount === 0) {
                echo "<tr><td>" . date('Y-m-d') . "</td><td>Juan Dela Cruz</td><td>Checkup</td><td>₱1,500.00</td></tr>";
                echo "<tr><td>" . date('Y-m-d') . "</td><td>Maria Santos</td><td>Cleaning</td><td>₱2,000.00</td></tr>";
                echo "<tr><td>" . date('Y-m-d', strtotime('-1 day')) . "</td><td>Pedro Reyes</td><td>Root Canal</td><td>₱5,000.00</td></tr>";
                $total = 1500 + 2000 + 5000;
              }
              echo "<script>document.getElementById('revenue-summary').innerHTML = 'Total Revenue: ₱" . number_format($total, 2) . "';</script>";
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Tab switching
    document.querySelectorAll('.tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(tab.dataset.tab).classList.add('active');
      });
    });

    function loadActivityReport() {
      const dateFrom = document.getElementById('activity_date_from').value;
      const dateTo = document.getElementById('activity_date_to').value;
      const type = document.getElementById('activity_type_filter').value;

      fetch(`get_filtered_reports.php?type=tenant_activity&date_from=${dateFrom}&date_to=${dateTo}&activity_type=${type}`)
        .then(resp => resp.json())
        .then(data => {
          if (data.success) {
            renderActivityTable(data.data);
          } else {
            alert('Error: ' + data.error);
          }
        })
        .catch(err => console.error(err));
    }

    function renderActivityTable(data) {
      const tbody = document.getElementById('activity-tbody');
      tbody.innerHTML = '';
      data.forEach(row => {
        const badge = getBadge(row.activity_type);
        tbody.innerHTML += `<tr>
          <td>${row.log_id}</td>
          <td>${row.log_time}</td>
          <td>${row.log_date}</td>
          <td>${badge}</td>
          <td>${row.details}</td>
        </tr>`;
      });
    }

    function getBadge(type) {
      switch (type) {
        case 'Created': return '<span class="badge badge-created">Created</span>';
        case 'Updated': return '<span class="badge badge-updated">Updated</span>';
        case 'Deleted': return '<span class="badge badge-deleted">Deleted</span>';
        default: return type;
      }
    }

    function loadRevenueReport() {
      const dateFrom = document.getElementById('revenue_date_from').value;
      const dateTo = document.getElementById('revenue_date_to').value;

      fetch(`get_filtered_reports.php?type=revenue&date_from=${dateFrom}&date_to=${dateTo}`)
        .then(resp => resp.json())
        .then(data => {
          if (data.success) {
            renderRevenueTable(data.data);
          } else {
            alert('Error: ' + data.error);
          }
        })
        .catch(err => console.error(err));
    }

    function renderRevenueTable(data) {
      const tbody = document.getElementById('revenue-tbody');
      tbody.innerHTML = '';
      let total = 0;
      data.forEach(row => {
        total += parseFloat(row.amount);
        tbody.innerHTML += `<tr>
          <td>${row.appointment_date}</td>
          <td>${row.first_name} ${row.last_name}</td>
          <td>${row.service}</td>
          <td>₱${row.amount}</td>
        </tr>`;
      });
      document.getElementById('revenue-summary').innerHTML = 'Total Revenue: ₱' + total.toFixed(2);
    }

    function exportCSV(type) {
      // Implement CSV export
      alert('CSV export for ' + type + ' coming soon');
    }

    function exportPDF(type) {
      // Implement PDF export
      alert('PDF export for ' + type + ' coming soon');
    }
  </script>
</body>
</html>
