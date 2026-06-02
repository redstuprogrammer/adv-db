<?php
/**
 * Tenant subscription management page.
 * Only tenant Admin users may access this page.
 */

ini_set('session.gc_maxlifetime', 86400 * 7);
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);
session_start();

require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/tenant_tier_helper.php';
require_once __DIR__ . '/includes/date_clock.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

if (!isset($_SESSION['role']) || strtolower(trim((string)$_SESSION['role'])) !== 'admin') {
    header('Location: tenant_login.php?tenant=' . rawurlencode($tenantSlug));
    exit();
}

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();

function tableExists($conn, string $tableName): bool {
    $tableName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '" . $tableName . "'");
    return $result && $result->num_rows > 0;
}

$subscriptionsTableExists = tableExists($conn, 'subscriptions');
$paymentMethodsTableExists = tableExists($conn, 'payment_methods');

$subscription = null;
$defaultPlanName = null;
$currentPaymentMethod = null;
$saveMessage = null;
$errorMessage = null;

$paymentMethods = [];
$pendingNotifications = [];
$notificationCount = 0;
$hasAutoRenewFailure = false;

if ($subscriptionsTableExists) {
    $stmt = $conn->prepare(
        'SELECT s.*, sp.plan_name, sp.price, sp.duration_days
         FROM subscriptions s
         LEFT JOIN subscription_plans sp ON sp.plan_id = s.plan_id
         WHERE s.tenant_id = ?
         ORDER BY s.id DESC
         LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $subscription = $result->fetch_assoc() ?: null;
        $stmt->close();
    }
}

$autoRenewEnabled = false;
$nextRenewalAt = null;
$subscriptionEndAt = null;
$subscriptionEndingSoon = false;
$subscriptionEndMessage = null;

$tenantSubEndAt = null;
$tStmt = $conn->prepare('SELECT subscription_start_date, subscription_duration FROM tenants WHERE tenant_id = ?');
if ($tStmt) {
    $tStmt->bind_param('i', $tenantId);
    $tStmt->execute();
    $tRes = $tStmt->get_result()->fetch_assoc();
    if ($tRes && !empty($tRes['subscription_start_date']) && !empty($tRes['subscription_duration'])) {
        $tenantSubEndAt = date('Y-m-d H:i:s', strtotime('+' . (int)$tRes['subscription_duration'] . ' months', strtotime($tRes['subscription_start_date'])));
    }
    $tStmt->close();
}

if (!empty($subscription)) {
    $autoRenewEnabled = !empty($subscription['auto_renew']);
    $subscriptionEndAt = $subscription['current_period_end'] ?? null;
    if ($autoRenewEnabled) {
        $nextRenewalAt = $subscriptionEndAt;
    }
}

// Use the most reliable end date available — prefer tenantSubEndAt, fall back to subscriptions table
$effectiveEndAt = $tenantSubEndAt ?? $subscriptionEndAt ?? null;
if (!empty($effectiveEndAt)) {
    $endTimestamp = strtotime($effectiveEndAt);
    if ($endTimestamp !== false) {
        $daysRemaining = (int)ceil(($endTimestamp - time()) / 86400);
        if ($daysRemaining >= 0 && $daysRemaining <= 7) {
            $subscriptionEndingSoon = true;
            $subscriptionEndMessage = $daysRemaining === 0
                ? 'Your subscription ends today.'
                : 'Your subscription ends in ' . $daysRemaining . ' day' . ($daysRemaining === 1 ? '' : 's') . '.';
        }
    }
}

if ($paymentMethodsTableExists) {
  // Single query — $currentPaymentMethod derived as first row afterwards
  $allStmt = $conn->prepare(
    'SELECT id, provider, brand, last4, exp_month, exp_year, billing_contact, is_default, provider_token
     FROM payment_methods
     WHERE tenant_id = ?
     ORDER BY is_default DESC, id DESC'
  );
  if ($allStmt) {
    $allStmt->bind_param('i', $tenantId);
    $allStmt->execute();
    $res = $allStmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $paymentMethods[] = $row;
    }
    $allStmt->close();
  }
  $currentPaymentMethod = $paymentMethods[0] ?? null;
}

$notificationsTableExists = tableExists($conn, 'subscription_renewal_attempts');
if ($notificationsTableExists) {
    $notifStmt = $conn->prepare(
        'SELECT id, subscription_id, attempt_count, status, response, next_retry_at, is_read, created_at
         FROM subscription_renewal_attempts
         WHERE tenant_id = ? AND status != ?
         ORDER BY created_at DESC'
    );
    if ($notifStmt) {
        $statusSuccess = 'success';
        $notifStmt->bind_param('is', $tenantId, $statusSuccess);
        $notifStmt->execute();
        $res = $notifStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $pendingNotifications[] = $row;
            if (empty($row['is_read'])) {
                $notificationCount++;
            }
        }
        $notifStmt->close();
        $hasAutoRenewFailure = count($pendingNotifications) > 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ----- Save or delete payment method -----
  if (isset($_POST['save_payment_method'])) {
    $provider = trim($_POST['provider'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $last4 = preg_replace('/\D/', '', (string)($_POST['last4'] ?? ''));
    $exp_month = intval($_POST['exp_month'] ?? 0);
    $exp_year = intval($_POST['exp_year'] ?? 0);
    $billing_contact = trim($_POST['billing_contact'] ?? '');
    $make_default = isset($_POST['make_default']) ? 1 : 0;

    if ($provider === '' || $last4 === '' || $exp_month < 1 || $exp_month > 12 || $exp_year < 2000) {
      $errorMessage = 'Please provide a valid payment method (provider, 4 digits, expiry).';
    } else {
      // If making default, clear other defaults first
      if ($make_default) {
        $clear = $conn->prepare('UPDATE payment_methods SET is_default = 0 WHERE tenant_id = ?');
        if ($clear) { $clear->bind_param('i', $tenantId); $clear->execute(); $clear->close(); }
      }

      if (!empty($currentPaymentMethod) && !empty($currentPaymentMethod['id'])) {
        $pmId = (int)$currentPaymentMethod['id'];
        $upd = $conn->prepare('UPDATE payment_methods SET provider = ?, brand = ?, last4 = ?, exp_month = ?, exp_year = ?, billing_contact = ?, is_default = ? WHERE id = ? AND tenant_id = ?');
        if ($upd) {
          $upd->bind_param('sssiiisii', $provider, $brand, $last4, $exp_month, $exp_year, $billing_contact, $make_default, $pmId, $tenantId);
          if ($upd->execute()) {
            $saveMessage = 'Payment method updated.';
            try {
              $desc = safeDesc('Updated', 'Payment Method', $pmId, ['provider' => $provider, 'last4' => $last4]);
              logTenantActivity($conn, $tenantId, 'Updated', $desc);
            } catch (Exception $e) { error_log('Payment method update logging failed: ' . $e->getMessage()); }
          } else {
            $errorMessage = 'Unable to update payment method.';
          }
          $upd->close();
        }
      } else {
        $ins = $conn->prepare('INSERT INTO payment_methods (tenant_id, provider, brand, last4, exp_month, exp_year, billing_contact, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        if ($ins) {
          $ins->bind_param('isssiisi', $tenantId, $provider, $brand, $last4, $exp_month, $exp_year, $billing_contact, $make_default);
          if ($ins->execute()) {
            $newId = (int)$conn->insert_id;
            $saveMessage = 'Payment method saved.';
            try {
              $desc = safeDesc('Created', 'Payment Method', $newId, ['provider' => $provider, 'last4' => $last4]);
              logTenantActivity($conn, $tenantId, 'Created', $desc);
            } catch (Exception $e) { error_log('Payment method creation logging failed: ' . $e->getMessage()); }
          } else {
            $errorMessage = 'Unable to save payment method.';
          }
          $ins->close();
        }
      }
      // Refresh payment methods for immediate UI feedback
      $paymentMethods = [];
      $rfStmt = $conn->prepare('SELECT id, provider, brand, last4, exp_month, exp_year, billing_contact, is_default, provider_token FROM payment_methods WHERE tenant_id = ? ORDER BY is_default DESC, id DESC');
      if ($rfStmt) { $rfStmt->bind_param('i', $tenantId); $rfStmt->execute(); $rfRes = $rfStmt->get_result(); while ($r = $rfRes->fetch_assoc()) { $paymentMethods[] = $r; } $rfStmt->close(); }
      $currentPaymentMethod = $paymentMethods[0] ?? null;
    }
  }

  if (isset($_POST['delete_payment_method']) && !empty($currentPaymentMethod['id'])) {
    $pmId = (int)$currentPaymentMethod['id'];
    $del = $conn->prepare('DELETE FROM payment_methods WHERE id = ? AND tenant_id = ?');
    if ($del) {
      $del->bind_param('ii', $pmId, $tenantId);
      if ($del->execute()) {
        $saveMessage = 'Payment method deleted.';
        try { $desc = safeDesc('Deleted', 'Payment Method', $pmId); logTenantActivity($conn, $tenantId, 'Deleted', $desc); } catch (Exception $e) { error_log('Payment method delete logging failed: ' . $e->getMessage()); }
        $currentPaymentMethod = null;
        $paymentMethods = [];
      } else {
        $errorMessage = 'Unable to delete payment method.';
      }
      $del->close();
    }
  }

  if (isset($_POST['mark_notifications_read'])) {
    if ($notificationsTableExists) {
      $updRead = $conn->prepare('UPDATE subscription_renewal_attempts SET is_read = 1 WHERE tenant_id = ? AND status != ?');
      if ($updRead) {
        $success = 'success';
        $updRead->bind_param('is', $tenantId, $success);
        if ($updRead->execute()) {
          $saveMessage = 'Notifications marked as read.';
          $notificationCount = 0;
        } else {
          $errorMessage = 'Unable to mark notifications as read.';
        }
        $updRead->close();
      }
    }
  }

  // Only update auto-renew when that form was actually submitted
  if (!isset($_POST['save_payment_method']) && !isset($_POST['delete_payment_method']) && !isset($_POST['mark_notifications_read'])) {
    $autoRenewValue = isset($_POST['auto_renew']) ? 1 : 0;
    if ($subscription && isset($subscription['id'])) {
      $currentAutoRenew = intval($subscription['auto_renew'] ?? 0);
      $update = $conn->prepare('UPDATE subscriptions SET auto_renew = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?');
      if ($update) {
        $update->bind_param('iii', $autoRenewValue, $subscription['id'], $tenantId);
        if ($update->execute()) {
          $saveMessage = 'Subscription preferences have been saved successfully.';
          $subscription['auto_renew'] = $autoRenewValue; // reflect change immediately
          $autoRenewEnabled = (bool)$autoRenewValue;
          if ($currentAutoRenew !== $autoRenewValue) {
            try {
              $desc = safeDesc('Updated', 'Subscription', $subscription['id'], ['setting' => 'auto_renew', 'value' => $autoRenewValue ? 'enabled' : 'disabled']);
              logTenantActivity($conn, $tenantId, 'Updated', $desc);
            } catch (Exception $e) {
              error_log('Auto-renew logging failed: ' . $e->getMessage());
            }
          }
        }
        $update->close();
      }
    } else {
      $errorMessage = 'No active subscription record was found to update.';
    }
  }
} // End if POST


$displayPlanName = $subscription['plan_name'] ?? null;
if (!$displayPlanName) {
    $tenantTier = trim((string)($_SESSION['tenant_subscription_tier'] ?? '')) ?: null;
    if (!$tenantTier && $tenantId) {
        $tenantTier = null;
        $stmt = $conn->prepare('SELECT subscription_tier FROM tenants WHERE tenant_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $stmt->bind_result($tenantTierResult);
            if ($stmt->fetch()) {
                $tenantTier = $tenantTierResult;
            }
            $stmt->close();
        }
    }
    $displayPlanName = $tenantTier ? ucwords($tenantTier) : 'Unknown Plan';
}


function formatReadableDate(?string $date): string {
    if (empty($date)) { return 'N/A'; }
    $timestamp = strtotime($date);
    if ($timestamp === false) { return 'N/A'; }
    return date('M d, Y', $timestamp);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($tenantName); ?> | Subscription</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="tenant_style.css">
  <style>
    :root {
      --navy:        #0d3b66;
      --navy-dark:   #082c52;
      --navy-light:  #e8f0f9;
      --slate:       #475569;
      --slate-light: #94a3b8;
      --border:      #e2e8f0;
      --bg:          #f4f7fb;
      --white:       #ffffff;
      --success-bg:  #ecfdf5;
      --success-bd:  #a7f3d0;
      --warn-bg:     #fffbeb;
      --warn-bd:     #fde68a;
      --danger-bg:   #fef2f2;
      --danger-bd:   #fecaca;
      --radius-sm:   10px;
      --radius-md:   16px;
      --radius-lg:   22px;
      --shadow:      0 4px 24px rgba(13,59,102,.08);
    }
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: #1e293b; }

    /* ── Wrapper ── */
    .sub-wrap { max-width: 860px; margin: 0 auto; padding: 32px 24px 64px; }

    /* ── Page header ── */
    .sub-page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 28px; gap: 12px; flex-wrap: wrap; }
    .sub-page-header h1 { font-family: 'DM Serif Display', serif; font-size: 2rem; color: var(--navy); margin: 0; line-height: 1.1; }
    .sub-page-header p { margin: 6px 0 0; font-size: 0.9rem; color: var(--slate); }
    .plan-badge { display: inline-flex; align-items: center; gap: 8px; background: var(--navy); color: #fff; font-size: 0.82rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; padding: 7px 16px; border-radius: 999px; white-space: nowrap; }
    .plan-badge::before { content: ''; display: block; width: 7px; height: 7px; border-radius: 50%; background: #34d399; }

    /* ── Alerts ── */
    .sub-alert { display: flex; align-items: flex-start; gap: 14px; border-radius: var(--radius-md); padding: 16px 20px; margin-bottom: 16px; font-size: 0.9rem; font-weight: 500; border: 1px solid transparent; animation: fadeUp .35s ease both; }
    .sub-alert-icon { font-size: 1.2rem; flex-shrink: 0; margin-top: 1px; }
    .sub-alert strong { display: block; margin-bottom: 2px; font-size: 0.88rem; text-transform: uppercase; letter-spacing: .05em; }
    .sub-alert.success { background: var(--success-bg); color: #065f46; border-color: var(--success-bd); }
    .sub-alert.error   { background: var(--danger-bg);  color: #7f1d1d; border-color: var(--danger-bd); }
    .sub-alert.warning { background: var(--warn-bg);    color: #78350f; border-color: var(--warn-bd); }

    /* ── Cards ── */
    .sub-card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow); padding: 26px 28px; margin-bottom: 20px; animation: fadeUp .4s ease both; }
    .sub-card:nth-child(2) { animation-delay: .06s; }
    .sub-card:nth-child(3) { animation-delay: .12s; }
    .sub-card-label { font-size: 0.68rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--slate-light); margin: 0 0 20px; }

    /* ── Stats ── */
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 22px; }
    .stat-item { background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 14px 16px; transition: border-color .2s, box-shadow .2s; }
    .stat-item:hover { border-color: #bdd5ed; box-shadow: var(--shadow); }
    .stat-lbl { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--slate-light); margin-bottom: 7px; }
    .stat-val { font-size: 1rem; font-weight: 600; color: var(--navy); line-height: 1.3; }
    .stat-val.muted { color: var(--slate); font-weight: 500; }
    .status-pill { display: inline-block; padding: 3px 11px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
    .status-pill.active  { background: #dcfce7; color: #166534; }
    .status-pill.trial   { background: #dbeafe; color: #1e40af; }
    .status-pill.expired { background: #fee2e2; color: #991b1b; }

    /* ── Storage ── */
    .storage-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 9px; }
    .storage-header strong { font-size: 0.85rem; font-weight: 600; color: var(--navy); }
    .storage-header span { font-size: 0.78rem; color: var(--slate-light); }
    .storage-track { height: 7px; background: var(--border); border-radius: 999px; overflow: hidden; }
    .storage-fill { height: 100%; border-radius: 999px; transition: width .7s cubic-bezier(.4,0,.2,1); }
    .storage-fill.ok      { background: linear-gradient(90deg,#0d3b66,#1d6fa4); }
    .storage-fill.warning { background: linear-gradient(90deg,#d97706,#f59e0b); }
    .storage-fill.danger  { background: linear-gradient(90deg,#dc2626,#ef4444); }
    .storage-footer { display: flex; justify-content: space-between; margin-top: 7px; font-size: 0.75rem; color: var(--slate-light); }

    /* ── Divider ── */
    .sub-hr { border: none; border-top: 1px solid var(--border); margin: 22px 0; }

    /* ── Toggle ── */
    .toggle-row { display: flex; align-items: flex-start; gap: 16px; padding: 16px 18px; background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-md); }
    .toggle-switch { position: relative; flex-shrink: 0; width: 44px; height: 25px; margin-top: 2px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
    .toggle-slider { position: absolute; inset: 0; background: #cbd5e1; border-radius: 999px; cursor: pointer; transition: background .25s; }
    .toggle-slider::before { content: ''; position: absolute; left: 3px; top: 3px; width: 19px; height: 19px; background: #fff; border-radius: 50%; box-shadow: 0 1px 4px rgba(0,0,0,.18); transition: transform .25s; }
    .toggle-switch input:checked + .toggle-slider { background: var(--navy); }
    .toggle-switch input:checked + .toggle-slider::before { transform: translateX(19px); }
    .toggle-text strong { display: block; font-size: 0.9rem; font-weight: 600; color: #1e293b; margin-bottom: 3px; }
    .toggle-text p { margin: 0; font-size: 0.81rem; color: var(--slate); line-height: 1.55; }

    /* ── Inline alerts ── */
    .inline-alert { display: flex; gap: 11px; align-items: flex-start; padding: 13px 15px; border-radius: var(--radius-sm); margin-top: 13px; font-size: 0.83rem; border: 1px solid transparent; line-height: 1.5; }
    .inline-alert.warn  { background: var(--warn-bg);   color: #78350f; border-color: var(--warn-bd); }
    .inline-alert.error { background: var(--danger-bg); color: #7f1d1d; border-color: var(--danger-bd); }
    .ia-icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

    /* ── Save button ── */
    .form-footer { display: flex; justify-content: flex-end; margin-top: 20px; }
    .btn-save { display: inline-flex; align-items: center; gap: 7px; background: var(--navy); color: #fff; border: none; border-radius: var(--radius-sm); padding: 11px 26px; font-size: 0.87rem; font-weight: 600; font-family: 'DM Sans', sans-serif; cursor: pointer; transition: background .2s, box-shadow .2s, transform .15s; box-shadow: 0 2px 10px rgba(13,59,102,.22); }
    .btn-save:hover { background: var(--navy-dark); box-shadow: 0 5px 18px rgba(13,59,102,.32); transform: translateY(-1px); }
    .btn-save:active { transform: translateY(0); }

    /* ── Payment methods ── */
    .pm-list { display: flex; flex-direction: column; gap: 10px; }
    .pm-card { display: flex; align-items: center; gap: 14px; padding: 13px 16px; border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--bg); transition: border-color .2s, box-shadow .2s; }
    .pm-card:hover { border-color: #bdd5ed; box-shadow: var(--shadow); }
    .pm-card.is-default { border-color: #bfdbfe; background: #f0f7ff; }
    .pm-icon { width: 40px; height: 40px; background: var(--white); border: 1px solid var(--border); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
    .pm-info { flex: 1; min-width: 0; }
    .pm-info strong { display: block; font-size: 0.88rem; font-weight: 600; color: #1e293b; }
    .pm-info span { font-size: 0.78rem; color: var(--slate); }
    .pm-default-tag { font-size: 0.68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; background: #dbeafe; color: #1e40af; padding: 3px 9px; border-radius: 999px; flex-shrink: 0; }
    .pm-empty { text-align: center; padding: 24px 20px; color: var(--slate-light); font-size: 0.86rem; }
    .pm-empty-icon { font-size: 1.8rem; margin-bottom: 6px; }
    .btn-paymongo { display: inline-flex; align-items: center; gap: 7px; background: var(--white); color: var(--navy); border: 1.5px solid var(--navy); border-radius: var(--radius-sm); padding: 9px 18px; font-size: 0.83rem; font-weight: 600; font-family: 'DM Sans', sans-serif; text-decoration: none; transition: background .2s, color .2s; margin-top: 14px; }
    .btn-paymongo:hover { background: var(--navy); color: #fff; }

    /* ── Animation ── */
    @keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

    @media (max-width: 640px) {
      .sub-wrap { padding: 18px 14px 48px; }
      .sub-page-header h1 { font-size: 1.6rem; }
      .sub-card { padding: 18px 16px; }
      .stat-grid { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">Subscription</div>
        <?php renderDateClock(); ?>
      </div>

      <div class="sub-wrap">

        <!-- Header -->
        <div class="sub-page-header">
          <div>
            <h1>Subscription</h1>
            <p><?php echo h($tenantName); ?> &mdash; manage your plan &amp; billing</p>
          </div>
          <span class="plan-badge"><?php echo h($displayPlanName); ?></span>
        </div>

        <!-- Flash messages -->
        <?php if ($saveMessage): ?>
          <div class="sub-alert success">
            <span class="sub-alert-icon">✅</span>
            <div><strong>Saved</strong><?php echo h($saveMessage); ?></div>
          </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
          <div class="sub-alert error">
            <span class="sub-alert-icon">⛔</span>
            <div><strong>Error</strong><?php echo h($errorMessage); ?></div>
          </div>
        <?php endif; ?>
        <?php if (!empty($subscriptionEndingSoon)): ?>
          <div class="sub-alert warning">
            <span class="sub-alert-icon">⚠️</span>
            <div>
              <strong>Renewal due soon</strong>
              <?php if ($autoRenewEnabled): ?>
                Auto-renews on <strong><?php echo h(formatReadableDate($subscriptionEndAt)); ?></strong>. Ensure your payment method is current.
              <?php else: ?>
                Expires on <strong><?php echo h(formatReadableDate($subscriptionEndAt)); ?></strong>. Enable auto-renew or renew manually to avoid interruption.
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php
        $storageInfo    = getTenantStorageUsageInfo($tenantId, $conn);
        $storageUsedMb  = formatBytesToMB($storageInfo['usage_bytes']);
        $storageLimitMb = formatBytesToMB($storageInfo['limit_bytes'] ?? 0);
        $storagePercent = $storageInfo['usage_percent'] ?? 0;
        $storageFillClass = $storagePercent >= 90 ? 'danger' : ($storagePercent >= 70 ? 'warning' : 'ok');
        $statusRaw  = strtolower($subscription['status'] ?? 'active');
        $statusPill = in_array($statusRaw, ['active','trial','expired']) ? $statusRaw : 'active';
        ?>

        <!-- Plan overview card -->
        <div class="sub-card">
          <p class="sub-card-label">Plan Overview</p>

          <div class="stat-grid">
            <div class="stat-item">
              <div class="stat-lbl">Plan</div>
              <div class="stat-val"><?php echo h($displayPlanName); ?></div>
            </div>
            <div class="stat-item">
              <div class="stat-lbl">Status</div>
              <div class="stat-val">
                <span class="status-pill <?php echo $statusPill; ?>"><?php echo h(ucfirst($statusRaw)); ?></span>
              </div>
            </div>
            <div class="stat-item">
              <div class="stat-lbl">Trial Ends</div>
              <div class="stat-val muted"><?php echo h(formatReadableDate($subscription['trial_ends_at'] ?? null)); ?></div>
            </div>
            <div class="stat-item">
              <div class="stat-lbl">Subscription Ends</div>
              <div class="stat-val muted"><?php echo h(formatReadableDate($tenantSubEndAt ?? $subscriptionEndAt)); ?></div>
            </div>
            <div class="stat-item">
              <div class="stat-lbl">Next Renewal</div>
              <div class="stat-val muted"><?php echo h($autoRenewEnabled ? formatReadableDate($nextRenewalAt) : 'Disabled'); ?></div>
            </div>
          </div>

          <hr class="sub-hr">

          <div class="storage-header">
            <strong>Storage</strong>
            <span><?php echo h((string)$storagePercent); ?>% used</span>
          </div>
          <div class="storage-track">
            <div class="storage-fill <?php echo $storageFillClass; ?>" style="width:<?php echo h((string)min((int)$storagePercent,100)); ?>%"></div>
          </div>
          <div class="storage-footer">
            <span><?php echo h($storageUsedMb); ?> used</span>
            <span><?php echo h($storageLimitMb); ?> limit</span>
          </div>
        </div>

        <!-- Billing settings card -->
        <div class="sub-card">
          <p class="sub-card-label">Billing Settings</p>

          <form method="post" action="subscription.php?tenant=<?php echo rawurlencode($tenantSlug); ?>">

            <div class="toggle-row">
              <label class="toggle-switch">
                <input type="checkbox" name="auto_renew" id="auto_renew" value="1"<?php echo !empty($subscription['auto_renew']) ? ' checked' : ''; ?>>
                <span class="toggle-slider"></span>
              </label>
              <div class="toggle-text">
                <strong>Automatic Renewal</strong>
                <p>Keep your subscription active and auto-renew at period end. If disabled, access continues until the current period expires.</p>
              </div>
            </div>

            <?php if ($hasAutoRenewFailure): ?>
              <div class="inline-alert error">
                <span class="ia-icon">🚨</span>
                <div><strong>Renewal failed.</strong> A recent billing attempt did not go through. Check your payment method below.</div>
              </div>
            <?php endif; ?>

            <?php if ($subscriptionEndingSoon): ?>
              <div class="inline-alert warn">
                <span class="ia-icon">⏳</span>
                <div><?php echo h($subscriptionEndMessage); ?>
                <?php echo $autoRenewEnabled ? " Auto-renewal is on — you're set." : ' Auto-renewal is off. Renew manually to keep access.'; ?></div>
              </div>
            <?php endif; ?>

            <div class="form-footer">
              <button type="submit" class="btn-save">Save Settings</button>
            </div>

          </form>
        </div>

        <!-- Payment methods card -->
        <div class="sub-card">
          <p class="sub-card-label">Payment Methods</p>

          <?php if (!empty($paymentMethods)): ?>
            <div class="pm-list">
              <?php foreach ($paymentMethods as $pm): ?>
                <?php
                  $brand = strtolower($pm['brand'] ?? '');
                  $pmIcon = match(true) {
                    str_contains($brand,'gcash')  => '📱',
                    str_contains($brand,'maya')   => '📱',
                    str_contains($brand,'grab')   => '🛵',
                    default                       => '💳',
                  };
                ?>
                <div class="pm-card <?php echo !empty($pm['is_default']) ? 'is-default' : ''; ?>">
                  <div class="pm-icon"><?php echo $pmIcon; ?></div>
                  <div class="pm-info">
                    <strong><?php echo h(ucfirst($pm['brand'] ?? 'Card')); ?> &nbsp;•••• <?php echo h($pm['last4'] ?? ''); ?></strong>
                    <span>Expires <?php echo h($pm['exp_month'] ?? ''); ?>/<?php echo h($pm['exp_year'] ?? ''); ?>
                      <?php if (!empty($pm['billing_contact'])): ?>&nbsp;·&nbsp;<?php echo h($pm['billing_contact']); ?><?php endif; ?>
                    </span>
                  </div>
                  <?php if (!empty($pm['is_default'])): ?>
                    <span class="pm-default-tag">Default</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="pm-empty">
              <div class="pm-empty-icon">💳</div>
              No saved payment methods. Manage billing via PayMongo.
            </div>
          <?php endif; ?>

          <a class="btn-paymongo" href="https://dashboard.paymongo.com/" target="_blank" rel="noopener">
            ↗&nbsp; Manage PayMongo Account
          </a>
        </div>

      </div><!-- /sub-wrap -->
    </div>
  </div>
</body>
</html>
