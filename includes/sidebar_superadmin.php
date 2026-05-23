<?php
$currentFile = basename($_SERVER['PHP_SELF']);

function saMenuActive(string $page): string {
    $currentFile = basename($_SERVER['PHP_SELF']);
    return $currentFile === $page ? ' menu-item active' : ' menu-item';
}

$currentSettings = [];
$systemName = 'OralSync';
$logoUrl = '';
if (function_exists('getAllSettings')) {
    $currentSettings = getAllSettings();
    $systemName = trim($currentSettings['system_name'] ?? $systemName) ?: $systemName;
    $logoPath = trim($currentSettings['logo_path'] ?? '');
    if ($logoPath !== '') {
        $logoUrl = '/' . ltrim($logoPath, '/');
    }
}
?>
<aside class="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-logo" style="display: flex; align-items: center; gap: 12px; padding: 24px 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
            <div style="font-size: 32px; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; overflow: hidden; border-radius: 4px; background: #ffffff;">
                <?php 
                $logoPath = trim($currentSettings['logo_path'] ?? '');
                if ($logoPath !== ''): 
                    // Check if logo file exists
                    $fullPath = ltrim($logoPath, '/');
                    $localPath = __DIR__ . '/../' . $fullPath;
                    if (file_exists($localPath)): 
                ?>
                    <img src="<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>?t=<?php echo time(); ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
                <?php else: ?>
                    🏥
                <?php endif; ?>
                <link rel="stylesheet" href="tenant_style.css">

                <nav class="tenant-sidebar">
                    <div class="sidebar-header">
                        <div class="sidebar-logo">
                            <div class="sidebar-logo-icon">
                                <?php 
                                $logoPath = trim($currentSettings['logo_path'] ?? '');
                                if ($logoPath !== ''):
                                    $fullPath = ltrim($logoPath, '/');
                                    $localPath = __DIR__ . '/../' . $fullPath;
                                    if (file_exists($localPath)):
                                ?>
                                    <img src="<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>?t=<?php echo time(); ?>" alt="Logo" style="width:100%;height:100%;object-fit:contain;border-radius:4px;">
                                <?php else: ?>
                                    🏥
                                <?php endif; ?>
                                <?php else: ?>
                                    🏥
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="sidebar-logo-text"><?php echo htmlspecialchars($systemName, ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="sidebar-clinic-name">Super Admin</div>
                                <div style="font-size:11px;color:rgba(255,255,255,0.95);font-weight:500;margin-top:2px">@<?php echo htmlspecialchars($_SESSION['superadmin_username'] ?? 'admin', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="sidebar-nav">
                        <a href="superadmin_dash.php" class="sidebar-nav-item<?php echo saMenuActive('superadmin_dash.php'); ?>"><span class="sidebar-nav-icon">🛡️</span><span>Dashboard</span></a>
                        <a href="superadmin_dash.php#tenant-section" class="sidebar-nav-item"><span class="sidebar-nav-icon">🏥</span><span>Tenant List</span></a>
                        <a href="superadmin_tenant_reports.php" class="sidebar-nav-item<?php echo saMenuActive('superadmin_tenant_reports.php'); ?>"><span class="sidebar-nav-icon">📈</span><span>Tenant Reports</span></a>
                        <a href="superadmin_sales_reports.php" class="sidebar-nav-item<?php echo saMenuActive('superadmin_sales_reports.php'); ?>"><span class="sidebar-nav-icon">💰</span><span>Sales Reports</span></a>
                        <a href="superadmin_audit_logs.php" class="sidebar-nav-item<?php echo saMenuActive('superadmin_audit_logs.php'); ?>"><span class="sidebar-nav-icon">📋</span><span>Audit Logs</span></a>
                        <a href="superadmin_create_superadmin.php" class="sidebar-nav-item<?php echo saMenuActive('superadmin_create_superadmin.php'); ?>"><span class="sidebar-nav-icon">👤</span><span>Create Super Admin</span></a>
                        <a href="superadmin_settings.php" class="sidebar-nav-item<?php echo saMenuActive('superadmin_settings.php'); ?>"><span class="sidebar-nav-icon">⚙️</span><span>Settings</span></a>
                    </div>

                    <div class="sidebar-footer">
                        <a href="logout.php" class="sidebar-logout-btn"><span>🚪</span><span>Sign Out</span></a>
                    </div>
                </nav>
