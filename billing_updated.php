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
$tenantConfig = getTenantConfig($tenantId);
$bookingDepositAmount = isset($tenantConfig['booking_deposit_amount']) ? (float)$tenantConfig['booking_deposit_amount'] : 0.0;
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
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">💳 Billing & Payments</div>
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
      </div>

      <div class="summary-grid">
        <div class="summary-card">
          <div class="summary-label">Total Revenue</div>
          <div class="summary-value">₱45,250.00</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">Pending Payments</div>
          <div class="summary-value">₱8,500.00</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">Overdue</div>
          <div class="summary-value">₱2,100.00</div>
        </div>
      </div>

      <div class="module-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 12px; flex-wrap: wrap;">
          <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Payment Records</h2>
          <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button class="btn-primary" style="background: #14b8a6;" onclick="openDepositModal(); return false;">Set Booking Downpayment</button>
            <a href="#" class="btn-primary" onclick="alert('Create Invoice functionality coming soon!'); return false;">+ Create Invoice</a>
          </div>
        </div>
        <div style="margin-bottom: 16px; color: #0f172a; font-size: 14px;">
          Current booking downpayment: <strong><?php echo $bookingDepositAmount > 0 ? '₱' . number_format($bookingDepositAmount, 2) : 'None configured'; ?></strong>
        </div>
        
        <div class="filters">
          <input type="date" onchange="alert('Filter functionality coming soon!');" />
          <select onchange="alert('Filter functionality coming soon!');">
            <option>All Status</option>
            <option>Paid</option>
            <option>Pending</option>
            <option>Overdue</option>
          </select>
        </div>

        <table class="module-table">
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Patient</th>
              <th>Amount</th>
              <th>Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>INV-001</td>
              <td>Juan Dela Cruz</td>
              <td>₱5,000.00</td>
              <td>2026-03-10</td>
              <td><span class="badge badge-paid">Paid</span></td>
              <td>
                <a href="#" class="action-btn" onclick="alert('View Invoice - coming soon'); return false;">View</a>
                <a href="#" class="action-btn" onclick="alert('Print Invoice - coming soon'); return false;">Print</a>
              </td>
            </tr>
            <tr>
              <td>INV-002</td>
              <td>Maria Santos</td>
              <td>₱3,500.00</td>
              <td>2026-03-15</td>
              <td><span class="badge badge-pending">Pending</span></td>
              <td>
                <a href="#" class="action-btn" onclick="alert('View Invoice - coming soon'); return false;">View</a>
                <a href="#" class="action-btn" onclick="alert('Print Invoice - coming soon'); return false;">Print</a>
              </td>
            </tr>
            <tr>
              <td>INV-003</td>
              <td>Pedro Reyes</td>
              <td>₱7,200.00</td>
              <td>2026-02-20</td>
              <td><span class="badge badge-overdue">Overdue</span></td>
              <td>
                <a href="#" class="action-btn" onclick="alert('View Invoice - coming soon'); return false;">View</a>
                <a href="#" class="action-btn" onclick="alert('Print Invoice - coming soon'); return false;">Print</a>
              </td>
            </tr>
          </tbody>
        </table>
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
  </script>
</body>
</html>
