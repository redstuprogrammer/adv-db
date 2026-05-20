<?php
// Extend session timeout for superadmin
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

define('ROOT_PATH', __DIR__ . '/');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}
require_once ROOT_PATH . 'includes/security_headers.php';
require_once ROOT_PATH . 'includes/session_utils.php';

// Check auth state FIRST, before loading database
$sessionManager = SessionManager::getInstance();
$sessionManager->requireSuperAdmin();

// Load settings and database after auth check
require_once ROOT_PATH . 'settings.php';
require_once ROOT_PATH . 'includes/subscription_tiers.php';
try {
    $currentSettings = getAllSettings();
} catch (Exception $e) {
    $currentSettings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($currentSettings['system_name'] ?? 'OralSync', ENT_QUOTES, 'UTF-8'); ?> | Super Admin</title>
    <link rel="stylesheet" href="style1.css">
    <link rel="stylesheet" href="tenant_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --sa-primary: #0d3b66;
            --sa-muted: #64748b;
            --sa-border: #e2e8f0;
            --sa-bg: #f8fafc;
        }

        body {
            background-color: var(--sa-bg);
            color: #0f172a;
        }

        .sa-main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            margin-bottom: 10px;
        }

        .sa-main-header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--sa-primary);
            margin: 0;
        }

        .sa-main-header span {
            font-size: 0.85rem;
            color: var(--sa-muted);
        }

        .sa-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: var(--sa-muted);
        }

        .sa-profile-avatar {
            width: 35px;
            height: 35px;
            border-radius: 999px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sa-section {
            display: none;
        }

        .sa-section.active-section {
            display: block;
        }

        .sa-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--sa-border);
            padding: 28px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08);
            transition: all 0.2s ease;
        }

        .sa-card:hover {
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.12);
            border-color: #0d3b66;
        }

        .sa-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--sa-border);
            padding-bottom: 16px;
        }

        .sa-card-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0d3b66;
            letter-spacing: -0.3px;
        }

        .sa-card-subtitle {
            font-size: 0.9rem;
            color: var(--sa-muted);
            margin-top: 6px;
            font-weight: 500;
        }

        .sa-empty-state {
            padding: 60px 20px;
            text-align: center;
            color: var(--sa-muted);
            font-size: 1rem;
            background: #f8fafc;
            border-radius: 12px;
            margin: 20px 0;
        }

        .sa-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .sa-pill-active {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #22c55e;
        }

        .sa-pill-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .sa-tenant-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .sa-search-wrapper {
            position: relative;
            min-width: 260px;
            max-width: 360px;
            flex: 1;
        }

        .sa-search-wrapper input {
            width: 100%;
            padding: 11px 40px 11px 36px;
            border-radius: 999px;
            border: 2px solid #cbd5e1;
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s ease;
        }

        .sa-search-wrapper input:focus {
            border-color: #0d3b66;
            box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.1);
            background: #f8fafc;
        }

        .sa-search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.9rem;
            color: var(--sa-muted);
        }

        .sa-filter-select {
            padding: 9px 12px;
            border-radius: 999px;
            border: 1px solid var(--sa-border);
            font-size: 0.8rem;
            background: #ffffff;
        }

        .sa-tenant-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .sa-tenant-table th {
            text-align: left;
            padding: 13px 16px;
            border-bottom: 2px solid var(--sa-border);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
            background: #f8fafc;
        }

        .sa-tenant-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .sa-tenant-table tr:hover td {
            background: #f8fafc;
        }

        .sa-tenant-name {
            font-weight: 700;
            color: #0d3b66;
            font-size: 0.95rem;
        }

        .sa-tenant-meta {
            display: block;
            font-size: 0.8rem;
            color: var(--sa-muted);
            margin-top: 4px;
        }

        .sa-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 10px 18px;
            transition: all 0.2s ease;
        }

        .sa-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .sa-btn:active {
            transform: translateY(0);
        }

        .sa-btn-outline {
            background: #ffffff;
            border: 2px solid var(--sa-border);
            color: #0f172a;
        }

        .sa-btn-outline:hover {
            border-color: #0d3b66;
            background: #f8fafc;
        }

        .sa-btn-danger {
            background: #fee2e2;
            color: #b91c1c;
            border: 2px solid #fecaca;
        }

        .sa-btn-danger:hover {
            background: #fca5a5;
            border-color: #dc2626;
        }

        .sa-btn-success {
            background: #0d3b66;
            color: #ffffff;
            border: 2px solid #0d3b66;
        }

        .sa-btn-success:hover {
            background: #0b2c4d;
            border-color: #0b2c4d;
        }

        .sa-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #0f172a;
            color: #f9fafb;
            padding: 12px 18px;
            border-radius: 999px;
            font-size: 0.8rem;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.25);
            opacity: 0;
            transform: translateY(-10px);
            pointer-events: none;
            transition: all 0.2s ease;
            z-index: 50;
        }

        .sa-toast.show {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .sa-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px 24px;
            margin-top: 20px;
        }

        .sa-form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 700;
            color: #0d3b66;
            margin-bottom: 8px;
        }

        .sa-form-group input,
        .sa-form-group select,
        .sa-form-group textarea {
            width: 100%;
            padding: 11px 13px;
            border-radius: 10px;
            border: 1px solid var(--sa-border);
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s ease;
        }

        .sa-form-group input:focus,
        .sa-form-group select:focus,
        .sa-form-group textarea:focus {
            border-color: #0d3b66;
            box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.1);
            background: #f8fafc;
        }

        .sa-form-group textarea {
            resize: vertical;
            min-height: 70px;
        }

        .sa-form-actions {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            border-top: 2px solid var(--sa-border);
            padding-top: 20px;
        }

        .sa-badge-required {
            color: #b91c1c;
        }

        .sa-success-panel {
            margin-top: 20px;
            padding: 16px 18px;
            border-radius: 12px;
            border: 2px solid #22c55e;
            background: #f0fdf4;
            font-size: 0.9rem;
            display: none;
        }

        .sa-success-panel strong {
            color: #166534;
            font-weight: 700;
        }

        .sa-success-actions {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .sa-link-sample {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.8rem;
            color: #0f172a;
            background: #f9fafb;
            padding: 6px 8px;
            border-radius: 8px;
            display: inline-block;
            margin-top: 4px;
        }

        .sa-note {
            font-size: 0.75rem;
            color: var(--sa-muted);
            margin-top: 4px;
        }
        .sa-tenant-link {
            text-decoration: none;
            color: inherit; /* Keeps the text color from your dashboard theme */
            transition: color 0.2s ease;
        }

        .sa-tenant-link:hover .sa-tenant-name {
            color: #007bff; /* Or your preferred brand primary color */
            text-decoration: underline;
        }

        .sa-tenant-info {
            display: flex;
            flex-direction: column;
        }

        /* Hide green circle indicators */
        .green-circle, .status-dot, .status-blob {
            display: none !important;
        }
                    .clickable-row {
                cursor: pointer;
                transition: background 0.2s ease;
            }

            .clickable-row:hover {
                background-color: #f1f5f9 !important; /* Matches your UI's slate theme */
            }
            .sa-modal-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.7);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000;
}

.modal-content {
    width: 90%; max-width: 600px;
    background: white;
    border-radius: 12px;
    overflow: hidden;
}

.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    padding: 20px;
}

.close-modal {
    background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;
}
#details-modal {
    display: none; /* JS toggles this to 'flex' */
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.7);
    z-index: 999;
    align-items: center;
    justify-content: center;
}

/* Dropdown Menu Styles */
.menu-dropdown {
    position: relative;
    width: 100%;
}

.menu-dropdown-toggle {
    width: 100%;
    background: none;
    border: none;
    color: #ffffff;
    padding: 12px 16px;
    text-align: left;
    font-size: 0.95rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
    transition: background-color 0.2s;
    margin: 0;
    font-family: inherit;
    border-left: 3px solid transparent;
}

.menu-dropdown-toggle:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

.menu-dropdown-toggle::after {
    content: '▸';
    margin-left: auto;
    transition: transform 0.2s ease;
}

.menu-dropdown-toggle.active {
    background-color: rgba(13, 59, 102, 0.5);
    border-left: 3px solid #22c55e;
}

.menu-dropdown-toggle.active::after {
    transform: rotate(90deg);
}

.menu-dropdown {
    position: relative;
}

.menu {
    padding: 16px 0;
    overflow: visible;
}

.menu-dropdown-items {
    background-color: rgba(255, 255, 255, 0.05);
    border-left: 3px solid #22c55e;
    overflow: hidden;
    flex-direction: column;
    position: relative;
    z-index: 10;
}

.menu-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px 12px 32px;
    color: #ffffff;
    text-decoration: none;
    font-size: 0.9rem;
    transition: background-color 0.15s;
    overflow: hidden;
}

.menu-dropdown-item:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.clickable-row { cursor: pointer; transition: background 0.2s; }
.clickable-row:hover { background-color: #f8fafc; }
    </style>
</head>
<body>

<div class="container">
    <?php include __DIR__ . '/includes/sidebar_superadmin.php'; ?>

    <main class="main-content">
        <header class="sa-main-header">
            <div>
                <h1>Super Admin Control</h1>
                <div style="display: flex; align-items: center; gap: 12px; margin-top: 4px;">
                    <span>Manage clinics and onboard new tenants for OralSync.</span>
                    <a href="https://oralsync3-g6hpg2fhdyfuagdy.eastasia-01.azurewebsites.net/Landing%20Page/code.html" target="_blank" class="sa-pill sa-pill-active" style="text-decoration: none; display: flex; align-items: center; gap: 4px;">
                        <span style="font-size: 14px;">🌐</span> View Homepage
                    </a>
                </div>
            </div>
            <div class="sa-profile">
                <span>Welcome, <strong>Super Admin</strong></span>
                <div class="sa-profile-avatar">🛡️</div>
            </div>
        </header>

        <!-- Dashboard Improved -->
        <section id="dashboard-section" class="sa-section active-section">
            <div class="sa-card" style="margin-bottom: 16px;">
                <div class="sa-card-header">
                    <div>
                        <div class="sa-card-title">Dashboard Metrics</div>
                        <div class="sa-card-subtitle">Real-time tenant health and activity summary.</div>
                    </div>
                </div>

                <div class="sa-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
                    <div class="sa-card" style="padding: 16px;">
                        <div style="font-size: 0.85rem; color: var(--sa-muted);">Total Clinics</div>
                        <div id="kpi-total" style="font-size: 2rem; font-weight: 800; color: #0d3b66;">0</div>
                    </div>
                    <div class="sa-card" style="padding: 16px;">
                        <div style="font-size: 0.85rem; color: var(--sa-muted);">Active Clinics</div>
                        <div id="kpi-active" style="font-size: 2rem; font-weight: 800; color: #0d3b66;">0</div>
                    </div>
                    <div class="sa-card" style="padding: 16px;">
                        <div style="font-size: 0.85rem; color: var(--sa-muted);">Inactive Clinics</div>
                        <div id="kpi-inactive" style="font-size: 2rem; font-weight: 800; color: #0d3b66;">0</div>
                    </div>
                    <div class="sa-card" style="padding: 16px;">
                        <div style="font-size: 0.85rem; color: var(--sa-muted);">New This Month</div>
                        <div id="kpi-new-month" style="font-size: 2rem; font-weight: 800; color: #0d3b66;">0</div>
                    </div>
                </div>
            </div>

            <div class="sa-card" style="margin-bottom: 20px;">
                <div class="sa-card-header">
                    <div>
                        <div class="sa-card-title">Activity Trend</div>
                        <div class="sa-card-subtitle">Last 7 days vs today volume</div>
                    </div>
                </div>
                <div style="margin-top: 10px; font-size: 0.82rem; color: var(--sa-muted);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Last 7 Days</span> <span id="trend-desc-7d">0 events</span>
                    </div>
                    <div style="background: #f1f5f9; border-radius: 999px; height: 10px; margin-top: 6px; overflow:hidden;">
                        <div id="trend-bar-7d" style="width: 0%; height: 100%; background: #0d3b66;"></div>
                    </div>
                </div>
                <div style="margin-top: 12px; font-size: 0.82rem; color: var(--sa-muted);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Today</span> <span id="trend-desc-today">0 events</span>
                    </div>
                    <div style="background: #f1f5f9; border-radius: 999px; height: 10px; margin-top: 6px; overflow:hidden;">
                        <div id="trend-bar-today" style="width: 0%; height: 100%; background: #16a34a;"></div>
                    </div>
                </div>
            </div>

            <!-- Sales Trends Chart -->
            <div class="sa-card" style="margin-bottom: 20px;">
                <div class="sa-card-header">
                    <div>
                        <div class="sa-card-title">Sales Trends</div>
                        <div class="sa-card-subtitle">Monthly subscription sales</div>
                    </div>
                </div>
                <div style="position: relative; height: 250px;">
                    <canvas id="dashboardSalesChart"></canvas>
                </div>
            </div>

            <div class="sa-card" style="margin-bottom: 20px;">
                <div class="sa-card-header">
                    <div>
                        <div class="sa-card-title">Tenant Overview</div>
                        <div class="sa-card-subtitle">Recent clinics and their status</div>
                    </div>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <table class="sa-tenant-table" id="mini-tenant-table">
                        <thead>
                            <tr>
                                <th>Clinic Name</th>
                                <th>Owner Name</th>
                                <th>Status</th>
                                <th>View</th>
                            </tr>
                        </thead>
                        <tbody id="mini-tenant-table-body"></tbody>
                    </table>
                </div>
            </div>

            <div class="sa-card" style="margin-bottom: 20px;">
                <div class="sa-card-header">
                    <div>
                        <div class="sa-card-title">Super Admin Daily Activity</div>
                        <div class="sa-card-subtitle">Super admin activities over the last 7 days</div>
                    </div>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <table class="sa-tenant-table" id="superadmin-activity-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Activities</th>
                            </tr>
                        </thead>
                        <tbody id="superadmin-activity-table-body"></tbody>
                    </table>
                </div>
            </div>

            <div class="sa-card" style="margin-bottom: 20px;">
                <div class="sa-card-header">
                    <div>
                        <div class="sa-card-title">Tenant Daily Activity</div>
                        <div class="sa-card-subtitle">Tenant activities over the last 7 days</div>
                    </div>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <table class="sa-tenant-table" id="tenant-activity-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Activities</th>
                            </tr>
                        </thead>
                        <tbody id="tenant-activity-table-body"></tbody>
                    </table>
                </div>
            </div>

            <div class="sa-card" style="margin-bottom: 20px;">
                <div class="sa-card-header">
                    <div>
                        <div class="sa-card-title">Analytics Charts</div>
                        <div class="sa-card-subtitle">User growth and tenant activity trends</div>
                    </div>
                </div>
                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <canvas id="growthChart"></canvas>
                    </div>
                    <div style="flex: 1;">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            <!-- System Announcements Feed for Super Admin -->
            <div class="sa-card" style="margin-bottom: 20px;">
                <div class="sa-card-header" style="border-bottom: 1.5px solid var(--sa-border); padding-bottom: 15px; margin-bottom: 20px;">
                    <div>
                        <div class="sa-card-title">📢 Live System Announcements</div>
                        <div class="sa-card-subtitle">Active system-wide notifications published by you showing to all clinics.</div>
                    </div>
                </div>
                
                <?php
                $dashboardSystemAnnouncements = [];
                $stmt = $conn->prepare("SELECT * FROM announcements WHERE tenant_id IS NULL AND status = 'active' AND publish_date <= NOW() ORDER BY publish_date DESC, id DESC LIMIT 5");
                if ($stmt) {
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res) {
                        while ($row = $res->fetch_assoc()) {
                            $dashboardSystemAnnouncements[] = $row;
                        }
                    }
                    $stmt->close();
                }
                ?>

                <?php if (empty($dashboardSystemAnnouncements)): ?>
                    <div style="text-align: center; padding: 30px 20px; border: 1px dashed var(--sa-border); border-radius: 12px; background: #f8fafc;">
                        <p style="color: var(--sa-muted); margin: 0; font-size: 0.9rem;">No active system announcements. Go to settings to publish one.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <?php foreach ($dashboardSystemAnnouncements as $ann): ?>
                            <?php
                            // Dynamic category badge color styling for system categories
                            $cat = strtolower($ann['category']);
                            $bg = '#f3e8ff'; // Default light purple
                            $color = '#7e22ce'; // Default purple
                            if (strpos($cat, 'maintenance') !== false) {
                                $bg = '#fef3c7'; // Amber
                                $color = '#b45309';
                            } elseif (strpos($cat, 'update') !== false) {
                                $bg = '#e0f2fe'; // Sky Blue
                                $color = '#0369a1';
                            } elseif (strpos($cat, 'alert') !== false) {
                                $bg = '#fee2e2'; // Light red
                                $color = '#b91c1c';
                            }
                            ?>
                            <div style="padding: 16px; border: 1px solid var(--sa-border); border-radius: 12px; background: #fafbfc; position: relative;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 8px;">
                                    <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: var(--sa-primary);"><?php echo htmlspecialchars($ann['title']); ?></h4>
                                    <span style="font-size: 0.75rem; background: <?php echo $bg; ?>; color: <?php echo $color; ?>; padding: 4px 10px; border-radius: 999px; font-weight: 700; white-space: nowrap;">
                                        <?php echo htmlspecialchars($ann['category']); ?>
                                    </span>
                                </div>
                                <p style="margin: 0 0 10px 0; font-size: 0.85rem; color: #475569; line-height: 1.5; white-space: pre-line;"><?php echo htmlspecialchars($ann['content']); ?></p>
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: var(--sa-muted); border-top: 1px solid #f1f5f9; padding-top: 8px;">
                                    <span>📅 Published: <?php echo date('M d, Y g:i A', strtotime($ann['publish_date'])); ?></span>
                                    <span style="font-weight: 600; display: inline-flex; align-items: center; gap: 4px; color: #0284c7;">
                                        <span style="display:inline-block; width: 6px; height: 6px; border-radius: 50%; background: #0284c7;"></span> Active Feed
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Tenant List -->
        <section id="tenant-section" class="sa-section">
            <div class="sa-card">
                <div class="sa-card-header">
                    <div>
                        <div class="sa-card-title">Tenant Clinics</div>
                        <div class="sa-card-subtitle">Monitor all registered clinics and their current status.</div>
                    </div>
                </div>

                <div class="sa-tenant-toolbar">
                    <div class="sa-search-wrapper">
                        <span class="sa-search-icon">🔍</span>
                        <input type="text" id="clinic-search" placeholder="Search by clinic name, owner, or email...">
                    </div>
                    <div>
                        <select id="status-filter" class="sa-filter-select">
                            <option value="all">All statuses</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div style="overflow-x:auto;">
                    <table class="sa-tenant-table" id="tenant-table">
                        <thead>
                            <tr>
                                <th>Clinic</th>
                                <th>Owner</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="tenant-table-body"></tbody>
                    </table>
                </div>
                <div class="sa-form-actions" style="justify-content: flex-start; gap: 8px; margin-top: 14px;">
                    <button id="prev-page" class="sa-btn sa-btn-outline" type="button">Previous</button>
                    <span id="page-info" style="font-size:0.85rem; color: var(--sa-muted);">Page 1</span>
                    <button id="next-page" class="sa-btn sa-btn-outline" type="button">Next</button>
                </div>
            </div>
        </section>


<div id="details-modal" class="sa-modal-overlay" style="display:none;">
    <div class="sa-card modal-content">
        <div class="sa-card-header">
            <div class="sa-card-title" id="modal-clinic-name">Clinic Details</div>
            <button class="close-modal" onclick="closeDetailsModal()">×</button>
        </div>
        
        <div class="modal-body">
            <div class="details-grid">
                <div class="detail-item"><strong>Owner:</strong> <span id="dt-owner"></span></div>
                <div class="detail-item"><strong>Email:</strong> <span id="dt-email"></span></div>
                <div class="detail-item"><strong>Phone:</strong> <span id="dt-phone"></span></div>
                <div class="detail-item"><strong>Status:</strong> <span id="dt-status"></span></div>
                <div class="detail-item"><strong>Tier:</strong> <span id="dt-tier"></span></div>
                <div class="detail-item"><strong>Homepage:</strong> <span id="dt-homepage"></span></div>
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <strong>Address:</strong> <span id="dt-address"></span>
                </div>
                <div class="detail-item" style="grid-column: 1 / -1; margin-top: 10px;">
                    <strong>Clinic Documents:</strong>
                    <button id="toggle-docs-btn" class="sa-btn sa-btn-outline" style="margin-left: 10px; font-size: 0.8rem; padding: 4px 12px;">📁 Show Documents</button>
                    <div id="dt-documents" style="margin-top: 10px; display: none;">
                        <span class="sa-note">No documents uploaded.</span>
                    </div>
                </div>
                <div class="detail-item" style="grid-column: 1 / -1; margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px;">
                    <strong>Upload New Documents:</strong>
                    <div style="margin-top: 10px; display: flex; gap: 10px; align-items: center;">
                        <input type="file" id="new-tenant-docs" multiple accept=".pdf,.doc,.docx,.jpg,.png" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; flex: 1;">
                        <button id="upload-docs-btn" class="sa-btn sa-btn-success" style="font-size: 0.8rem; padding: 10px 20px;">Upload</button>
                    </div>
                    <p class="sa-note" style="margin-top: 5px;">PDF, Word, or Images (Max 50MB each)</p>
                </div>
            </div>
            <hr>
            <div class="sa-note">Registration Date: <span id="dt-date"></span></div>
        </div>

        <div class="sa-form-actions">
            <button class="sa-btn sa-btn-outline" onclick="closeDetailsModal()">Close</button>
        </div>
    </div>
</div>
<style>
    @keyframes modalSlide {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    #sa-modal-overlay { display: none; }
    #sa-modal-overlay.active { display: flex; }
</style>
<div id="sa-toast" class="sa-toast"></div>

<script>
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdownToggle = document.querySelector('.menu-dropdown-toggle');
        const dropdownItems = document.querySelector('.menu-dropdown-items');
        const dropdown = document.querySelector('.menu-dropdown');
        
        if (dropdown && !dropdown.contains(e.target)) {
            dropdownItems.style.display = 'none';
            if (dropdownToggle) dropdownToggle.classList.remove('active');
        }
    });

    window.addEventListener('load', function() {
        const currentPage = window.location.pathname.toLowerCase();
        const dropdownToggle = document.querySelector('.menu-dropdown-toggle');
        const dropdownItems = document.querySelector('.menu-dropdown-items');
        if ((currentPage.includes('superadmin_reports') || currentPage.includes('superadmin_sales_report') || currentPage.includes('tenant_reports') || currentPage.includes('sales_reports')) && dropdownToggle && dropdownItems) {
            dropdownItems.style.display = 'flex';
            dropdownToggle.classList.add('active');
        }
    });

    // Sidebar navigation between sections
    (function () {
        const menuItems = document.querySelectorAll('.menu-item[data-section]');
        const sections = document.querySelectorAll('.sa-section');
        const dropdownToggle = document.querySelector('.menu-dropdown-toggle');
        const dropdownItems = document.querySelector('.menu-dropdown-items');

        function activateSuperAdminSection(sectionId) {
            if (!sectionId) return;
            const targetSection = document.getElementById(sectionId);
            if (!targetSection) return;

            document.querySelectorAll('.menu-item[data-section]').forEach(mi => mi.classList.remove('active'));
            if (dropdownToggle) dropdownToggle.classList.remove('active');
            if (dropdownItems) dropdownItems.style.display = 'none';

            const menuItem = document.querySelector(`.menu-item[data-section="${sectionId}"]`);
            if (menuItem) {
                menuItem.classList.add('active');
            }

            document.querySelectorAll('.sa-section').forEach(sec => sec.classList.remove('active-section'));
            targetSection.classList.add('active-section');
        }

        function handleSuperAdminHash() {
            const hash = window.location.hash.substring(1);
            if (hash) {
                activateSuperAdminSection(hash);
            }
        }

        menuItems.forEach(item => {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                const target = this.getAttribute('data-section');
                if (!target) return;
                activateSuperAdminSection(target);
                history.replaceState(null, '', '#' + target);
            });
        });

        // Handle URL hash on page load and hash changes
        window.addEventListener('load', handleSuperAdminHash);
        window.addEventListener('hashchange', handleSuperAdminHash);
    })();

    // Dropdown menu toggle
    (function () {
        const dropdownToggle = document.querySelector('.menu-dropdown-toggle');
        const dropdownItems = document.querySelector('.menu-dropdown-items');

        if (dropdownToggle && dropdownItems) {
            dropdownToggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const isVisible = dropdownItems.style.display !== 'none';
                dropdownItems.style.display = isVisible ? 'none' : 'flex';
                dropdownItems.style.flexDirection = 'column';
                dropdownToggle.classList.toggle('active');
            });

            // Prevent dropdown from closing when clicking on dropdown items (allow page navigation)
            dropdownItems.addEventListener('click', function(e) {
                e.stopPropagation();
                // Let the link navigate naturally - don't prevent default
            });
        }
    })();

    function showToast(message, durationMs = 4500) {
        const toast = document.getElementById('sa-toast');
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, durationMs);
    }

    /**
     * CORE FUNCTION: Fetches tenants and builds the table with 
     * required database hooks (data-id and sa-btn-toggle).
     */
    let tenantData = [];
    let filteredData = [];
    let currentPage = 1;
    const rowsPerPage = 8;

    function normalize(text) {
        return (text || '').toString().trim().toLowerCase();
    }

    function computeMetrics(data) {
        const totals = data.length;
        const active = data.filter(t => (t.status || '').toLowerCase() === 'active').length;
        const inactive = totals - active;
        const now = new Date();
        const currentMonth = now.getMonth();
        const currentYear = now.getFullYear();
        const newMonth = data.filter(t => {
            const d = new Date(t.created_at);
            return d.getMonth() === currentMonth && d.getFullYear() === currentYear;
        }).length;

        document.getElementById('kpi-total').textContent = totals;
        document.getElementById('kpi-active').textContent = active;
        document.getElementById('kpi-inactive').textContent = inactive;
        document.getElementById('kpi-new-month').textContent = newMonth;

        // Fetch analytics for trend bars
        fetch('superadmin_analytics_api.php')
            .then(response => response.ok ? response.json() : Promise.reject())
            .then(analytics => {
                const last7d = analytics.last_7_days_superadmin_logs || 0;
                const today = analytics.today_superadmin_logs || 0;
                document.getElementById('trend-desc-7d').textContent = `${last7d} events`;
                document.getElementById('trend-desc-today').textContent = `${today} events`;
                // Scale bars (assume max 50 for visual)
                document.getElementById('trend-bar-7d').style.width = `${Math.min(100, (last7d / 50) * 100)}%`;
                document.getElementById('trend-bar-today').style.width = `${Math.min(100, (today / 50) * 100)}%`;

                renderSuperAdminActivityTable(analytics);
                renderTenantActivityTable(analytics);

                // Render charts
                const growthLabels = [];
                const now = new Date();
                for (let i = 11; i >= 0; i--) {
                    const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
                    growthLabels.push(date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' }));
                }
                const ctxGrowth = document.getElementById('growthChart');
                if (ctxGrowth) {
                    new Chart(ctxGrowth, {
                        type: 'line',
                        data: {
                            labels: growthLabels,
                            datasets: [{
                                label: 'New Tenants per Month',
                                data: analytics.monthly_tenant_growth || [],
                                borderColor: '#0d3b66',
                                backgroundColor: 'rgba(13, 59, 102, 0.1)',
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }

                const activityLabels = [];
                const todayDate = new Date();
                for (let i = 6; i >= 0; i--) {
                    const date = new Date(todayDate);
                    date.setDate(todayDate.getDate() - i);
                    activityLabels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                }
                const ctxActivity = document.getElementById('activityChart');
                if (ctxActivity) {
                    new Chart(ctxActivity, {
                        type: 'bar',
                        data: {
                            labels: activityLabels,
                            datasets: [{
                                label: 'Tenant Activities',
                                data: analytics.daily_tenant_activities || [],
                                backgroundColor: '#0d3b66'
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            })
            .catch(() => {
                // Fallback to placeholder
                const recent = Math.min(20, data.length);
                document.getElementById('trend-desc-7d').textContent = `~${recent} events`;
                document.getElementById('trend-desc-today').textContent = `${Math.floor(recent / 4)} events`;
                document.getElementById('trend-bar-7d').style.width = `${Math.min(100, (recent / 20) * 100)}%`;
                document.getElementById('trend-bar-today').style.width = `${Math.min(100, (Math.floor(recent / 4) / 20) * 100)}%`;
            });
    }

    function renderTenantTable(page = 1) {
        const tbody = document.getElementById('tenant-table-body');
        if (!tbody) return;

        tbody.innerHTML = '';
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        const pageData = filteredData.slice(start, end);

        if (!pageData.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="padding: 16px; text-align: center; color: var(--sa-muted);">No rows found.</td></tr>';
        }

        pageData.forEach(tenant => {
            const tr = document.createElement('tr');
            tr.classList.add('clickable-row');
            tr.setAttribute('data-id', tenant.tenant_id);
            tr.setAttribute('data-status', tenant.status);
            tr.setAttribute('data-href', `view_tenant.php?id=${tenant.tenant_id}`);

            const createdDate = new Date(tenant.created_at).toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
            const isActive = (tenant.status || '').toLowerCase() === 'active';
            const appBase = window.location.pathname.replace(/\/[^\/]*$/, '');
            const tenantUrl = `${appBase}/tenant_login.php?tenant=${encodeURIComponent(tenant.subdomain_slug)}`;

            tr.innerHTML = `
                <td>
                    <div class="sa-tenant-info">
                        <a href="${tenantUrl}" target="_blank" class="sa-tenant-link" onclick="event.stopPropagation();">
                            <span class="sa-tenant-name">${tenant.company_name}</span>
                        </a>
                        <span class="sa-tenant-meta">${tenant.city}, ${tenant.province}</span>
                    </div>
                </td>
                <td>${tenant.owner_name}</td>
                <td>${tenant.contact_email}</td>
                <td>${tenant.phone}</td>
                <td><span class="sa-pill ${isActive ? 'sa-pill-active' : 'sa-pill-inactive'}">${tenant.status}</span></td>
                <td>${createdDate}</td>
                <td><button class="sa-btn ${isActive ? 'sa-btn-danger' : 'sa-btn-success'} sa-btn-toggle">${isActive ? 'Deactivate' : 'Activate'}</button></td>
            `;
            tbody.appendChild(tr);
        });

        const pageInfo = document.getElementById('page-info');
        const totalPages = Math.max(1, Math.ceil(filteredData.length / rowsPerPage));
        if (pageInfo) pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;

        document.getElementById('prev-page').disabled = currentPage <= 1;
        document.getElementById('next-page').disabled = currentPage >= totalPages;
    }

    function renderSuperAdminActivityTable(analytics) {
        const tbody = document.getElementById('superadmin-activity-table-body');
        if (!tbody) return;
        tbody.innerHTML = '';
        const saLogs = analytics.daily_superadmin_logs || [];
        const today = new Date();
        for (let i = 0; i <= 6; i++) {
            const date = new Date(today);
            date.setDate(today.getDate() - i);
            const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            const saCount = saLogs[6 - i] || 0;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${dateStr}</td>
                <td>${saCount}</td>
            `;
            tbody.appendChild(tr);
        }
    }

    function renderTenantActivityTable(analytics) {
        const tbody = document.getElementById('tenant-activity-table-body');
        if (!tbody) return;
        tbody.innerHTML = '';
        const tenantLogs = analytics.daily_tenant_activities || [];
        const today = new Date();
        for (let i = 0; i <= 6; i++) {
            const date = new Date(today);
            date.setDate(today.getDate() - i);
            const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            const tenantCount = tenantLogs[6 - i] || 0;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${dateStr}</td>
                <td>${tenantCount}</td>
            `;
            tbody.appendChild(tr);
        }
    }

    function renderMiniTenantTable() {
        const tbody = document.getElementById('mini-tenant-table-body');
        if (!tbody) return;
        tbody.innerHTML = '';
        const miniData = filteredData.slice(0, 5);
        miniData.forEach(tenant => {
            const tr = document.createElement('tr');
            const isActive = (tenant.status || '').toLowerCase() === 'active';
            tr.innerHTML = `
                <td>${tenant.company_name}</td>
                <td>${tenant.owner_name}</td>
                <td><span class="sa-pill ${isActive ? 'sa-pill-active' : 'sa-pill-inactive'}">${tenant.status}</span></td>
                <td><button class="sa-btn sa-btn-outline" onclick="viewTenantProfile(${tenant.tenant_id})">View</button></td>
            `;
            tbody.appendChild(tr);
        });
    }

    function applyFilters() {
        const searchInput = document.getElementById('clinic-search');
        const statusFilter = document.getElementById('status-filter');

        const term = normalize(searchInput?.value);
        const status = statusFilter?.value?.toLowerCase() || 'all';
        filteredData = tenantData.filter(tenant => {
            const matchTerm = [tenant.company_name, tenant.owner_name, tenant.contact_email].some(val => normalize(val).includes(term));
            const matchStatus = status === 'all' || normalize(tenant.status) === normalize(status);
            return matchTerm && matchStatus;
        });

        currentPage = 1;
        renderTenantTable(currentPage);
        renderMiniTenantTable();
    }

    function refreshTenantList() {
        fetch('get_tenants.php')
            .then(response => response.json())
            .then(data => {
                tenantData = Array.isArray(data) ? data : [];
                filteredData = [...tenantData];
                computeMetrics(tenantData);
                applyFilters();
                renderMiniTenantTable();
            })
            .catch(err => {
                console.error('Error loading tenants:', err);
                showToast('Could not load tenants.');
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        refreshTenantList();
        const searchInput = document.getElementById('clinic-search');
        const statusFilter = document.getElementById('status-filter');

        searchInput?.addEventListener('input', applyFilters);
        statusFilter?.addEventListener('change', applyFilters);

        document.getElementById('prev-page')?.addEventListener('click', function () {
            if (currentPage > 1) { currentPage--; renderTenantTable(currentPage); }
        });

        document.getElementById('next-page')?.addEventListener('click', function () {
            const totalPages = Math.max(1, Math.ceil(filteredData.length / rowsPerPage));
            if (currentPage < totalPages) { currentPage++; renderTenantTable(currentPage); }
        });

        document.getElementById('tenant-table-body')?.addEventListener('click', function (e) {
            const btn = e.target.closest('.sa-btn-toggle');
            if (btn) {
                const row = btn.closest('tr');
                if (!row) return;

                const tenantId = row.getAttribute('data-id');
                const currentStatus = (row.getAttribute('data-status') || 'active').toLowerCase();
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

                const formData = new FormData();
                formData.append('tenant_id', tenantId);
                formData.append('status', newStatus);

                fetch('update_tenant_status.php', { method: 'POST', body: formData })
                    .then(resp => resp.json())
                    .then(model => {
                        if (model.success) {
                            row.setAttribute('data-status', newStatus);
                            const pill = row.querySelector('.sa-pill');
                            if (pill) {
                                pill.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                                pill.classList.toggle('sa-pill-active', newStatus === 'active');
                                pill.classList.toggle('sa-pill-inactive', newStatus !== 'active');
                            }
                            btn.textContent = newStatus === 'active' ? 'Deactivate' : 'Activate';
                            btn.classList.toggle('sa-btn-success', newStatus !== 'active');
                            btn.classList.toggle('sa-btn-danger', newStatus === 'active');
                            showToast(`Clinic status updated to ${newStatus}.`);
                            refreshTenantList();
                        } else {
                            showToast('Error: ' + (model.message || 'Unable to update status'));
                        }
                    }).catch(error => {
                        console.error(error);
                        showToast('Network error while updating status.');
                    });

                return;
            }

            const row = e.target.closest('.clickable-row');
            if (!row) return;
            if (e.target.closest('.sa-btn')) return;
            if (e.target.closest('.sa-tenant-link')) return;

            const tenantId = row.getAttribute('data-id');
            if (!tenantId) return;
            window.activeTenantId = tenantId;

            fetch(`get_tenant_details.php?id=${tenantId}`)
                .then(res => res.json())
                .then(tenant => {
                    document.getElementById('modal-clinic-name').textContent = tenant.company_name;
                    document.getElementById('dt-owner').textContent = tenant.owner_name;
                    document.getElementById('dt-email').textContent = tenant.contact_email;
                    document.getElementById('dt-phone').textContent = tenant.phone;
                    document.getElementById('dt-status').textContent = tenant.status;
                    document.getElementById('dt-tier').textContent = tenant.subscription_tier ? tenant.subscription_tier.toUpperCase() : 'Not Set';
                    const homepageEl = document.getElementById('dt-homepage');
                    if (tenant.homepage_url && tenant.homepage_url.trim()) {
                        homepageEl.innerHTML = `<a href="${tenant.homepage_url}" target="_blank" class="sa-tenant-link" style="color: #0d3b66; font-weight: 500;">${tenant.homepage_url}</a>`;
                    } else {
                        homepageEl.textContent = 'Not set';
                    }
                    document.getElementById('dt-address').textContent = `${tenant.address}, ${tenant.city}, ${tenant.province}`;
                    const date = new Date(tenant.created_at).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' });
                    document.getElementById('dt-date').textContent = date;

// Documents toggle functionality
                    const toggleBtn = document.getElementById('toggle-docs-btn');
                    const docsContainer = document.getElementById('dt-documents');
                    if (toggleBtn && docsContainer) {
                        toggleBtn.onclick = () => {
                            const isVisible = docsContainer.style.display !== 'none';
                            docsContainer.style.display = isVisible ? 'none' : 'block';
                            toggleBtn.textContent = isVisible ? '📁 Show Documents' : '🙈 Hide Documents';
                            toggleBtn.classList.toggle('sa-btn-success', !isVisible);
                        };
                        
                        // Initialize documents content
                        if (tenant.documents && tenant.documents.length > 0) {
                            docsContainer.innerHTML = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-top: 8px;">' + 
                                tenant.documents.map(doc => `
                                    <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #f8fafc;">
                                        <div style="font-weight: 600; color: #0d3b66; margin-bottom: 6px; word-break: break-all;">${doc.document_name}</div>
                                        <div style="font-size: 0.8rem; color: var(--sa-muted); margin-bottom: 8px;">${(doc.file_size / 1024 / 1024).toFixed(1)} MB</div>
                                        <a href="${doc.file_path}" target="_blank" class="sa-btn sa-btn-success" style="width: 100%; justify-content: center; font-size: 0.8rem;">View</a>
                                    </div>
                                `).join('') + '</div>';
                        } else {
                            docsContainer.innerHTML = '<span class="sa-note">No documents uploaded.</span>';
                        }
                    }

                    document.getElementById('details-modal').style.display = 'flex';
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    showToast('Error loading clinic details.');
                });
        });

        // Handle upload button in details modal
        document.getElementById('upload-docs-btn')?.addEventListener('click', function() {
            const fileInput = document.getElementById('new-tenant-docs');
            const files = fileInput.files;
            if (files.length === 0) {
                showToast('Please select documents to upload.');
                return;
            }

            const formData = new FormData();
            formData.append('tenant_id', window.activeTenantId);
            
            let oversizedFiles = 0;
            for (let i = 0; i < files.length; i++) {
                if (files[i].size > 50 * 1024 * 1024) {
                    oversizedFiles++;
                    continue;
                }
                formData.append('documents[]', files[i]);
            }

            if (oversizedFiles > 0) {
                if (formData.getAll('documents[]').length === 0) {
                    showToast('File size is too big. Max limit is 50MB per file.');
                    return;
                } else {
                    showToast(oversizedFiles + ' file(s) skipped because they exceed 50MB.');
                }
            }

            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Uploading...';

            fetch('upload_tenant_documents.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(async res => {
                if (!res.ok) {
                    if (res.status === 413) {
                        throw new Error('The uploaded files are too large. Please upload smaller files (max 50MB each).');
                    }
                    let textMsg = '';
                    try {
                        textMsg = await res.text();
                    } catch(e) {}
                    throw new Error(textMsg || `Server error (HTTP ${res.status})`);
                }
                const contentType = res.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    let textMsg = '';
                    try {
                        textMsg = await res.text();
                    } catch(e) {}
                    const cleanMsg = textMsg.replace(/<[^>]*>/g, '').trim().substring(0, 150);
                    throw new Error(cleanMsg || 'Server returned an invalid response format.');
                }
                return res.json();
            })
            .then(data => {
                btn.disabled = false;
                btn.textContent = 'Upload';
                if (data.success) {
                    showToast(data.message);
                    fileInput.value = '';
                    // Refresh details modal
                    viewTenantProfile(window.activeTenantId);
                } else {
                    showToast(data.message);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.textContent = 'Upload';
                console.error(err);
                showToast(err.message || 'Upload failed due to a network error. Ensure files are under 50MB and are PDF, Word, or Images.');
            });
        });
    });

    function closeDetailsModal() {
        document.getElementById('details-modal').style.display = 'none';
    }

    function viewTenantProfile(tenantId) {
        window.activeTenantId = tenantId;
        fetch(`get_tenant_details.php?id=${tenantId}`)
            .then(res => res.json())
            .then(tenant => {
                document.getElementById('modal-clinic-name').textContent = tenant.company_name;
                document.getElementById('dt-owner').textContent = tenant.owner_name;
                document.getElementById('dt-email').textContent = tenant.contact_email;
                document.getElementById('dt-phone').textContent = tenant.phone;
                document.getElementById('dt-status').textContent = tenant.status;
                document.getElementById('dt-tier').textContent = tenant.subscription_tier ? tenant.subscription_tier.toUpperCase() : 'Not Set';
                const homepageEl = document.getElementById('dt-homepage');
                if (tenant.homepage_url && tenant.homepage_url.trim()) {
                    homepageEl.innerHTML = `<a href="${tenant.homepage_url}" target="_blank" class="sa-tenant-link" style="color: #0d3b66; font-weight: 500;">${tenant.homepage_url}</a>`;
                } else {
                    homepageEl.textContent = 'Not set';
                }
                document.getElementById('dt-address').textContent = `${tenant.address}, ${tenant.city}, ${tenant.province}`;
                const date = new Date(tenant.created_at).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' });
                document.getElementById('dt-date').textContent = date;

                // Display documents
                const docsContainer = document.getElementById('dt-documents');
                if (docsContainer) {
                    if (tenant.documents && tenant.documents.length > 0) {
                        docsContainer.innerHTML = '<ul style="margin: 0; padding-left: 20px;">' + 
                            tenant.documents.map(doc => `<li><a href="${doc.file_path}" target="_blank" class="sa-tenant-link" style="color: #0d3b66; text-decoration: underline;">${doc.document_name}</a></li>`).join('') + 
                            '</ul>';
                    } else {
                        docsContainer.innerHTML = '<span class="sa-note">No documents uploaded.</span>';
                    }
                }

                document.getElementById('details-modal').style.display = 'flex';
            })
            .catch(err => {
                console.error('Fetch error:', err);
                showToast('Error loading clinic details.');
            });
    }



    function copyToClipboard(text, successText = 'Copied to clipboard') {
        if (!text) {
            showToast?.('Nothing to copy.');
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text)
                .then(() => showToast?.(successText))
                .catch(() => showToast?.('Copy failed. Please use Ctrl+C.'));
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showToast?.(successText);
            } catch (err) {
                showToast?.('Copy failed. Please use Ctrl+C.');
            }
            document.body.removeChild(textarea);
        }
    }



    // Initialize Sales Trends Chart
    (function() {
        const chartCanvas = document.getElementById('dashboardSalesChart');
        if (!chartCanvas) return;

        // Generate last 12 months labels (oldest first)
        const labels = [];
        const now = new Date();
        for (let i = 11; i >= 0; i--) {
            const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
            labels.push(date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' }));
        }

        const phCurrency = new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });

        // Fetch sales data
        fetch('get_sales_data.php')
            .then(response => response.json())
            .then(data => {
                const revenueData = (data.monthlyRevenue || []).map(val => parseFloat(val) || 0);
                new Chart(chartCanvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Monthly Sales',
                            data: revenueData,
                            borderColor: '#0d3b66',
                            backgroundColor: 'rgba(13, 59, 102, 0.1)',
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: '#0d3b66'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return phCurrency.format(context.parsed.y);
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return phCurrency.format(value);
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => console.error('Error loading sales data:', error));

    })();
</script>

</body>
</html>


