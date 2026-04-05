<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$reportsActive = in_array($currentFile, ['superadmin_reports.php', 'superadmin_sales_report.php'], true);

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
            <a href="/superadmin/superadmin_dash.php" class="<?php echo saMenuActive('superadmin_dash.php'); ?>" data-section="dashboard-section"><span>🛡️</span> Dashboard</a>
            <a href="/superadmin/superadmin_dash.php#tenant-section" class="menu-item" data-section="tenant-section"><span>🏥</span> Tenant List</a>
            <a href="/superadmin/superadmin_dash.php#register-section" class="menu-item" data-section="register-section"><span>➕</span> Register Clinic</a>
            <div class="menu-dropdown" style="position: relative; z-index: 50;">
                <button type="button" class="menu-dropdown-toggle<?php echo $reportsActive ? ' active' : ''; ?>" style="width: 100%; text-align: left;">
                    <span>📈</span> Reports
                </button>
                <div class="menu-dropdown-items" style="position: absolute; left: 0; right: 0; display: <?php echo $reportsActive ? 'flex' : 'none'; ?>; flex-direction: column; z-index: 50; background: rgba(15, 23, 42, 0.98);">
                    <a href="/superadmin/superadmin_reports.php" class="menu-dropdown-item hover:bg-white/10<?php echo $currentFile === 'superadmin_reports.php' ? ' active' : ''; ?>">Tenant Reports</a>
                    <a href="/superadmin/superadmin_sales_report.php" class="menu-dropdown-item hover:bg-white/10<?php echo $currentFile === 'superadmin_sales_report.php' ? ' active' : ''; ?>">Sales Reports</a>
                </div>
            </div>
            <a href="/superadmin/superadmin_audit_logs.php" class="<?php echo saMenuActive('superadmin_audit_logs.php'); ?>"><span>📋</span> Audit Logs</a>
            <a href="/superadmin/superadmin_settings.php" class="<?php echo saMenuActive('superadmin_settings.php'); ?>"><span>⚙️</span> Settings</a>
        </nav>
    </div>
    <div class="sidebar-bottom">
        <a href="/logout.php" class="sign-out"><span>🚪</span> Sign Out</a>
    </div>
</aside>
