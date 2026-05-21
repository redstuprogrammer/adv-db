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
require_once __DIR__ . '/includes/tenant_tier_helper.php';

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
$hasBasicReporting = tenantHasTierFeature((int)$tenantId, 'basic_reporting', $conn);
$hasAdvancedReporting = tenantHasTierFeature((int)$tenantId, 'advanced_reporting', $conn);

if (!$hasBasicReporting) {
    http_response_code(403);
    die('Reports are not available on your current subscription plan.');
}
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

      .chart-container {
        max-width: 900px;
        margin: 0 auto 30px;
        height: 320px;
        position: relative;
      }

      /* Pagination Styles */
      .sa-pagination {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-top: 20px;
          padding: 15px 0;
          border-top: 1px solid var(--border);
      }

      .sa-pagination-info {
          font-size: 0.875rem;
          color: #64748b;
      }

      .sa-pagination-controls {
          display: flex;
          gap: 5px;
      }

      .sa-pagination-btn {
          padding: 6px 12px;
          border: 1px solid var(--border);
          background: white;
          color: var(--accent);
          border-radius: 4px;
          cursor: pointer;
          font-size: 0.875rem;
          transition: all 0.2s;
      }

      .sa-pagination-btn:hover:not(:disabled) {
          background: #f1f5f9;
      }

      .sa-pagination-btn.active {
          background: var(--accent);
          color: white;
          border-color: var(--accent);
      }

      .sa-pagination-btn:disabled {
          opacity: 0.5;
          cursor: not-allowed;
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
        <?php renderDateClock(); ?>
      </div>

      <!-- Tabs -->
      <div class="tabs">
        <button class="tab active" data-tab="activity">Activity Audit Trail</button>
        <?php if ($hasAdvancedReporting): ?>
          <button class="tab" data-tab="revenue">Sales Performance</button>
        <?php endif; ?>
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
          <div id="activity-pagination"></div>
        </div><!-- end .module-card -->
      </div><!-- end #activity tab-content -->

      <!-- Revenue Performance Tab -->
      <?php if ($hasAdvancedReporting): ?>
      <div class="tab-content" id="revenue">
        <div class="module-card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Sales Performance</h2>
            <div style="display: flex; gap: 10px; align-items: center;">
              <div style="color: #64748b; font-size: 13px;">Sales trends and payment status are tracked here.</div>
              <select id="revenueReportPeriod" style="padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 12px; font-family: inherit;">
                  <option value="all">All Time</option>
                  <option value="daily">Today's Sales</option>
                  <option value="weekly">This Week's Sales</option>
                  <option value="monthly">This Month's Sales</option>
                  <option value="yearly">This Year's Sales</option>
              </select>
              <button class="btn-primary" onclick="exportRevenuePDF(this)" style="padding: 6px 12px; font-size: 12px;">Generate Report</button>
            </div>
          </div>
          
          <div class="filters">
            <input type="date" id="revenue_date_from" />
            <input type="date" id="revenue_date_to" />
          </div>

          <div id="revenue-summary" style="margin-bottom: 20px; font-weight: bold; font-size: 18px; color: var(--accent);">
            Total Sales: ₱0.00
          </div>

          <div class="chart-container">
            <canvas id="revenueChart"></canvas>
          </div>

          <table class="module-table" id="revenue-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Patient Name</th>
                <th>Amount Paid</th>
                <th>Payment Type</th>
              </tr>
            </thead>
            <tbody id="revenue-tbody">
              <?php
              // Load initial data - Get all-time total first to match dashboard
              $totalQuery = $conn->prepare("SELECT SUM(amount_paid) as total FROM billing WHERE tenant_id = ? AND payment_status IN ('paid', 'partial')");
              $totalQuery->bind_param('i', $tenantId);
              $totalQuery->execute();
              $totalResult = $totalQuery->get_result()->fetch_assoc();
              $allTimeTotal = $totalResult['total'] ?? 0;
              $totalQuery->close();

              $stmt = $conn->prepare("SELECT p.first_name, p.last_name, py.amount_paid as amount, a.appointment_date as appointment_date, py.payment_type, py.payment_status, py.source 
                                      FROM billing py 
                                      LEFT JOIN appointment a ON py.appointment_id = a.appointment_id 
                                      LEFT JOIN patient p ON a.patient_id = p.patient_id 
                                      WHERE py.tenant_id = ? AND py.payment_status IN ('paid', 'partial') 
                                      ORDER BY a.appointment_date DESC LIMIT 10");
              $stmt->bind_param('i', $tenantId);
              $stmt->execute();
              $result = $stmt->get_result();
              $revenueRowCount = 0;
              while ($row = $result->fetch_assoc()) {
                $revenueRowCount++;
                $typeLabel = 'Full Payment';
                $pType = strtolower(trim($row['payment_type'] ?? ''));
                $pStatus = strtolower(trim($row['payment_status'] ?? ''));
                $pSource = strtolower(trim($row['source'] ?? ''));

                if ($pType === 'deposit') {
                    $typeLabel = 'Downpayment';
                } elseif ($pStatus === 'partial') {
                    $typeLabel = 'Partial Payment';
                } elseif ($pSource === 'mobile' && $pStatus === 'paid') {
                    $typeLabel = 'Downpayment';
                }
                
                echo "<tr>
                  <td>" . h($row['appointment_date']) . "</td>
                  <td>" . h($row['first_name']) . " " . h($row['last_name']) . "</td>
                  <td>₱" . number_format($row['amount'], 2) . "</td>
                  <td><span class='badge' style='background:rgba(13, 59, 102, 0.1); color:var(--accent);'>" . h($typeLabel) . "</span></td>
                </tr>";
              }
              $stmt->close();
              
              if ($revenueRowCount === 0) {
                echo "<tr><td colspan='4' style='text-align:center; color:#64748b;'>No sales records are available yet.</td></tr>";
              }
              ?>
            </tbody>
          </table>
          <div id="revenue-pagination"></div>
          <?php
          echo "<script>document.getElementById('revenue-summary').innerHTML = 'Total Sales: ₱" . number_format($allTimeTotal, 2) . "';</script>";
          // Revenue chart data - last 12 months
          $chartLabels = [];
          $chartData = [];
          for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $chartLabels[] = date('M Y', strtotime($month . '-01'));
            $stmt = $conn->prepare("SELECT SUM(p.amount_paid) as monthly_total FROM billing p JOIN appointment a ON p.appointment_id = a.appointment_id WHERE a.tenant_id = ? AND p.payment_status IN ('paid', 'partial') AND DATE_FORMAT(a.appointment_date, '%Y-%m') = ?");
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
                  label: 'Monthly Sales (₱)',
                  data: <?php echo json_encode($chartData); ?>,
                  borderColor: 'rgba(75, 192, 192, 1)',
                  backgroundColor: 'rgba(75, 192, 192, 0.2)',
                  tension: 0.1
                }]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
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
        </div><!-- end .module-card -->
      </div><!-- end #revenue tab-content -->
      <?php endif; ?>

    </div><!-- end tenant-main-content -->
  </div><!-- end tenant-layout -->

  <script>
    <?php printDateClockScript(); ?>

    let currentActivityPage = 1;
    let currentRevenuePage = 1;
    const perPage = 10;

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
        const targetTab = document.getElementById(tab.dataset.tab);
        if (targetTab) {
          targetTab.classList.add('active');
          // Load data for Revenue tab
          if (tab.dataset.tab === 'revenue') {
            loadRevenueReport();
          } else if (tab.dataset.tab === 'activity') {
            loadActivityReport();
          }
        }
      });
    });


    function loadActivityReport(page = 1) {
      currentActivityPage = page;
      const dateFrom = document.getElementById('activity_date_from').value;
      const dateTo = document.getElementById('activity_date_to').value;
      const type = document.getElementById('activity_type_filter').value;

      fetch(`/get_filtered_reports.php?type=tenant_activity&date_from=${dateFrom}&date_to=${dateTo}&activity_type=${type}&page=${page}&per_page=${perPage}`)
        .then(resp => resp.json())
        .then(data => {
          if (data.success) {
            renderActivityTable(data.data);
            renderPagination('activity', data.pagination);
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

    function loadRevenueReport(page = 1) {
      currentRevenuePage = page;
      const dateFrom = document.getElementById('revenue_date_from').value;
      const dateTo = document.getElementById('revenue_date_to').value;
      const url = `/get_filtered_reports.php?type=revenue&date_from=${dateFrom}&date_to=${dateTo}&page=${page}&per_page=${perPage}`;
      console.log('Fetching revenue:', url);

      fetch(url)
        .then(resp => {
          console.log('Response status:', resp.status);
          return resp.json();
        })

        .then(data => {
          if (data.success) {
            renderRevenueTable(data.data, data.pagination ? data.pagination.grand_total : 0);
            renderPagination('revenue', data.pagination);
          } else {
            showCustomAlert('Error loading revenue data: ' + data.error);
          }
        })
        .catch(err => console.error(err));
    }

    function renderRevenueTable(data, grandTotal = 0) {
      const tbody = document.getElementById('revenue-tbody');
      tbody.innerHTML = '';
      
      data.forEach(row => {
        let typeLabel = 'Full Payment';
        const pType = String(row.payment_type || '').toLowerCase();
        const pStatus = String(row.payment_status || '').toLowerCase();
        const pSource = String(row.source || '').toLowerCase();

        if (pType === 'deposit') {
          typeLabel = 'Downpayment';
        } else if (pStatus === 'partial') {
          typeLabel = 'Partial Payment';
        } else if (pSource === 'mobile' && pStatus === 'paid') {
          typeLabel = 'Downpayment';
        }
        
        tbody.innerHTML += `<tr>
          <td>${row.appointment_date}</td>
          <td>${row.first_name} ${row.last_name}</td>
          <td>₱${parseFloat(row.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
          <td><span class="badge" style="background:rgba(13, 59, 102, 0.1); color:var(--accent);">${typeLabel}</span></td>
        </tr>`;
      });
      document.getElementById('revenue-summary').innerHTML = 'Total Sales: ₱' + parseFloat(grandTotal).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function renderPagination(type, pagination) {
      const container = document.getElementById(`${type}-pagination`);
      if (!container) return;
      
      if (!pagination || pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
      }

      const loadFunc = type === 'activity' ? 'loadActivityReport' : 'loadRevenueReport';
      
      let html = `
        <div class="sa-pagination">
          <div class="sa-pagination-info">
            Showing ${(pagination.current_page - 1) * pagination.per_page + 1} to ${Math.min(pagination.current_page * pagination.per_page, pagination.total_count)} of ${pagination.total_count} records
          </div>
          <div class="sa-pagination-controls">
            <button class="sa-pagination-btn" ${pagination.current_page <= 1 ? 'disabled' : ''} onclick="${loadFunc}(${pagination.current_page - 1})">Previous</button>
      `;

      let startPage = Math.max(1, pagination.current_page - 2);
      let endPage = Math.min(pagination.total_pages, startPage + 4);
      if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
      }

      for (let i = startPage; i <= endPage; i++) {
        if (i < 1) continue;
        html += `<button class="sa-pagination-btn ${i === pagination.current_page ? 'active' : ''}" onclick="${loadFunc}(${i})">${i}</button>`;
      }

      html += `
            <button class="sa-pagination-btn" ${pagination.current_page >= pagination.total_pages ? 'disabled' : ''} onclick="${loadFunc}(${pagination.current_page + 1})">Next</button>
          </div>
        </div>
      `;
      container.innerHTML = html;
    }

    function exportRevenuePDF(btn) {
      const period = document.getElementById('revenueReportPeriod').value;
      const originalText = btn.textContent;
      btn.textContent = 'Generating...';
      btn.disabled = true;

      const tenantSlug = new URLSearchParams(window.location.search).get('tenant');
      const tenantId = '<?php echo $tenantId; ?>';

      fetch(`generate_pdf.php?tenant=${tenantSlug}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          type: 'sales', 
          title: 'Clinic Sales Report',
          period: period,
          tenant_id: tenantId
        })
      })
      .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
              if (response.status === 404) throw new Error('No data found for the selected period');
              throw new Error(text || 'PDF generation failed');
            });
        }
        return response.blob();
      })
      .then(blob => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Clinic_Sales_Report_${period}_${new Date().toISOString().split('T')[0]}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
      })
      .catch(error => {
        console.error(error);
        alert(error.message || 'Failed to generate report');
      })
      .finally(() => {
        btn.textContent = originalText;
        btn.disabled = false;
      });
    }

    // Export functions have been restored for enhanced professional reporting.
  </script>

  <?php renderCustomModal(); ?>

</body>
</html>
