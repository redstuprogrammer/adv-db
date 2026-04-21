<?php
require_once __DIR__ . '/includes/security_headers.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>OralSync | Privacy Policy</title>
  <link rel="stylesheet" href="tenant_style.css" />
</head>
<body>
  <div class="t-wrap">
    <div class="t-card" style="max-width:980px;width:100%">
      <h1 class="t-cardTitle">Privacy Policy</h1>
      <div class="t-cardSub">Last updated: <?php echo date('Y-m-d'); ?></div>

      <div style="margin-top:14px;color:#0f172a;line-height:1.7;font-size:14px">
        <p>OralSync is a multi-tenant clinic platform demo. We collect only the information necessary to provide login and basic clinic administration features.</p>
        <p><strong>Information collected:</strong> clinic name, owner name, email address, phone number, and address submitted by a super admin during onboarding.</p>
        <p><strong>Passwords:</strong> passwords are stored in hashed form.</p>
        <p><strong>Contact:</strong> if you have questions about this policy, use the Contact page.</p>
      </div>

      <div style="margin-top:14px">
        <a class="t-btn t-btnPrimary" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center" href="index.php">Back to home</a>
      </div>
    </div>
  </div>
</body>
</html>

