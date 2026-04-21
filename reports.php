<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';
require_once __DIR__ . '/includes/custom_modal.php';

// Role Check Implementation - Ensure user is logged in
if (!isset($_SESSION['role'])) {
    header("Location: tenant_login.php");
    exit();
}

// Role Check Implementation - Ensure user is an Admin
if ($_SESSION['role'] !== 'Admin') {
    header("Location: tenant_login.php");
    exit();
}

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">📊 Reports & Analytics</div>
        <?php renderDateClock(); ?>
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
            <div style="color: #64748b; font-size: 13px;">Latest clinic activity is displayed here for review.</div>
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
              $stmt = $conn->prepare("SELECT log_id, log_time, log_date, activity_type, activity_description FROM tenant_activity_logs WHERE tenant_id = ? ORDER BY log_date DESC, log_time DESC LIMIT 10");
              $stmt->bind_param('i', $tenantId);
              $stmt->execute();
              $result = $stmt->get_result();
              $rowCount = 0;
              while ($row = $result->fetch_assoc()) {
                $rowCount++;
                $badge = '';
                $movementDetail = h($row['activity_description'] ?? $row['activity_type']);
                
                // Determine badge and movement detail based on activity type
                switch ($row['activity_type']) {
                  case 'Created': 
                    $badge = '<span class="badge badge-created">Created</span>'; 
                    break;
                  case 'Updated': 
                    $badge = '<span class="badge badge-updated">Updated</span>'; 
                    break;
                  case 'Deleted': 
                    $badge = '<span class="badge badge-deleted">Deleted</span>'; 
                    break;
                  case 'Admin Login':
                    $badge = '<span class="badge badge-created">Admin Login</span>';
                    $movementDetail = '👤 Admin Login';
                    break;
                  case 'Admin Logout':
                    $badge = '<span class="badge badge-deleted">Admin Logout</span>';
                    $movementDetail = '👤 Admin Logout';
                    break;
                  default: 
                    $badge = h($row['activity_type']);
                }
                
                echo "<tr>
                  <td>" . h($row['log_id']) . "</td>
                  <td>" . date('h:i A', strtotime($row['log_time'])) . "</td>
                  <td>" . h($row['log_date']) . "</td>
                  <td>$badge</td>
                  <td>$movementDetail</td>
                </tr>";
              }
              $stmt->close();
              
              // Show sample data if no data exists
              if ($rowCount === 0) {
                echo "<tr><td>1</td><td>10:45 AM</td><td>" . date('Y-m-d') . "</td><td><span class='badge badge-created'>Admin Login</span></td><td>👤 Admin Login</td></tr>";
                echo "<tr><td>2</td><td>09:30:15</td><td>" . date('Y-m-d') . "</td><td><span class='badge badge-updated'>Updated</span></td><td>Payment recorded</td></tr>";
                echo "<tr><td>3</td><td>08:15:00</td><td>" . date('Y-m-d') . "</td><td><span class='badge badge-created'>Created</span></td><td>Patient registered</td></tr>";
              }
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
            <div style="color: #64748b; font-size: 13px;">Revenue trends and payment status are tracked here.</div>
          </div>
          
          <div class="filters">
            <input type="date" id="revenue_date_from" />
            <input type="date" id="revenue_date_to" />
          </div>

          <div id="revenue-summary" style="margin-bottom: 20px; font-weight: bold;">
            Total Revenue: ₱0
          </div>

          <canvas id="revenueChart" width="400" height="200" style="margin-bottom: 20px;"></canvas>

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
              $stmt = $conn->prepare("SELECT p.first_name, p.last_name, COALESCE(s.service_name, 'General Service') AS service, py.amount, py.status, a.appointment_date as appointment_date 
                                      FROM payment py 
                                      LEFT JOIN appointment a ON py.appointment_id = a.appointment_id 
                                      LEFT JOIN patient p ON a.patient_id = p.patient_id 
                                      LEFT JOIN service s ON a.service_id = s.service_id AND s.tenant_id = py.tenant_id
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
              
              if ($revenueRowCount === 0) {
                echo "<tr><td colspan='4' style='text-align:center; color:#64748b;'>No subscription revenue records are available yet.</td></tr>";
              }
              echo "<script>document.getElementById('revenue-summary').innerHTML = 'Total Revenue: ₱" . number_format($total, 2) . "';</script>";
              // Revenue chart data - last 12 months
              $chartLabels = [];
              $chartData = [];
              for ($i = 11; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-$i months"));
                $chartLabels[] = date('M Y', strtotime($month . '-01'));
                $stmt = $conn->prepare("SELECT SUM(p.amount) as monthly_total FROM payments p JOIN appointments a ON p.appointment_id = a.appointment_id WHERE a.tenant_id = ? AND DATE_FORMAT(a.appointment_date, '%Y-%m') = ?");
                $stmt->bind_param("is", $tenantId, $month);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $chartData[] = $row['monthly_total'] ?? 0;
                $stmt->close();
              }
              ?>
              <script>
                const ctx = document.getElementById('revenueChart').getContext('2d');
                new Chart(ctx, {
                  type: 'line',
                  data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                      label: 'Monthly Revenue (₱)',
                      data: <?php echo json_encode($chartData); ?>,
                      borderColor: 'rgba(75, 192, 192, 1)',
                      backgroundColor: 'rgba(75, 192, 192, 0.2)',
                      tension: 0.1
                    }]
                  },
                  options: {
                    responsive: true,
                    scales: {
                      y: {
                        beginAtZero: true,
                        ticks: {
                          callback: function(value) {
                            return '₱' + value.toLocaleString();
                          }
                        }
                      }
                    }
                  }
                });
              </script>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <script>
    <?php printDateClockScript(); ?>

    document.addEventListener('DOMContentLoaded', function() {
      const params = new URLSearchParams(window.location.search);
      if (params.has('tab')) {
        const tabName = params.get('tab');
        const tabButton = document.querySelector(`[data-tab="${tabName}"]`);
        if (tabButton) {
          tabButton.click();
        }
      }

      const activityFrom = document.getElementById('activity_date_from');
      const activityTo = document.getElementById('activity_date_to');
      const activityTypeFilter = document.getElementById('activity_type_filter');
      [activityFrom, activityTo, activityTypeFilter].forEach(el => {
        if (el) el.addEventListener('change', loadActivityReport);
      });

      const revenueFrom = document.getElementById('revenue_date_from');
      const revenueTo = document.getElementById('revenue_date_to');
      [revenueFrom, revenueTo].forEach(el => {
        if (el) el.addEventListener('change', loadRevenueReport);
      });

      // Validate date ranges
      if (activityFrom && activityTo) {
        activityTo.addEventListener('change', function() {
          if (activityFrom.value && activityTo.value && activityTo.value < activityFrom.value) {
            showCustomAlert('End date cannot be before start date.');
            activityTo.value = '';
          }
        });
      }

      if (revenueFrom && revenueTo) {
        revenueTo.addEventListener('change', function() {
          if (revenueFrom.value && revenueTo.value && revenueTo.value < revenueFrom.value) {
            showCustomAlert('End date cannot be before start date.');
            revenueTo.value = '';
          }
        });
      }
    });

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

      fetch(`/get_filtered_reports.php?type=tenant_activity&date_from=${dateFrom}&date_to=${dateTo}&activity_type=${type}`)
        .then(resp => resp.json())
        .then(data => {
          if (data.success) {
            renderActivityTable(data.data);
          } else {
            showCustomAlert('Error loading activity data: ' + data.error);
          }
        })
        .catch(err => console.error(err));
    }

    function renderActivityTable(data) {
      const tbody = document.getElementById('activity-tbody');
      tbody.innerHTML = '';
      data.forEach(row => {
        const badge = getBadge(row.activity_type);
        let movementDetail = row.details || row.activity_description || row.activity_type;
        
        // Handle special cases for login/logout
        if (row.activity_type === 'Admin Login') {
          movementDetail = '👤 Admin Login';
        } else if (row.activity_type === 'Admin Logout') {
          movementDetail = '👤 Admin Logout';
        }
        
        tbody.innerHTML += `<tr>
          <td>${row.log_id}</td>
          <td>${row.log_time}</td>
          <td>${row.log_date}</td>
          <td>${badge}</td>
          <td>${movementDetail}</td>
        </tr>`;
      });
    }

    function getBadge(type) {
      switch (type) {
        case 'Created': return '<span class="badge badge-created">Created</span>';
        case 'Updated': return '<span class="badge badge-updated">Updated</span>';
        case 'Deleted': return '<span class="badge badge-deleted">Deleted</span>';
        case 'Admin Login': return '<span class="badge badge-created">Admin Login</span>';
        case 'Admin Logout': return '<span class="badge badge-deleted">Admin Logout</span>';
        default: return type;
      }
    }

function loadRevenueReport() {
      const dateFrom = document.getElementById('revenue_date_from').value;
      const dateTo = document.getElementById('revenue_date_to').value;
      const url = `/get_filtered_reports.php?type=revenue&date_from=${dateFrom}&date_to=${dateTo}`;
      console.log('Fetching revenue:', url);

      fetch(url)
        .then(resp => {
          console.log('Response status:', resp.status);
          return resp.json();
        })

        .then(data => {
          if (data.success) {
            renderRevenueTable(data.data);
          } else {
            showCustomAlert('Error loading revenue data: ' + data.error);
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

    // Export functions have been removed to keep the reports page streamlined and focused on review only.
  </script>

  <?php renderCustomModal(); ?>

</body>
</html>


