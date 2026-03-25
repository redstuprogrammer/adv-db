<?php
session_start();
require_once __DIR__ . '/security_headers.php';
require_once 'connect.php';
require_once 'tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function baseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
error_log("tenant_dashboard.php accessed with tenant: " . $tenantSlug);
requireTenantLogin($tenantSlug);

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();

// Fetch metrics for dashboard
$patientCount = getTenantPatientCount($tenantId) ?? 0;
$appointmentCount = getTenantUpcomingAppointmentCount($tenantId) ?? 0;
$outstandingInvoices = getTenantOutstandingInvoiceCount($tenantId) ?? 0;
$todayRevenue = getTenantTodayRevenue($tenantId) ?? 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Dashboard</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
      :root {
        --dashboard-accent: #0d3b66;
        --dashboard-success: #10b981;
        --dashboard-warning: #f59e0b;
        --dashboard-danger: #ef4444;
        --dashboard-border: #e2e8f0;
        --dashboard-bg: #f8fafc;
      }

      .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        gap: 20px;
      }

      .dashboard-header h1 {
        font-size: 28px;
        font-weight: 900;
        color: var(--dashboard-accent);
        margin: 0;
      }

      .dashboard-header-meta {
        color: #64748b;
        font-size: 14px;
      }

      .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
      }

      .stat-card {
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        transition: all 0.2s ease;
      }

      .stat-card:hover {
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
        border-color: var(--dashboard-accent);
      }

      .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-bottom: 12px;
      }

      .stat-icon.icon-blue { background: rgba(13, 59, 102, 0.1); }
      .stat-icon.icon-green { background: rgba(16, 185, 129, 0.1); }
      .stat-icon.icon-amber { background: rgba(245, 158, 11, 0.1); }
      .stat-icon.icon-red { background: rgba(239, 68, 68, 0.1); }

      .stat-label {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
      }

      .stat-value {
        font-size: 28px;
        font-weight: 900;
        color: var(--dashboard-accent);
      }

      .quick-actions {
        margin-bottom: 32px;
      }

      .quick-actions h2 {
        font-size: 16px;
        font-weight: 700;
        color: var(--dashboard-accent);
        margin-bottom: 16px;
      }

      .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
      }

      .action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 16px;
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 10px;
        cursor: pointer;
        text-decoration: none;
        color: var(--dashboard-accent);
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s ease;
      }

      .action-btn:hover {
        background: var(--dashboard-bg);
        border-color: var(--dashboard-accent);
        box-shadow: 0 4px 12px rgba(13, 59, 102, 0.15);
      }

      .action-icon {
        font-size: 24px;
      }

      .modules-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
      }

      .module-card {
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        padding: 20px;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s ease;
      }

      .module-card:hover {
        border-color: var(--dashboard-accent);
        box-shadow: 0 8px 20px rgba(13, 59, 102, 0.12);
      }

      .module-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--dashboard-accent);
        margin-bottom: 6px;
      }

      .module-desc {
        font-size: 12px;
        color: #64748b;
        line-height: 1.5;
      }

      .footer-action {
        color: var(--dashboard-accent);
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
      }

      .footer-action:hover {
        text-decoration: underline;
      }

      @media (max-width: 768px) {
        .dashboard-header {
          flex-direction: column;
          align-items: flex-start;
        }

        .dashboard-stats {
          grid-template-columns: 1fr;
        }

        .actions-grid {
          grid-template-columns: repeat(2, 1fr);
        }

        .modules-grid {
          grid-template-columns: 1fr;
        }
      }
    </style>
</head>
<body>
  <div class="t-wrap">
    <div style="width:100%;max-width:1200px;margin:0 auto;padding:20px;">
      
      <!-- Header -->
      <div class="dashboard-header">
        <div>
          <h1><?php echo h($tenantName); ?></h1>
          <div class="dashboard-header-meta">Clinic Administration Dashboard</div>
        </div>
        <div style="font-size: 12px; color: #64748b;">
          <?php echo date('l, M d, Y'); ?>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="dashboard-stats">
        <div class="stat-card">
          <div class="stat-icon icon-blue">👥</div>
          <div class="stat-label">Total Patients</div>
          <div class="stat-value"><?php echo $patientCount; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon icon-green">📅</div>
          <div class="stat-label">Upcoming Appointments</div>
          <div class="stat-value"><?php echo $appointmentCount; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon icon-amber">💳</div>
          <div class="stat-label">Outstanding Invoices</div>
          <div class="stat-value"><?php echo $outstandingInvoices; ?></div>
        </div>

        <div class="stat-card">
          <div class="stat-icon icon-green">💰</div>
          <div class="stat-label">Today's Revenue</div>
          <div class="stat-value">₱<?php echo number_format($todayRevenue, 0); ?></div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="actions-grid">
          <a href="patients.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="action-btn">
            <span class="action-icon">➕</span>
            <span>Add Patient</span>
          </a>
          <a href="appointments.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="action-btn">
            <span class="action-icon">📅</span>
            <span>Schedule Appointment</span>
          </a>
          <a href="billing.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="action-btn">
            <span class="action-icon">💵</span>
            <span>Create Invoice</span>
          </a>
          <a href="manage_users.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="action-btn">
            <span class="action-icon">👤</span>
            <span>Manage Staff</span>
          </a>
        </div>
      </div>

      <!-- Core Modules -->
      <div style="margin-bottom: 32px;">
        <h2 style="font-size: 16px; font-weight: 700; color: var(--dashboard-accent); margin-bottom: 16px;">Core Modules</h2>
        <div class="modules-grid">
          <a href="manage_users.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="module-card">
            <div class="module-title">👤 Users & Staff</div>
            <div class="module-desc">Manage admins, receptionists, and dentists</div>
          </a>
          <a href="patients.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="module-card">
            <div class="module-title">👥 Patients</div>
            <div class="module-desc">View and manage patient records</div>
          </a>
          <a href="appointments.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="module-card">
            <div class="module-title">📅 Appointments</div>
            <div class="module-desc">Schedule and track appointments</div>
          </a>
          <a href="billing.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="module-card">
            <div class="module-title">💳 Billing</div>
            <div class="module-desc">Invoices and payment records</div>
          </a>
          <a href="reports.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="module-card">
            <div class="module-title">📊 Reports</div>
            <div class="module-desc">Analytics and activity reports</div>
          </a>
        </div>
      </div>

      <!-- Footer -->
      <div style="margin-top: 32px; padding-top: 20px; border-top: 1px solid var(--dashboard-border); text-align: right;">
        <a class="footer-action" href="tenant_logout.php?tenant=<?php echo urlencode($tenantSlug); ?>">Sign out</a>
      </div>

    </div>
  </div>
</body>
</html>

