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
$notificationMessage = null;

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
if (!empty($subscription)) {
    $autoRenewEnabled = !empty($subscription['auto_renew']);
    $subscriptionEndAt = $subscription['current_period_end'] ?? null;
    if ($autoRenewEnabled) {
        $nextRenewalAt = $subscriptionEndAt;
    }
    if (!empty($subscriptionEndAt)) {
        $endTimestamp = strtotime($subscriptionEndAt);
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
}

if ($paymentMethodsTableExists) {
    $stmt = $conn->prepare(
      'SELECT id, provider, brand, last4, exp_month, exp_year, billing_contact, is_default
       FROM payment_methods
       WHERE tenant_id = ?
       ORDER BY is_default DESC, id DESC
       LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentPaymentMethod = $result->fetch_assoc() ?: null;
        $stmt->close();
    }

  // Fetch all payment methods for display
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
          $upd->bind_param('sssiisiii', $provider, $brand, $last4, $exp_month, $exp_year, $billing_contact, $make_default, $pmId, $tenantId);
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
          $ins->bind_param('isssiiisi', $tenantId, $provider, $brand, $last4, $exp_month, $exp_year, $billing_contact, $make_default);
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
      // Refresh current payment method for immediate UI feedback
      $stmt = $conn->prepare('SELECT id, provider, brand, last4, exp_month, exp_year, billing_contact, is_default FROM payment_methods WHERE tenant_id = ? ORDER BY is_default DESC, id DESC LIMIT 1');
      if ($stmt) { $stmt->bind_param('i', $tenantId); $stmt->execute(); $currentPaymentMethod = $stmt->get_result()->fetch_assoc() ?: null; $stmt->close(); }
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

  if (isset($_POST['save_integration_settings'])) {
        $gateway = trim($_POST['payment_gateway'] ?? 'PayMongo');
        $oldGateway = getTenantConfigValue($tenantId, 'payment_gateway', 'PayMongo');

        if (saveTenantConfig($tenantId, ['payment_gateway' => $gateway])) {
            $saveMessage = 'Integration settings saved successfully.';
            if ($oldGateway !== $gateway) {
                    try {
                        $desc = safeDesc('Updated', 'Settings', null, ['section' => 'payment_gateway', 'from' => $oldGateway, 'to' => $gateway]);
                        logTenantActivity($conn, $tenantId, 'Updated', $desc);
                    } catch (Exception $e) {
                        error_log('Payment gateway logging failed: ' . $e->getMessage());
                    }
                }
            } else {
                $errorMessage = 'Unable to save integration settings. Please try again.';
            }
        }

        $autoRenewValue = isset($_POST['auto_renew']) ? 1 : 0;
        if ($subscription && isset($subscription['id'])) {
            $currentAutoRenew = intval($subscription['auto_renew'] ?? 0);
            $update = $conn->prepare('UPDATE subscriptions SET auto_renew = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?');
            if ($update) {
                $update->bind_param('iii', $autoRenewValue, $subscription['id'], $tenantId);
                if ($update->execute()) {
                    $saveMessage = 'Subscription preferences have been saved successfully.';
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
    if (empty($date)) {
        return 'N/A';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return 'N/A';
    }
    return date('M d, Y', $timestamp);
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($tenantName); ?> | Subscription</title>
  <link rel="stylesheet" href="tenant_style.css">
  <style>
    .subscription-card { background: white; border-radius: 20px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08); padding: 28px; margin-bottom: 24px; }
    .subscription-card h2 { margin-top: 0; color: #0d3b66; }
    .subscription-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-top: 18px; }
    .subscription-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; }
    .subscription-item h3 { margin: 0 0 12px; font-size: 14px; color: #0d3b66; }
    .subscription-item p { margin: 0; color: #334155; font-size: 16px; line-height: 1.5; }
    .alert { border-radius: 16px; padding: 16px 18px; margin-bottom: 20px; font-weight: 600; }
    .alert-success { background: #ecfdf5; color: #0f766e; border: 1px solid #6ee7b7; }
    .alert-error { background: #fce7f3; color: #9d174d; border: 1px solid #f472b6; }
    .alert-warning { background: #fffbeb; color: #78350f; border: 1px solid #facc15; }
    .form-checkbox { display: flex; align-items: center; gap: 12px; margin-top: 20px; }
    .form-checkbox input { width: 18px; height: 18px; }
    .button-primary { border: none; border-radius: 14px; background: #0d3b66; color: white; padding: 12px 22px; cursor: pointer; font-weight: 700; }
    .button-primary:hover { background: #0a2d4f; }
    .payment-method { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 16px; padding: 18px; }
    .payment-method p { margin: 4px 0; }
    .content-columns { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 24px; }
    .left-column { min-width: 0; }
    .notification-panel { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 22px; padding: 22px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
    .notification-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .notification-tab { display: inline-flex; align-items: center; justify-content: center; background: #eef2ff; color: #1d4ed8; border-radius: 999px; padding: 10px 16px; font-weight: 700; }
    .notification-pill { display: inline-block; background: #e0f2fe; color: #0369a1; border-radius: 999px; padding: 8px 12px; margin-bottom: 12px; font-size: 13px; font-weight: 700; }
    .notification-item { background: #f8fafc; border: 1px solid #dbeafe; border-radius: 16px; padding: 16px; margin-bottom: 14px; }
    .notification-title { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
    @media (max-width: 980px) {
      .content-columns { grid-template-columns: 1fr; }
      .notification-panel { margin-top: 24px; }
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

      <?php if ($saveMessage): ?>
        <div class="alert alert-success"><?php echo h($saveMessage); ?></div>
      <?php endif; ?>
      <?php if ($errorMessage): ?>
        <div class="alert alert-error"><?php echo h($errorMessage); ?></div>
      <?php endif; ?>
      <?php if (!empty($subscriptionEndingSoon)): ?>
        <div class="alert alert-warning">
          <strong>Renewal due soon:</strong>
          <?php if ($autoRenewEnabled): ?>
            Your subscription is set to renew automatically on <?php echo h(formatReadableDate($subscriptionEndAt)); ?>. Please ensure your payment method is current.
          <?php else: ?>
            Your subscription expires on <?php echo h(formatReadableDate($subscriptionEndAt)); ?>. Renew manually or enable auto-renew to avoid service interruption.
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="content-columns">
        <div class="left-column">
          <div class="subscription-card">
            <h2>Subscription Details</h2>
            
            <?php
            $storageInfo = getTenantStorageUsageInfo($tenantId, $conn);
            $storageUsedMb = formatBytesToMB($storageInfo['usage_bytes']);
            $storageLimitMb = formatBytesToMB($storageInfo['limit_bytes'] ?? 0);
            $storagePercent = $storageInfo['usage_percent'] ?? 0;
            ?>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; margin-bottom: 18px;">
                <h3 style="margin: 0 0 12px; font-size: 14px; color: #0d3b66;">Storage Usage (<?php echo h((string)$storagePercent); ?>%)</h3>
                <div style="background: #e2e8f0; border-radius: 8px; height: 12px; width: 100%; overflow: hidden; margin-bottom: 8px;">
                    <div style="background: <?php echo $storagePercent >= 90 ? '#ef4444' : '#0d3b66'; ?>; height: 100%; width: <?php echo h((string)$storagePercent); ?>%; transition: width 0.3s ease;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 13px; color: #475569;">
                    <span><?php echo h($storageUsedMb); ?> used</span>
                    <span><?php echo h($storageLimitMb); ?> total limit</span>
                </div>
            </div>

            <div class="subscription-grid">
              <div class="subscription-item">
                <h3>Current Plan</h3>
                <p><?php echo h($displayPlanName); ?></p>
              </div>
              <div class="subscription-item">
                <h3>Current Status</h3>
                <p><?php echo h($subscription['status'] ?? 'Active'); ?></p>
              </div>
              <div class="subscription-item">
                <h3>Trial ends</h3>
                <p><?php echo h(formatReadableDate($subscription['trial_ends_at'] ?? null)); ?></p>
              </div>
              <div class="subscription-item">
                <h3>Subscription ends</h3>
                <p><?php echo h(formatReadableDate($subscriptionEndAt)); ?></p>
              </div>
              <div class="subscription-item">
                <h3>Next renewal</h3>
                <p><?php echo h($autoRenewEnabled ? formatReadableDate($nextRenewalAt) : 'Disabled'); ?></p>
              </div>
            </div>

            <form method="post" action="subscription.php?tenant=<?php echo rawurlencode($tenantSlug); ?>">
              <div class="subscription-item" style="grid-column: 1 / -1;">
                <div class="form-checkbox">
                  <input type="checkbox" name="auto_renew" id="auto_renew" value="1"<?php echo isset($subscription['auto_renew']) && $subscription['auto_renew'] ? ' checked' : ''; ?> />
                  <label for="auto_renew">Keep my subscription active and automatically renew at the end of the current period.</label>
                </div>
                <p style="color: #475569; margin-top: 12px;">Toggle this checkbox to stop or resume automatic billing. Access remains until the end of the current period.</p>
              </div>
              <?php if ($hasAutoRenewFailure): ?>
                <div class="subscription-item" style="grid-column: 1 / -1; background:#fff1f2; border-color:#fecdd3;">
                  <h3 style="color:#b91c1c;">Auto-renewal alert</h3>
                  <p style="color:#991b1b;">An automatic renewal attempt failed. Please update your payment method or review the pending notifications on the right.</p>
                </div>
              <?php endif; ?>
              <?php if ($subscriptionEndingSoon): ?>
                <div class="subscription-item" style="grid-column: 1 / -1; background:#fff7ed; border-color:#fcd34d;">
                  <h3 style="color:#b45309;">Subscription ending soon</h3>
                  <p style="color:#92400e;"><?php echo h($subscriptionEndMessage); ?> <?php echo $autoRenewEnabled ? 'Your subscription is set to renew automatically.' : 'Auto-renewal is disabled, so your plan will expire unless you renew manually.'; ?></p>
                </div>
              <?php endif; ?>
              <div style="grid-column: 1 / -1; text-align: right; margin-top: 16px;">
                <button type="submit" class="button-primary">Save Subscription Settings</button>
              </div>
            </form>

            <div class="subscription-item subscription-card" style="margin-top:24px;">
              <h3>Payment method</h3>
              <div class="payment-method">
                <?php if (!empty($paymentMethods)): ?>
                  <?php foreach ($paymentMethods as $pm): ?>
                    <div style="border:1px solid #e6eef6; padding:10px; border-radius:10px; margin-bottom:8px; background:#fff;">
                      <strong><?php echo h($pm['brand'] ?? 'Card'); ?></strong>
                      <span style="color:#64748b; margin-left:8px;">ending in <?php echo h($pm['last4'] ?? ''); ?></span>
                      <?php if (!empty($pm['is_default'])): ?>
                        <span style="background:#ecfdf5; color:#065f46; padding:4px 8px; border-radius:8px; margin-left:10px; font-size:12px;">Default</span>
                      <?php endif; ?>
                      <div style="font-size:13px; color:#475569; margin-top:6px;">Expiry: <?php echo h($pm['exp_month'] ?? ''); ?>/<?php echo h($pm['exp_year'] ?? ''); ?>
                        <?php if (!empty($pm['provider_token'])): ?>
                          &nbsp;•&nbsp; <span style="font-family:monospace; color:#334155;">token: <?php echo h(substr($pm['provider_token'], 0, 8)); ?>...</span>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p style="color:#667085;">No saved payment methods available. You can still manage your subscription settings here.</p>
                <?php endif; ?>

                <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;">
                  <a class="button-primary" href="https://dashboard.paymongo.com/" target="_blank" style="text-decoration:none; display:inline-block; padding:10px 14px; border-radius:12px;">Manage PayMongo Account</a>
                </div>
              </div>
            </div>

            <div class="subscription-item subscription-card" style="margin-top:24px;">
              <h3>Update fallback payment method</h3>
              <form method="post" action="subscription.php?tenant=<?php echo rawurlencode($tenantSlug); ?>">
                <input type="hidden" name="save_payment_method" value="1">
                <input type="hidden" name="existing_pm_id" value="<?php echo h($currentPaymentMethod['id'] ?? ''); ?>">
                <p><label>Provider</label><br>
                  <input type="text" name="provider" value="<?php echo h($currentPaymentMethod['provider'] ?? 'PayMongo'); ?>" style="width:100%; padding:8px; margin-top:6px;" required></p>
                <p><label>Brand (Card Brand)</label><br>
                  <input type="text" name="brand" value="<?php echo h($currentPaymentMethod['brand'] ?? ''); ?>" style="width:100%; padding:8px; margin-top:6px;"></p>
                <p style="display:flex; gap:8px;"><span style="flex:1"><label>Last 4 digits</label><br>
                  <input type="text" name="last4" maxlength="4" value="<?php echo h($currentPaymentMethod['last4'] ?? ''); ?>" style="width:100%; padding:8px; margin-top:6px;" required></span>
                  <span style="width:140px"><label>Exp (MM)</label><br>
                  <input type="number" name="exp_month" min="1" max="12" value="<?php echo h($currentPaymentMethod['exp_month'] ?? ''); ?>" style="width:100%; padding:8px; margin-top:6px;" required></span>
                  <span style="width:160px"><label>Exp (YYYY)</label><br>
                  <input type="number" name="exp_year" min="2023" value="<?php echo h($currentPaymentMethod['exp_year'] ?? ''); ?>" style="width:100%; padding:8px; margin-top:6px;" required></span></p>
                <p><label>Billing Contact</label><br>
                  <input type="text" name="billing_contact" value="<?php echo h($currentPaymentMethod['billing_contact'] ?? ''); ?>" style="width:100%; padding:8px; margin-top:6px;"></p>
                <p><label style="display:inline-block; margin-right:12px;"><input type="checkbox" name="make_default" value="1" <?php echo (!empty($currentPaymentMethod['is_default']) ? 'checked' : ''); ?>> Make default</label></p>
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:10px;">
                  <button type="submit" class="button-primary">Save Payment Method</button>
                  <?php if (!empty($currentPaymentMethod['id'])): ?>
                    <button type="submit" name="delete_payment_method" value="1" onclick="return confirm('Delete saved payment method? This cannot be undone.');" style="background:#ef4444; border:none; color:white; padding:12px 16px; border-radius:12px;">Delete</button>
                  <?php endif; ?>
                </div>
              </form>
            </div>

            <form method="post" action="subscription.php?tenant=<?php echo rawurlencode($tenantSlug); ?>">
              <div class="subscription-item subscription-card" style="grid-column: 1 / -1;">
                <h3>🔌 Integrations & Payments</h3>
                <p style="color: #475569; margin-bottom: 16px;">Choose and configure your clinic's active payment gateway provider for patients' booking deposits and bill payments.</p>
                <?php $activeGateway = getTenantConfigValue($tenantId, 'payment_gateway', 'PayMongo'); ?>
                <input type="hidden" name="save_integration_settings" value="1">
                <div class="form-group" style="margin-bottom: 18px;">
                  <label for="payment_gateway">Active Payment Gateway</label>
                  <select id="payment_gateway" name="payment_gateway" style="width:100%; padding:10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size:14px; box-sizing:border-box; background:white; height:42px;">
                    <option value="PayMongo" <?php echo $activeGateway === 'PayMongo' ? 'selected' : ''; ?>>PayMongo (GCash, Maya, Cards)</option>
                    <option value="Maya" <?php echo $activeGateway === 'Maya' ? 'selected' : ''; ?>>Maya Business</option>
                    <option value="PayPal" <?php echo $activeGateway === 'PayPal' ? 'selected' : ''; ?>>PayPal Checkout</option>
                  </select>
                </div>
                <div style="text-align: right;">
                  <button type="submit" class="button-primary">Save Integration Settings</button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <aside class="notification-panel">
          <div class="notification-header">
            <div>
              <div class="notification-tab">Notifications</div>
              <p style="margin:8px 0 0; color:#475569;">Pending auto-renewal notifications for this tenant.</p>
            </div>
            <form method="post" action="subscription.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" style="margin:0;">
              <input type="hidden" name="mark_notifications_read" value="1">
              <button type="submit" class="button-primary" style="padding:10px 14px;">Mark all read</button>
            </form>
          </div>
          <div style="margin-top:18px;">
            <?php if ($notificationCount > 0): ?>
              <div class="notification-pill"><?php echo h((string)$notificationCount); ?> unread</div>
            <?php endif; ?>
            <?php if (!empty($pendingNotifications)): ?>
              <?php foreach ($pendingNotifications as $note): ?>
                <div class="notification-item">
                  <div class="notification-title">
                    <span><strong>Renewal attempt <?php echo h((string)$note['attempt_count']); ?></strong></span>
                    <span style="font-size:12px; color:#64748b;"><?php echo h(formatReadableDate($note['created_at'])); ?></span>
                  </div>
                  <p style="margin:10px 0 0; color:#334155;">Status: <?php echo h($note['status']); ?><?php if (!empty($note['next_retry_at'])): ?> — next retry: <?php echo h(formatReadableDate($note['next_retry_at'])); ?><?php endif; ?></p>
                  <?php if (!empty($note['response'])): ?>
                    <details style="margin-top:10px; font-size:13px; color:#475569;">
                      <summary style="cursor:pointer;">View response</summary>
                      <pre style="white-space:pre-wrap; word-break:break-word; margin-top:8px; background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:12px;"><?php echo h(substr((string)$note['response'], 0, 800)); ?></pre>
                    </details>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p style="color:#475569;">No pending renewal notifications at this time.</p>
            <?php endif; ?>
          </div>
        </aside>
      </div>
    </div>
  </div>
</body>
</html>
