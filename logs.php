<?php
// Placeholder page for legacy tenant reports/logs navigation
// Added to prevent 404s from tenant sidebar in current root deploy.

// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7);
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

$tenantName = $_SESSION['tenant_name'];
$tenantId = $_SESSION['tenant_id'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Reports</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
      body { background: #f8fafc; color: #102a43; margin: 0; font-family: Inter, sans-serif; }
      .tenant-layout { display: flex; min-height: 100vh; }
      .tenant-sidebar { width: 280px; background: #0d3b66; color: #fff; padding: 24px; box-sizing: border-box; }
      .sidebar-header { margin-bottom: 32px; }
      .sidebar-header .sidebar-logo-icon { font-size: 30px; margin-bottom: 10px; }
      .sidebar-logo-text { font-size: 20px; font-weight: 900; margin-bottom: 4px; }
      .sidebar-clinic-name { font-size: 14px; color: rgba(255,255,255,0.75); }
      .sidebar-nav { display: grid; gap: 10px; }
      .sidebar-nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 14px; color: #fff; text-decoration: none; transition: background 0.2s ease; }
      .sidebar-nav-item:hover { background: rgba(255,255,255,0.12); }
      .sidebar-nav-icon { font-size: 18px; }
      .sidebar-nav-item.active { background: rgba(255,255,255,0.18); }
      .tenant-main-content { flex: 1; padding: 32px; background: #eef2ff; }
      .tenant-header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
      .tenant-header-title { font-size: 28px; font-weight: 900; color: #0d3b66; }
      .tenant-header-date { color: #64748b; }
      .report-card { background: #fff; border: 1px solid #dbe4ff; border-radius: 22px; padding: 28px; box-shadow: 0 16px 32px rgba(13, 59, 102, 0.08); }
      .report-card h2 { margin-top: 0; color: #102a43; }
      .report-card p { color: #475569; line-height: 1.7; }
      .placeholder-tag { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 999px; background: #e0e7ff; color: #3730a3; font-weight: 700; margin-top: 16px; }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <main class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">Reports / Logs</div>
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
      </div>
      <section class="report-card">
        <h2>Placeholder Reports Page</h2>
        <p>This page has been added so legacy sidebar links no longer return a 404. It is intentionally lightweight and tenant-aware.</p>
        <div class="placeholder-tag">Tenant ID: <?php echo h((string)$tenantId); ?></div>
        <p style="margin-top: 24px;">If you want, I can next wire this page to show audit logs or tenant activity safely from the current database schema.</p>
      </section>
    </main>
  </div>
  <script>console.log("Tenant Logic Active");</script>
</body>
</html>
