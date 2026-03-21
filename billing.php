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
    <title><?php echo h($tenantName); ?> | Billing</title>
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
        <a href="tenant_dashboard.php?tenant=<?php echo urlencode($tenantSlug); ?>">Dashboard</a> / Billing
      </div>

      <div class="module-header">
        <div>
          <h1>💳 Billing & Payments</h1>
          <p style="color: #64748b; margin: 8px 0 0 0; font-size: 14px;">Manage invoices and payment records</p>
        </div>
        <a href="#" class="btn-primary">+ Create Invoice</a>
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
        <h2 style="margin-top: 0; color: var(--accent); font-size: 16px;">Payment Records</h2>
        
        <div class="filters">
          <input type="date" />
          <select>
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
                <a href="#" class="action-btn">View</a>
                <a href="#" class="action-btn">Print</a>
              </td>
            </tr>
            <tr>
              <td>INV-002</td>
              <td>Maria Santos</td>
              <td>₱3,500.00</td>
              <td>2026-03-15</td>
              <td><span class="badge badge-pending">Pending</span></td>
              <td>
                <a href="#" class="action-btn">View</a>
                <a href="#" class="action-btn">Print</a>
              </td>
            </tr>
            <tr>
              <td>INV-003</td>
              <td>Pedro Reyes</td>
              <td>₱7,200.00</td>
              <td>2026-02-20</td>
              <td><span class="badge badge-overdue">Overdue</span></td>
              <td>
                <a href="#" class="action-btn">View</a>
                <a href="#" class="action-btn">Print</a>
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
