<?php
session_start();
require_once __DIR__ . '/security_headers.php';
require_once 'connect.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function baseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
$sessionTenantSlug = (string)($_SESSION['tenant_slug'] ?? '');
$tenantName = (string)($_SESSION['tenant_name'] ?? 'Clinic');

if (empty($_SESSION['tenant_id']) || $tenantSlug === '' || $sessionTenantSlug === '' || $tenantSlug !== $sessionTenantSlug) {
    // Use relative redirects to avoid mixed-content / proxy scheme issues.
    header('Location: tenant_login.php?tenant=' . rawurlencode($tenantSlug ?: 'unknown'));
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Dashboard</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
      .d-card{border:1px solid var(--tenant-border);background:var(--tenant-card);border-radius:18px;padding:22px;box-shadow:0 20px 40px rgba(15,23,42,0.10)}
      .d-top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
      .d-title{font-size:18px;font-weight:900;color:var(--tenant-accent);margin:0}
      .d-muted{color:var(--tenant-muted);font-size:13px;margin-top:6px;line-height:1.6}
      .d-pill{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:#f8fafc;border:1px solid var(--tenant-border);font-size:12px;color:#0f172a;font-weight:800}
      .d-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:14px}
      .d-slot{border:1px dashed var(--tenant-border);border-radius:14px;padding:14px;background:#f9fafb;min-height:96px}
      .d-slotTitle{font-weight:900;color:#0f172a;font-size:13px;margin-bottom:6px}
      .d-slotBody{color:var(--tenant-muted);font-size:12px;line-height:1.5}
      .d-link{color:#0f172a;text-decoration:none;font-weight:900}
      .d-link:hover{text-decoration:underline}
      @media(max-width:900px){.d-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
  <div class="t-wrap">
    <div style="width:100%;max-width:980px;">
      <div class="d-card">
        <div class="d-top">
          <div>
            <h1 class="d-title"><?php echo h($tenantName); ?> Dashboard</h1>
            <div class="d-muted">This dashboard is intentionally empty for now. We’ll add real widgets next (appointments, patients, billing, reports).</div>
          </div>
          <div class="d-pill">Tenant: <?php echo h($tenantSlug); ?></div>
        </div>

        <div class="d-grid">
          <div class="d-slot">
            <div class="d-slotTitle">Branding (coming soon)</div>
            <div class="d-slotBody">Clinic logo, accent color, welcome message, and support info.</div>
          </div>
          <div class="d-slot">
            <div class="d-slotTitle">Quick actions</div>
            <div class="d-slotBody">Create appointment, add patient, view schedule.</div>
          </div>
          <div class="d-slot">
            <div class="d-slotTitle">Analytics</div>
            <div class="d-slotBody">Today’s patients, upcoming visits, revenue summary.</div>
          </div>
        </div>

        <div style="margin-top:14px;color:var(--tenant-muted);font-size:12px;">
          <a class="d-link" href="tenant_logout.php?tenant=<?php echo urlencode($tenantSlug); ?>">Sign out</a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

