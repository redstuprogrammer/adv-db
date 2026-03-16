<?php
require_once __DIR__ . '/security_headers.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>OralSync | Clinic Platform</title>
  <link rel="stylesheet" href="tenant_style.css" />
  <style>
    .homeCard{max-width:980px;width:100%}
    .homeTop{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap}
    .homeTitle{font-size:22px;font-weight:900;color:var(--tenant-accent);margin:0}
    .homeSub{margin-top:6px;color:var(--tenant-muted);line-height:1.6}
    .homeGrid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}
    .homeBox{border:1px solid var(--tenant-border);border-radius:16px;padding:14px;background:#f9fafb}
    .homeBoxTitle{font-weight:900;margin-bottom:6px}
    .homeLink{display:inline-flex;align-items:center;gap:8px;text-decoration:none;font-weight:900;color:#0f172a}
    .homeLink:hover{text-decoration:underline}
    @media(max-width:900px){.homeGrid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="t-wrap">
    <div class="t-card homeCard">
      <div class="homeTop">
        <div>
          <h1 class="homeTitle">OralSync</h1>
          <div class="homeSub">
            Multi-tenant clinic platform for onboarding and tenant portals. If you’re a clinic owner, use the unique login link sent to you by email.
          </div>
        </div>
        <a class="t-btn t-btnPrimary" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center" href="superadmin_login.php">Super Admin</a>
      </div>

      <div class="homeGrid">
        <div class="homeBox">
          <div class="homeBoxTitle">Clinic owners</div>
          <div class="homeSub">Open your clinic’s unique login URL (example format):</div>
          <div style="margin-top:8px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,Liberation Mono,Courier New,monospace;font-size:12px;color:#0f172a;">
            /tenant/&lt;your-clinic-slug&gt;/login
          </div>
        </div>
        <div class="homeBox">
          <div class="homeBoxTitle">Site links</div>
          <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
            <a class="homeLink" href="privacy.php">Privacy Policy</a>
            <a class="homeLink" href="terms.php">Terms</a>
            <a class="homeLink" href="contact.php">Contact</a>
          </div>
        </div>
      </div>

      <div class="t-foot" style="margin-top:14px">
        This is a legitimate clinic management demo site. No software downloads are served from this domain.
      </div>
    </div>
  </div>
</body>
</html>
