<?php
require_once __DIR__ . '/tenant_utils.php';
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

$menu = [];

switch ($role) {
    case 'dentist':
        $menu = [
            ['section' => 'Dentist', 'items' => [
                ['href' => $basePath . '/dentist_dashboard.php' . $baseTenantQuery, 'icon' => '📊', 'label' => 'Dashboard', 'active' => 'dentist_dashboard.php'],
            ]],
            ['section' => 'Core Features', 'items' => [
                ['href' => $basePath . '/dentist_appointments.php' . $baseTenantQuery, 'icon' => '📅', 'label' => 'Appointments', 'active' => 'dentist_appointments.php'],
                ['href' => $basePath . '/dentist_patients.php' . $baseTenantQuery, 'icon' => '👥', 'label' => 'Patients', 'active' => 'dentist_patients.php'],
                ['href' => $basePath . '/dentist_schedule.php' . $baseTenantQuery, 'icon' => '🗓️', 'label' => 'My Schedule', 'active' => 'dentist_schedule.php'],
                ['href' => $basePath . '/profile_settings.php' . $baseTenantQuery, 'icon' => '⚙️', 'label' => 'Profile', 'active' => 'profile_settings.php'],
            ]],
        ];
        $logoutLink = $basePath . '/dentist_logout.php' . $baseTenantQuery;
        break;
    case 'receptionist':
        $menu = [
            ['section' => 'Receptionist', 'items' => [
                ['href' => $basePath . '/receptionist_dashboard.php' . $baseTenantQuery, 'icon' => '📊', 'label' => 'Dashboard', 'active' => 'receptionist_dashboard.php'],
            ]],
            ['section' => 'Core Features', 'items' => [
                ['href' => $basePath . '/receptionist_patients.php' . $baseTenantQuery, 'icon' => '👥', 'label' => 'Patients', 'active' => 'receptionist_patients.php'],
                ['href' => $basePath . '/receptionist_appointments.php' . $baseTenantQuery, 'icon' => '📅', 'label' => 'Appointments', 'active' => 'receptionist_appointments.php'],
                ['href' => $basePath . '/receptionist_billing.php' . $baseTenantQuery, 'icon' => '💳', 'label' => 'Billing', 'active' => 'receptionist_billing.php'],
                ['href' => $basePath . '/profile_settings.php' . $baseTenantQuery, 'icon' => '⚙️', 'label' => 'Profile', 'active' => 'profile_settings.php'],
            ]],
        ];
        $logoutLink = $basePath . '/receptionist_logout.php' . $baseTenantQuery;
        break;
    default:
        $menu = [
            ['section' => 'Main', 'items' => [
                ['href' => $basePath . '/dashboard.php' . $baseTenantQuery, 'icon' => '📊', 'label' => 'Dashboard', 'active' => 'dashboard.php'],
            ]],
            ['section' => 'Core Features', 'items' => [
                ['href' => $basePath . '/patients.php' . $baseTenantQuery, 'icon' => '👥', 'label' => 'Patients', 'active' => 'patients.php'],
                ['href' => $basePath . '/appointments.php' . $baseTenantQuery, 'icon' => '📅', 'label' => 'Appointments', 'active' => 'appointments.php'],
                ['href' => $basePath . '/billing.php' . $baseTenantQuery, 'icon' => '💳', 'label' => 'Billing', 'active' => 'billing.php'],
            ]],
            ['section' => 'Management', 'items' => [
                ['href' => $basePath . '/users.php' . $baseTenantQuery, 'icon' => '👤', 'label' => 'Users', 'active' => 'users.php'],
                ['href' => $basePath . '/staff.php' . $baseTenantQuery, 'icon' => '👨‍⚕️', 'label' => 'Staff', 'active' => 'staff.php'],
                ['href' => $basePath . '/services.php' . $baseTenantQuery, 'icon' => '🦷', 'label' => 'Services', 'active' => 'services.php'],
                ['href' => $basePath . '/clinic_schedule.php' . $baseTenantQuery, 'icon' => '🗓️', 'label' => 'Clinic Availability', 'active' => 'clinic_schedule.php'],
                ['href' => $basePath . '/reports.php' . $baseTenantQuery, 'icon' => '📈', 'label' => 'Reports', 'active' => 'reports.php'],
                ['href' => $basePath . '/settings.php' . $baseTenantQuery, 'icon' => '⚙️', 'label' => 'Settings', 'active' => 'settings.php'],
            ]],
        ];
        $logoutLink = $basePath . '/tenant_logout.php' . $baseTenantQuery;
        break;
}
?>
<nav class="tenant-sidebar">
    <div class="sidebar-header h-20" style="height: 80px; min-height: 80px;">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon" style="font-size: 24px; font-weight: 900; color: #0d3b66;">🏥</div>
            <div>
                <div class="sidebar-logo-text">OralSync</div>
                <div class="sidebar-clinic-name"><?php echo htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
    </div>

    <div class="sidebar-nav">
        <?php foreach ($menu as $group): ?>
            <div class="sidebar-section">
                <div class="sidebar-section-title"><?php echo htmlspecialchars($group['section'], ENT_QUOTES, 'UTF-8'); ?></div>
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
