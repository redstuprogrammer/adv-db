<?php
require_once __DIR__ . '/tenant_utils.php';
require_once __DIR__ . '/tenant_tier_helper.php';
$tenantName = $tenantName ?? $_SESSION['tenant_name'] ?? 'OralSync Clinic';
$tenantSlug = $tenantSlug ?? trim((string)($_GET['tenant'] ?? ''));

// Resolve role from SessionManager (supports multi-tab/multi-role sessions).
// Fall back to the flat $_SESSION['role'] key only for legacy compatibility.
$role = 'admin';
if (class_exists('SessionManager')) {
    $sm = SessionManager::getInstance();
    $resolvedRole = $sm->getCurrentRole();
    if ($resolvedRole && $resolvedRole !== 'superadmin') {
        $role = strtolower($resolvedRole);
    }
} else {
    $role = strtolower(trim((string)($_SESSION['role'] ?? 'admin')));
}
$currentPage = basename($_SERVER['PHP_SELF']);

function sidebarActive($currentPage, $pageNames) {
    if (is_array($pageNames)) {
        return in_array($currentPage, $pageNames, true) ? ' active bg-blue-700' : '';
    }
    return $currentPage === $pageNames ? ' active bg-blue-700' : '';
}

$basePath = getAppBasePath();
$baseTenantQuery = '?tenant=' . rawurlencode($tenantSlug);
$tenantIdForMenu = getCurrentTenantId();
$canUseBilling = $tenantIdForMenu ? tenantHasTierFeature((int)$tenantIdForMenu, 'payment_tracking', $conn ?? null) : true;
$canUseReports = $tenantIdForMenu ? tenantHasTierFeature((int)$tenantIdForMenu, 'basic_reporting', $conn ?? null) : true;

$menu = [];

switch ($role) {
    case 'dentist':
        $menu = [
            ['section' => 'Dentist', 'items' => [
                ['href' => $basePath . '/dentist_dashboard.php' . $baseTenantQuery, 'icon' => '📊', 'label' => 'Dashboard', 'active' => 'dentist_dashboard.php'],
            ]],
            ['section' => '', 'items' => [
                ['href' => $basePath . '/dentist_appointments.php' . $baseTenantQuery, 'icon' => '📅', 'label' => 'Appointments', 'active' => 'dentist_appointments.php'],
                ['href' => $basePath . '/dentist_patients.php' . $baseTenantQuery, 'icon' => '👥', 'label' => 'Patients', 'active' => 'dentist_patients.php'],
                ['href' => $basePath . '/dentist_schedule.php' . $baseTenantQuery, 'icon' => '🗓️', 'label' => 'My Schedule', 'active' => 'dentist_schedule.php'],
                ['href' => $basePath . '/dentist_account_settings.php' . $baseTenantQuery, 'icon' => '🔐', 'label' => 'Account Settings', 'active' => 'dentist_account_settings.php'],
            ]],
        ];
        $logoutLink = $basePath . '/dentist_logout.php' . $baseTenantQuery;
        break;
    case 'receptionist':
        $menu = [
            ['section' => 'Receptionist', 'items' => [
                ['href' => $basePath . '/receptionist_dashboard.php' . $baseTenantQuery, 'icon' => '📊', 'label' => 'Dashboard', 'active' => 'receptionist_dashboard.php'],
            ]],
            ['section' => '', 'items' => [
                ['href' => $basePath . '/receptionist_patients.php' . $baseTenantQuery, 'icon' => '👥', 'label' => 'Patients', 'active' => 'receptionist_patients.php'],
                ['href' => $basePath . '/receptionist_appointments.php' . $baseTenantQuery, 'icon' => '📅', 'label' => 'Appointments', 'active' => 'receptionist_appointments.php'],
                ...($canUseBilling ? [['href' => $basePath . '/receptionist_billing.php' . $baseTenantQuery, 'icon' => '💳', 'label' => 'Billing', 'active' => 'receptionist_billing.php']] : []),
                ['href' => $basePath . '/receptionist_account_settings.php' . $baseTenantQuery, 'icon' => '🔐', 'label' => 'Account Settings', 'active' => 'receptionist_account_settings.php'],
            ]],
        ];
        $logoutLink = $basePath . '/receptionist_logout.php' . $baseTenantQuery;
        break;
    default:
        $menu = [
            ['section' => '', 'items' => [
                ['href' => $basePath . '/dashboard.php' . $baseTenantQuery, 'icon' => '📊', 'label' => 'Dashboard', 'active' => 'dashboard.php'],
            ]],
            ['section' => '', 'items' => [
                ['href' => $basePath . '/patients.php' . $baseTenantQuery, 'icon' => '👥', 'label' => 'Patients', 'active' => 'patients.php'],
                ['href' => $basePath . '/appointments.php' . $baseTenantQuery, 'icon' => '📅', 'label' => 'Appointments', 'active' => 'appointments.php'],
                ...($canUseBilling ? [['href' => $basePath . '/billing.php' . $baseTenantQuery, 'icon' => '💳', 'label' => 'Billing', 'active' => 'billing.php']] : []),
                ['href' => $basePath . '/subscription.php' . $baseTenantQuery, 'icon' => '🧾', 'label' => 'Subscription', 'active' => 'subscription.php'],
            ]],
            ['section' => '', 'items' => [
                ['href' => $basePath . '/users.php' . $baseTenantQuery, 'icon' => '👤', 'label' => 'Users', 'active' => 'users.php'],
                ['href' => $basePath . '/staff.php' . $baseTenantQuery, 'icon' => '👨‍⚕️', 'label' => 'Staff', 'active' => 'staff.php'],
                ['href' => $basePath . '/services.php' . $baseTenantQuery, 'icon' => '🦷', 'label' => 'Services', 'active' => 'services.php'],
                ['href' => $basePath . '/clinic_schedule.php' . $baseTenantQuery, 'icon' => '🗓️', 'label' => 'Clinic Availability', 'active' => 'clinic_schedule.php'],
                ...($canUseReports ? [['href' => $basePath . '/reports.php' . $baseTenantQuery, 'icon' => '📈', 'label' => 'Reports', 'active' => 'reports.php']] : []),
                ['href' => $basePath . '/settings.php' . $baseTenantQuery, 'icon' => '⚙️', 'label' => 'Settings', 'active' => 'settings.php'],
            ]],
        ];
        $logoutLink = $basePath . '/tenant_logout.php' . $baseTenantQuery;
        break;
}
?>
<nav class="tenant-sidebar">
    <div class="sidebar-header h-20">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon" style="font-size: 24px; font-weight: 900; color: #0d3b66;">🏥</div>
            <div>
                <div class="sidebar-logo-text">OralSync</div>
                <div class="sidebar-clinic-name"><?php echo htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8'); ?></div>
                <div style="font-size: 10px; color: rgba(255, 255, 255, 0.95); font-weight: 600; text-transform: uppercase; margin-top: 2px;">
                    👤 <?php echo htmlspecialchars(SessionManager::getInstance()->getUsername() ?? 'User', ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="sidebar-nav">
        <?php foreach ($menu as $group): ?>
            <div class="sidebar-section">
                <?php if (!empty(trim((string)($group['section'] ?? '')))): ?>
                    <div class="sidebar-section-title"><?php echo htmlspecialchars($group['section'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php foreach ($group['items'] as $item): ?>
                    <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-nav-item<?php echo sidebarActive($currentPage, $item['active']); ?>">
                        <span class="sidebar-nav-icon"><?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="sidebar-footer">
        <a href="<?php echo htmlspecialchars($logoutLink, ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-logout-btn">
            <span>🚪</span>
            <span>Sign Out</span>
        </a>
    </div>
</nav>

<script>
<?php 
if (function_exists('printDateClockScript')) {
    printDateClockScript();
} 
?>
</script>
<?php include_once __DIR__ . '/toast_notification.php'; ?>
