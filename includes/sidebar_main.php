<?php
$tenantName = $tenantName ?? $_SESSION['tenant_name'] ?? 'OralSync Clinic';
$tenantSlug = $tenantSlug ?? trim((string)($_GET['tenant'] ?? ''));
$role = strtolower(trim((string)($_SESSION['role'] ?? 'admin')));
$currentPage = basename($_SERVER['PHP_SELF']);

function sidebarActive($currentPage, $pageNames) {
    if (is_array($pageNames)) {
        return in_array($currentPage, $pageNames, true) ? ' active bg-blue-700' : '';
    }
    return $currentPage === $pageNames ? ' active bg-blue-700' : '';
}

$baseTenantQuery = '?tenant=' . rawurlencode($tenantSlug);

$menu = [];

switch ($role) {
    case 'dentist':
        $menu = [
            ['section' => 'Dentist', 'items' => [
                ['href' => '/dentist_dashboard.php' . $baseTenantQuery, 'icon' => '📊', 'label' => 'Dashboard', 'active' => 'dentist_dashboard.php'],
            ]],
            ['section' => 'Core Features', 'items' => [
                ['href' => '/dentist_appointments.php' . $baseTenantQuery, 'icon' => '📅', 'label' => 'Appointments', 'active' => 'dentist_appointments.php'],
                ['href' => '/dentist_patients.php' . $baseTenantQuery, 'icon' => '👥', 'label' => 'Patients', 'active' => 'dentist_patients.php'],
                ['href' => '/dentist_schedule.php' . $baseTenantQuery, 'icon' => '🗓️', 'label' => 'My Schedule', 'active' => 'dentist_schedule.php'],
                ['href' => '/profile_settings.php' . $baseTenantQuery, 'icon' => '⚙️', 'label' => 'Profile', 'active' => 'profile_settings.php'],
            ]],
        ];
        $logoutLink = '/dentist_logout.php' . $baseTenantQuery;
        break;
    case 'receptionist':
        $menu = [
            ['section' => 'Receptionist', 'items' => [
                ['href' => '/receptionist_dashboard.php' . $baseTenantQuery, 'icon' => '📊', 'label' => 'Dashboard', 'active' => 'receptionist_dashboard.php'],
            ]],
            ['section' => 'Core Features', 'items' => [
                ['href' => '/receptionist_patients.php' . $baseTenantQuery, 'icon' => '👥', 'label' => 'Patients', 'active' => 'receptionist_patients.php'],
                ['href' => '/receptionist_appointments.php' . $baseTenantQuery, 'icon' => '📅', 'label' => 'Appointments', 'active' => 'receptionist_appointments.php'],
                ['href' => '/receptionist_billing.php' . $baseTenantQuery, 'icon' => '💳', 'label' => 'Billing', 'active' => 'receptionist_billing.php'],
                ['href' => '/profile_settings.php' . $baseTenantQuery, 'icon' => '⚙️', 'label' => 'Profile', 'active' => 'profile_settings.php'],
            ]],
        ];
        $logoutLink = '/receptionist_logout.php' . $baseTenantQuery;
        break;
    default:
        $menu = [
            ['section' => 'Main', 'items' => [
                ['href' => '/dashboard.php' . $baseTenantQuery, 'icon' => '📊', 'label' => 'Dashboard', 'active' => 'dashboard.php'],
            ]],
            ['section' => 'Core Features', 'items' => [
                ['href' => '/patients.php' . $baseTenantQuery, 'icon' => '👥', 'label' => 'Patients', 'active' => 'patients.php'],
                ['href' => '/appointments.php' . $baseTenantQuery, 'icon' => '📅', 'label' => 'Appointments', 'active' => 'appointments.php'],
                ['href' => '/billing.php' . $baseTenantQuery, 'icon' => '💳', 'label' => 'Billing', 'active' => 'billing.php'],
            ]],
            ['section' => 'Management', 'items' => [
                ['href' => '/users.php' . $baseTenantQuery, 'icon' => '👤', 'label' => 'Users', 'active' => 'users.php'],
                ['href' => '/staff.php' . $baseTenantQuery, 'icon' => '👨‍⚕️', 'label' => 'Staff', 'active' => 'staff.php'],
                ['href' => '/services.php' . $baseTenantQuery, 'icon' => '🦷', 'label' => 'Services', 'active' => 'services.php'],
                ['href' => '/clinic_schedule.php' . $baseTenantQuery, 'icon' => '🗓️', 'label' => 'Clinic Availability', 'active' => 'clinic_schedule.php'],
                ['href' => '/reports.php' . $baseTenantQuery, 'icon' => '📈', 'label' => 'Reports', 'active' => 'reports.php'],
                ['href' => '/settings.php' . $baseTenantQuery, 'icon' => '⚙️', 'label' => 'Settings', 'active' => 'settings.php'],
            ]],
        ];
        $logoutLink = '/tenant_logout.php' . $baseTenantQuery;
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
