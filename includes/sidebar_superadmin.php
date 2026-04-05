<?php
$currentFile = basename($_SERVER['PHP_SELF']);

function saMenuActive(string $page): string {
    $currentFile = basename($_SERVER['PHP_SELF']);
    return $currentFile === $page ? ' menu-item active' : ' menu-item';
}
?>
<aside class="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-logo" style="display: flex; align-items: center; gap: 12px; padding: 24px 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); height: 80px;">
            <div style="font-size: 32px; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; overflow: hidden; border-radius: 4px;">🏥</div>
            <div>
                <div class="sidebar-logo-text" style="margin: 0; font-size: 18px; font-weight: 700;">OralSync</div>
                <div style="font-size: 12px; color: rgba(255, 255, 255, 0.7);">Super Admin</div>
            </div>
        </div>
        <nav class="menu">
            <a href="superadmin_dash.php" class="<?php echo saMenuActive('superadmin_dash.php'); ?>" data-section="dashboard-section"><span>🛡️</span> Dashboard</a>
            <a href="superadmin_dash.php#tenant-section" class="menu-item" data-section="tenant-section"><span>🏥</span> Tenant List</a>
            <a href="superadmin_dash.php#register-section" class="menu-item" data-section="register-section"><span>➕</span> Register Clinic</a>
            <a href="superadmin_tenant_reports.php" class="<?php echo saMenuActive('superadmin_tenant_reports.php'); ?>"><span>📈</span> Tenant Reports</a>
            <a href="superadmin_sales_reports.php" class="<?php echo saMenuActive('superadmin_sales_reports.php'); ?>"><span>💰</span> Sales Reports</a>
            <a href="superadmin_audit_logs.php" class="<?php echo saMenuActive('superadmin_audit_logs.php'); ?>"><span>📋</span> Audit Logs</a>
            <a href="superadmin_settings.php" class="<?php echo saMenuActive('superadmin_settings.php'); ?>"><span>⚙️</span> Settings</a>
        </nav>
    </div>
    <div class="sidebar-bottom">
        <a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a>
    </div>
</aside>
