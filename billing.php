<?php
/**
 * ============================================
 * TENANT BILLING & PAYMENT AUDIT - ENHANCED WITH TRANSACTION TRACKING
 * Last Updated: April 4, 2026
 * Features: Payment Records, Revenue Summary, Transaction Audit, PDF Export
 * ✓ FLAG TEST: Billing module successfully updated for Azure
 * ============================================
 */

// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';
require_once __DIR__ . '/includes/tenant_tier_helper.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

// Role Check Implementation - Ensure user is an Admin
if (!isset($_SESSION['role'])) {
    header("Location: tenant_login.php");
    exit();
}

if ($_SESSION['role'] !== 'Admin') {
    header("Location: tenant_login.php");
    exit();
}

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();
$hasPaymentTracking = tenantHasTierFeature((int)$tenantId, 'payment_tracking', $conn);
$hasInvoiceGeneration = tenantHasTierFeature((int)$tenantId, 'invoice_generation', $conn);

if (!$hasPaymentTracking) {
    http_response_code(403);
    die('Billing and payment tracking are not available on your current subscription plan.');
}
$tenantConfig = getTenantConfig($tenantId);
$bookingDepositAmount = isset($tenantConfig['booking_deposit_amount']) ? (float)$tenantConfig['booking_deposit_amount'] : 0.0;

// Ensure appointment_id exists (critical migration fix for Azure)
$checkColumn = $conn->query("SHOW COLUMNS FROM payment LIKE 'appointment_id'");
if ($checkColumn && $checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE payment ADD COLUMN appointment_id INT AFTER tenant_id");
}

// Pagination Logic
$records_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// Get total records for pagination
$total_records = 0;
$count_query = "SELECT COUNT(*) FROM billing WHERE tenant_id = ?";
$count_stmt = mysqli_prepare($conn, $count_query);
if ($count_stmt) {
    mysqli_stmt_bind_param($count_stmt, 'i', $tenantId);
    mysqli_stmt_execute($count_stmt);
    mysqli_stmt_bind_result($count_stmt, $total_records);
    mysqli_stmt_fetch($count_stmt);
    mysqli_stmt_close($count_stmt);
}
$total_pages = ceil($total_records / $records_per_page);

$query = "SELECT 
            py.billing_id as payment_id, 
            p.patient_id,
            p.first_name, 
            p.last_name, 
            COALESCE(s.service_name, 'General Service') AS service_name, 
            py.total_amount as amount, 
            py.amount_paid,
            py.payment_status as status, 
            py.mode,
            py.payment_type,
            py.billing_date,
            py.source,
            a.appointment_id,
            a.appointment_date
          FROM billing py
          LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
          LEFT JOIN patient p ON a.patient_id = p.patient_id
          LEFT JOIN service s ON a.service_id = s.service_id
          WHERE py.tenant_id = ?
          ORDER BY py.billing_id DESC
          LIMIT ? OFFSET ?";

$payments = [];
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'iii', $tenantId, $records_per_page, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Calculate summary statistics (for ALL records, not just the paginated page)
$totalRevenue = 0;
$pendingAmount = 0;
$paidCount = 0;
$pendingCount = 0;

$stats_query = "SELECT amount_paid, payment_status FROM billing WHERE tenant_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
if ($stats_stmt) {
    mysqli_stmt_bind_param($stats_stmt, 'i', $tenantId);
    mysqli_stmt_execute($stats_stmt);
    $stats_result = mysqli_stmt_get_result($stats_stmt);
    while ($row = mysqli_fetch_assoc($stats_result)) {
        $totalRevenue += (float)$row['amount_paid'];
        if (strtolower($row['payment_status'] ?? '') === 'paid') {
            $paidCount++;
        } else {
            $pendingAmount += (float)$row['amount_paid'];
            $pendingCount++;
        }
    }
    mysqli_stmt_close($stats_stmt);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Billing</title>
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
        margin-bottom: 24px;
      }

      .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
      }

      .summary-card {
        padding: 16px;
        background: var(--bg);
        border-radius: 8px;
        border-left: 4px solid var(--accent);
      }

      .summary-label {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 6px;
      }

      .summary-value {
        font-size: 24px;
        font-weight: 900;
        color: var(--accent);
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

      .badge-paid { background: rgba(16, 185, 129, 0.1); color: #10b981; }
      .badge-pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
      .badge-overdue { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

      .live-clock-badge {
        background: linear-gradient(135deg, rgba(13, 59, 102, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
        border: 2px solid var(--accent);
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 16px;
        font-weight: 700;
        color: var(--accent);
        font-family: 'Courier New', monospace;
        letter-spacing: 1px;
        white-space: nowrap;
      }

      /* Status Pills */
      .status-pill {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
        display: inline-block;
      }

      .status-pill.status-paid { background: #dcfce7; color: #166534; }
      .status-pill.status-installment,
      .status-pill.status-pending { background: #fef9c3; color: #854d0e; }

      /* Search */
      .search-container {
        margin-bottom: 20px;
      }

      .search-input {
        width: 100%;
        max-width: 400px;
        padding: 12px 16px;
        border: 1px solid var(--border);
        border-radius: 25px;
        outline: none;
        font-size: 14px;
      }

      .search-input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.1);
      }

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

      .modal { display: none; position: fixed; z-index: 9999; inset: 0; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; backdrop-filter: blur(2px); }
      .modal-content { background: white; padding: 28px; border-radius: 16px; width: min(420px, 90%); position: relative; box-shadow: 0 20px 40px rgba(0,0,0,0.12); }
      .close-x { position: absolute; right: 18px; top: 14px; cursor: pointer; font-size: 24px; color: #64748b; }
      .form-group { margin-bottom: 16px; }
      .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #0d3b66; }
      .form-group input { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid #e2e8f0; }
      
      /* Pagination Styles */
      .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 30px;
        padding: 20px 0;
      }
      .page-link {
        padding: 8px 16px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: white;
        color: var(--accent);
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s ease;
      }
      .page-link:hover {
        background: var(--bg);
        border-color: var(--accent);
      }
      .page-link.active {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
      }
      .page-link.disabled {
        color: #94a3b8;
        pointer-events: none;
        background: #f1f5f9;
        border-color: #e2e8f0;
      }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">Billing & Payments</div>
        <?php renderDateClock(); ?>
      </div>


      <div class="module-card">
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px;">
          <button class="action-btn" style="background: #14b8a6; border-color: #14b8a6;" onclick="openDepositModal(); return false;">Set Booking Downpayment</button>
          <div style="color: #0f172a; font-size: 14px;">
            Current booking downpayment: <strong><?php echo $bookingDepositAmount > 0 ? '₱' . number_format($bookingDepositAmount, 2) : 'None configured'; ?></strong>
          </div>
          <button class="action-btn" style="background: var(--accent); border-color: var(--accent);" onclick="openAddModal(); return false;">+ Create Invoice</button>
        </div>

        <div class="search-container" style="margin-bottom:16px;">
          <input type="text" id="paymentSearch" class="search-input" placeholder="Search patient, invoice, or status..." onkeyup="filterPayments()">
        </div>

        <table class="module-table" id="paymentTable">
          <thead>
            <tr>
              <th>Invoice</th>
              <th>Patient Name</th>
              <th>Amount</th>
              <th>Type</th>
              <th>Date & Time</th>
              <th>Mode</th>
              <th>Status</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($payments)): ?>
              <tr>
                <td colspan="7" style="text-align: center; color: #64748b; padding: 40px;">No financial records found in the database.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($payments as $payment): ?>
                <tr>
                  <td style="font-family: monospace; font-weight: bold; color: var(--accent);">#<?php echo str_pad($payment['payment_id'], 4, '0', STR_PAD_LEFT); ?></td>
                  <td><strong><?php echo h($payment['first_name'] . " " . $payment['last_name']); ?></strong></td>
                  <td style="font-weight:700; color: var(--accent);">₱<?php echo number_format($payment['amount'], 2); ?></td>
                  <td>
                    <?php 
                      $typeLabel = 'Full Payment';
                      $pType = strtolower(trim($payment['payment_type'] ?? ''));
                      $pStatus = strtolower(trim($payment['status'] ?? ''));
                      $pSource = strtolower(trim($payment['source'] ?? ''));

                      if ($pStatus === 'unpaid') {
                          $typeLabel = 'Unpaid Invoice';
                      } elseif ($pType === 'deposit') {
                          $typeLabel = 'Downpayment';
                      } elseif ($pStatus === 'partial' || $pStatus === 'installment') {
                          $typeLabel = 'Partial Payment';
                      } elseif ($pSource === 'mobile' && $pStatus === 'paid') {
                          // Fallback: If it's from mobile and paid, and not explicitly marked 'full', 
                          // it's likely a downpayment if it doesn't match service total
                          $typeLabel = 'Downpayment';
                      }
                      echo '<span class="badge" style="background:rgba(13, 59, 102, 0.1); color:var(--accent);">' . h($typeLabel) . '</span>';
                    ?>
                  </td>
                  <td style="font-size: 12px; color: #64748b;">
                    <?php 
                      if (!empty($payment['billing_date'])) {
                          echo date('M d, Y', strtotime($payment['billing_date'])) . '<br>';
                          echo '<small>' . date('h:i A', strtotime($payment['billing_date'])) . '</small>';
                      } else {
                          echo 'N/A';
                      }
                    ?>
                  </td>
                  <td><?php echo h(ucfirst($payment['mode'] ?: 'N/A')); ?></td>
                  <td><span class="status-pill status-<?php echo strtolower($payment['status']); ?>"><?php echo ucfirst($payment['status']); ?></span></td>
                  <td style="text-align: right;">
                    <?php if ($hasInvoiceGeneration): ?>
                      <a href="print_invoice.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&id=<?php echo $payment['payment_id']; ?>" class="action-btn" target="_blank">Print</a>
                    <?php else: ?>
                      <span style="color:#64748b;font-size:12px;">Invoice print unavailable on current plan</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <!-- Pagination Navigation -->
        <?php if ($total_pages > 1): ?>
          <div class="pagination">
            <a href="?tenant=<?php echo urlencode($tenantSlug); ?>&page=<?php echo max(1, $page - 1); ?>" 
               class="page-link <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
               &laquo; Previous
            </a>

            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);

            if ($start > 1) {
                echo '<a href="?tenant=' . urlencode($tenantSlug) . '&page=1" class="page-link">1</a>';
                if ($start > 2) echo '<span style="color: #94a3b8;">...</span>';
            }

            for ($i = $start; $i <= $end; $i++) {
                $activeClass = ($i === $page) ? 'active' : '';
                echo '<a href="?tenant=' . urlencode($tenantSlug) . '&page=' . $i . '" class="page-link ' . $activeClass . '">' . $i . '</a>';
            }

            if ($end < $total_pages) {
                if ($end < $total_pages - 1) echo '<span style="color: #94a3b8;">...</span>';
                echo '<a href="?tenant=' . urlencode($tenantSlug) . '&page=' . $total_pages . '" class="page-link">' . $total_pages . '</a>';
            }
            ?>

            <a href="?tenant=<?php echo urlencode($tenantSlug); ?>&page=<?php echo min($total_pages, $page + 1); ?>" 
               class="page-link <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
               Next &raquo;
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div id="depositModal" class="modal">
    <div class="modal-content">
      <span class="close-x" onclick="closeDepositModal()">&times;</span>
      <h3 style="color: #0d3b66; margin: 0 0 18px 0;">Clinic Booking Downpayment</h3>
      <p style="color: #64748b; margin-bottom: 18px;">Set the clinic downpayment amount used for patient-requested mobile appointments.</p>
      <div class="form-group">
        <label for="booking_deposit_amount">Downpayment Amount (₱)</label>
        <input type="number" id="booking_deposit_amount" step="0.01" min="0.00" value="<?php echo number_format($bookingDepositAmount, 2, '.', ''); ?>">
      </div>
      <button type="button" class="btn-primary" style="width: 100%;" onclick="saveDepositConfig()">Save Downpayment</button>
      <div id="depositMessage" style="margin-top: 14px; color: #0d3b66;"></div>
    </div>
  </div>

  <script>
    // ✓ FLAG TEST: Billing module logic active
    console.log("Billing Module Active");
    console.log('UI Parity Active - Version 2.0');
    console.log('Billing Page Initialized');
    console.log('FINAL UI SYNC COMPLETE');
    
    function openDepositModal() {
      document.getElementById('booking_deposit_amount').value = <?php echo json_encode(number_format($bookingDepositAmount, 2, '.', '')); ?>;
      document.getElementById('depositMessage').textContent = '';
      document.getElementById('depositModal').style.display = 'flex';
    }

    function closeDepositModal() {
      document.getElementById('depositModal').style.display = 'none';
    }

    async function saveDepositConfig() {
      const amountField = document.getElementById('booking_deposit_amount');
      const amount = parseFloat(amountField.value);
      const messageEl = document.getElementById('depositMessage');

      if (isNaN(amount) || amount < 0) {
        messageEl.textContent = 'Please enter a valid non-negative amount.';
        messageEl.style.color = '#b91c1c';
        return;
      }

      const response = await fetch('api/save_deposit_config.php?tenant=' + encodeURIComponent('<?php echo rawurlencode($tenantSlug); ?>'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ booking_deposit_amount: amount })
      });
      const result = await response.json();

      if (result.success) {
        messageEl.textContent = result.message;
        messageEl.style.color = '#166534';
        document.getElementById('depositModal').style.display = 'none';
        document.querySelector('.module-card > div:nth-child(2) strong').textContent = amount > 0 ? '₱' + amount.toFixed(2) : 'None configured';
      } else {
        messageEl.textContent = result.message || 'Unable to save downpayment.';
        messageEl.style.color = '#b91c1c';
      }
    }

    window.onclick = function(e) {
      if (e.target.id === 'depositModal') {
        closeDepositModal();
      }
    }

    <?php printDateClockScript(); ?>
    
    function filterPayments() {
      const query = document.getElementById('paymentSearch').value.toLowerCase();
      const rows = document.querySelectorAll('#paymentTable tbody tr');
      
      rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
      });
    }
  </script>
</body>
</html>


