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

if ($paymentMethodsTableExists) {
    $stmt = $conn->prepare(
        'SELECT provider, brand, last4, exp_month, exp_year, billing_contact
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
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    } else {
        $errorMessage = 'No active subscription record was found to update.';
    }
}

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
    .form-checkbox { display: flex; align-items: center; gap: 12px; margin-top: 20px; }
    .form-checkbox input { width: 18px; height: 18px; }
    .button-primary { border: none; border-radius: 14px; background: #0d3b66; color: white; padding: 12px 22px; cursor: pointer; font-weight: 700; }
    .button-primary:hover { background: #0a2d4f; }
    .payment-method { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 16px; padding: 18px; }
    .payment-method p { margin: 4px 0; }
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

      <div class="subscription-card">
        <h2>Subscription Details</h2>
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
            <h3>Next renewal</h3>
            <p><?php echo h(formatReadableDate($subscription['current_period_end'] ?? null)); ?></p>
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

          <div class="subscription-item subscription-card" style="grid-column: 1 / -1;">
            <h3>Payment method</h3>
            <?php if ($currentPaymentMethod): ?>
              <div class="payment-method">
                <p><strong><?php echo h($currentPaymentMethod['provider']); ?></strong></p>
                <p><?php echo h($currentPaymentMethod['brand'] ?? 'Card'); ?> ending in <strong><?php echo h($currentPaymentMethod['last4'] ?? '0000'); ?></strong></p>
                <p>Expires <?php echo h(sprintf('%02d/%04d', $currentPaymentMethod['exp_month'] ?? 0, $currentPaymentMethod['exp_year'] ?? 0)); ?></p>
              </div>
            <?php else: ?>
              <p style="color: #475569;">No saved payment method is available. You can still manage your subscription settings here.</p>
            <?php endif; ?>
          </div>

          <div style="grid-column: 1 / -1; text-align: right; margin-top: 16px;">
            <button type="submit" class="button-primary">Save Subscription Settings</button>
          </div>
        </form>

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
  </div>
</body>
</html>
