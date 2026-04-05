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
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

// Role Check Implementation - Ensure user is an Admin
if (!isset($_SESSION['role'])) {
    header("Location: /tenant_login.php");
    exit();
}

if ($_SESSION['role'] !== 'Admin') {
    header("Location: /tenant_login.php");
    exit();
}

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();

// Fetch payment records with patient and appointment info
$payments = [];
$query = "SELECT 
            py.payment_id, 
            p.patient_id,
            p.first_name, 
            p.last_name, 
            'General Service' AS service_name, 
            py.amount, 
            py.status,
            a.appointment_id,
            a.appointment_date
          FROM payment py
          LEFT JOIN appointment a ON py.appointment_id = a.appointment_id AND a.tenant_id = py.tenant_id
          LEFT JOIN patient p ON a.patient_id = p.patient_id AND p.tenant_id = py.tenant_id
          WHERE py.tenant_id = ?
          ORDER BY py.payment_id DESC";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt->close();
}

// Calculate summary statistics
$totalRevenue = 0;
$pendingAmount = 0;
$paidCount = 0;
$pendingCount = 0;

foreach ($payments as $payment) {
    $totalRevenue += (float)$payment['amount'];
    if (strtolower($payment['status']) === 'paid') {
        $paidCount++;
    } else {
        $pendingAmount += (float)$payment['amount'];
        $pendingCount++;
    }
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
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/../includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">💳 Billing & Payments</div>
        <div style="display: flex; align-items: center; gap: 16px;">
          <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
          <div id="liveClock" class="live-clock-badge">00:00:00 AM</div>
        </div>
      </div>


      <div class="module-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 16px; flex-wrap: wrap;">
          <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Transaction Audit</h2>
          <div class="search-container">
            <input type="text" id="paymentSearch" class="search-input" placeholder="🔍 Search patient, invoice, or status..." onkeyup="filterPayments()">
          </div>
        </div>

        <table class="module-table" id="paymentTable">
          <thead>
            <tr>
              <th>Invoice</th>
              <th>Patient Name</th>
              <th>Treatment</th>
              <th>Amount</th>
              <th>Status</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($payments)): ?>
              <tr>
                <td colspan="6" style="text-align: center; color: #64748b; padding: 40px;">No financial records found in the database.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($payments as $payment): ?>
                <tr>
                  <td style="font-family: monospace; font-weight: bold; color: var(--accent);">#<?php echo str_pad($payment['payment_id'], 4, '0', STR_PAD_LEFT); ?></td>
                  <td><strong><?php echo h($payment['first_name'] . " " . $payment['last_name']); ?></strong></td>
                  <td>
                    <div style="font-weight: 500;"><?php echo h($payment['service_name']); ?></div>
                    <div style="font-size: 11px; color: #94a3b8;"><?php echo $payment['appointment_date'] ? date('M d, Y', strtotime($payment['appointment_date'])) : 'N/A'; ?></div>
                  </td>
                  <td style="font-weight:700; color: var(--accent);">₱<?php echo number_format($payment['amount'], 2); ?></td>
                  <td><span class="status-pill status-<?php echo strtolower($payment['status']); ?>"><?php echo ucfirst($payment['status']); ?></span></td>
                  <td style="text-align: right;">
                    <a href="generate_pdf.php?id=<?php echo $payment['payment_id']; ?>&tenant=<?php echo urlencode($tenantSlug); ?>" class="action-btn" target="_blank">View PDF</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    // ✓ FLAG TEST: Billing module logic active
    console.log("Billing Module Active");
    console.log('UI Parity Active - Version 2.0');
    console.log('Billing Page Initialized');
    console.log('FINAL UI SYNC COMPLETE');
    
    // Live Clock Update Function
    function updateClock() {
      const now = new Date();
      const timeString = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
      });
      const clockElement = document.getElementById('liveClock');
      if (clockElement) {
        clockElement.textContent = timeString;
      }
    }
    updateClock();
    setInterval(updateClock, 1000);
    
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


