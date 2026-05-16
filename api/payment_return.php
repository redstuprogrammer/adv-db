<?php
// ============================================================
// FILE TYPE: REDIRECT TARGET — deploy to server
// PATH on server: /api/payment_return.php
// ============================================================
// PayMongo redirects here after checkout (success or failed).
// This page just renders a minimal HTML page that the mobile
// WebView will intercept via onNavigationStateChange.
//
// The mobile app watches for URLs containing:
//   /payment_return.php?status=success  → confirm + show success
//   /payment_return.php?status=failed   → show error
// ============================================================

$status = $_GET['status'] ?? 'unknown';
$ref    = $_GET['ref']    ?? '';
$appt   = $_GET['appt']   ?? '';
$type   = $_GET['type']   ?? 'full';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>OralSync Payment</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #0f172a;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .card {
      background: #1e293b;
      border-radius: 20px;
      padding: 40px 32px;
      text-align: center;
      max-width: 360px;
      width: 100%;
    }
    .icon { font-size: 56px; margin-bottom: 20px; }
    .title { font-size: 22px; font-weight: 800; color: #f1f5f9; margin-bottom: 10px; }
    .subtitle { font-size: 14px; color: #64748b; line-height: 1.6; }
    .ref { font-size: 12px; color: #475569; margin-top: 16px; font-family: monospace; }
    .note { font-size: 12px; color: #334155; margin-top: 24px; }
  </style>
</head>
<body>
  <div class="card">
    <?php if ($status === 'success'): ?>
      <div class="icon">✅</div>
      <div class="title">Payment Successful</div>
      <div class="subtitle">Your payment has been processed. The app will update shortly.</div>
      <?php if ($ref): ?>
        <div class="ref">Ref: <?= htmlspecialchars($ref) ?></div>
      <?php endif; ?>
    <?php else: ?>
      <div class="icon">❌</div>
      <div class="title">Payment Failed</div>
      <div class="subtitle">Something went wrong. Please go back to the app and try again.</div>
    <?php endif; ?>
    <div class="note">You can close this window and return to OralSync.</div>
  </div>
</body>
</html>