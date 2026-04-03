<?php
session_start();
require_once __DIR__ . '/security_headers.php';
if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadmin_login.php');
    exit;
}
require_once __DIR__ . '/subscription_tiers.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Super Admin</title>
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
            border: 1px solid var(--sa-border);
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
            background: #22c55e;
            color: #ffffff;
            border: 2px solid #22c55e;
        }

        .sa-btn-success:hover {
            background: #16a34a;
            border-color: #16a34a;
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
}

.menu-dropdown-toggle:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.menu-dropdown-items {
    background-color: rgba(255, 255, 255, 0.1);
    border-left: 3px solid #22c55e;
    overflow: hidden;
}

.menu-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    color: #ffffff;
    text-decoration: none;
    font-size: 0.9rem;
    transition: background-color 0.15s;
}

.menu-dropdown-item:hover {
    background-color: rgba(255, 255, 255, 0.15);
}

.clickable-row { cursor: pointer; transition: background 0.2s; }
.clickable-row:hover { background-color: #f8fafc; }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="sidebar-logo" style="display: flex; align-items: center; gap: 12px; padding: 24px 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <div style="font-size: 32px;">🏥</div>
                <div>
                    <div class="sidebar-logo-text" style="margin: 0;">OralSync</div>
                    <div style="font-size: 12px; color: rgba(255, 255, 255, 0.7);">Super Admin</div>
                </div>
            </div>
            <nav class="menu">
                <a href="#" class="menu-item active" data-section="dashboard-section"><span>🛡️</span> Dashboard</a>
                <a href="#" class="menu-item" data-section="tenant-section"><span>🏥</span> Tenant List</a>
                <a href="#" class="menu-item" data-section="register-section"><span>➕</span> Register Clinic</a>
                <div class="menu-dropdown" style="width: 100%;">
                    <button class="menu-item menu-dropdown-toggle" type="button"><span>📊</span> Reports</button>
                    <div class="menu-dropdown-items" style="display: none; flex-direction: column; width: 100%; overflow-x: hidden;">
                        <a href="superadmin_reports.php" class="menu-dropdown-item"><span>📈</span> Tenant Reports</a>
                        <a href="superadmin_sales_report.php" class="menu-dropdown-item"><span>💰</span> Sales Reports</a>
                    </div>
                </div>
                <a href="superadmin_audit_logs.php" class="menu-item"><span>📋</span> Audit Logs</a>
                <a href="superadmin_settings.php" class="menu-item"><span>⚙️</span> Settings</a>
            </nav>
        </div>
        <div class="sidebar-bottom">
            <a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="sa-main-header">
            <div>
                <h1>Super Admin Control</h1>
                <span>Manage clinics and onboard new tenants for OralSync.</span>
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
                        <div class="sa-card-title">Revenue Trends</div>
                        <div class="sa-card-subtitle">Monthly subscription revenue</div>
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

        <!-- Register Clinic -->
        <section id="register-section" class="sa-section">
            <div class="sa-card">
                <div class="sa-card-header">
                    <div>
                        <div class="sa-card-title">Register New Clinic</div>
                        <div class="sa-card-subtitle">Capture clinic and owner details. Email delivery is simulated for now.</div>
                    </div>
                </div>

                <form id="register-form">
                    <div class="sa-form-grid">
                        <div class="sa-form-group">
                            <label for="clinic-name">Clinic Name <span class="sa-badge-required">*</span></label>
                            <input type="text" id="clinic-name" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="owner-name">Owner Name <span class="sa-badge-required">*</span></label>
                            <input type="text" id="owner-name" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="owner-email">Clinic / Owner Email <span class="sa-badge-required">*</span></label>
                            <input type="email" id="owner-email" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="clinic-phone">Clinic Phone Number <span class="sa-badge-required">*</span></label>
                            <input type="tel" id="clinic-phone" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="clinic-address">Clinic Address <span class="sa-badge-required">*</span></label>
                            <input type="text" id="clinic-address" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="clinic-city">City / Municipality <span class="sa-badge-required">*</span></label>
                            <input type="text" id="clinic-city" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="clinic-province">Province / Area (Luzon only) <span class="sa-badge-required">*</span></label>
                            <select id="clinic-province" required>
                                <option value="">Select province</option>
                                <option>Metro Manila</option>
                                <option>Bulacan</option>
                                <option>Pampanga</option>
                                <option>Tarlac</option>
                                <option>Bataan</option>
                                <option>Nueva Ecija</option>
                                <option>Zambales</option>
                                <option>Cavite</option>
                                <option>Laguna</option>
                                <option>Batangas</option>
                                <option>Rizal</option>
                                <option>Quezon</option>
                                <option>Benguet</option>
                                <option>Ilocos Norte</option>
                                <option>Ilocos Sur</option>
                                <option>La Union</option>
                                <option>Pangasinan</option>
                                <option>Cagayan</option>
                                <option>Isabela</option>
                                <option>Abra</option>
                            </select>
                        </div>
                        <div class="sa-form-group">
                            <label for="clinic-tier">Subscription Tier <span class="sa-badge-required">*</span></label>
                            <select id="clinic-tier" required>
                                <option value="">Select a tier</option>
                                <?php
                                foreach (getTierOptions() as $tierKey => $tierName) {
                                    echo "<option value=\"" . htmlspecialchars($tierKey) . "\">" . htmlspecialchars($tierName) . "</option>";
                                }
                                ?>
                            </select>
                            <div class="sa-note" id="tier-details" style="margin-top: 10px; padding: 10px; background: #f0f9ff; border-left: 3px solid #0d3b66; display: none;">
                                <!-- Tier details will be shown here -->
                            </div>
                        </div>
                        <div class="sa-form-group" style="grid-column: 1 / -1;">
                            <label for="clinic-notes">Notes / Special Instructions</label>
                            <textarea id="clinic-notes" placeholder="Optional notes about billing, onboarding preferences, or setup requirements."></textarea>
                        </div>
                    </div>

                    <div class="sa-form-actions">
                        <button type="reset" class="sa-btn sa-btn-outline">Clear</button>
                        <button type="submit" class="sa-btn sa-btn-success">Register Clinic</button>
                    </div>
                </form>

                <div id="registration-success" class="sa-success-panel" style="display:none;">
    <div style="display: flex; align-items: center; gap: 8px; color: #10b981; font-weight: 600;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        Clinic Registered Successfully!
    </div>
    <div id="success-message-body" style="margin-top: 12px; font-size: 0.9rem; color: var(--sa-text-muted);">
        The clinic has been added to the database. Provide the credentials below to the owner.
    </div>

    <div class="sa-credential-box">
        <div class="sa-credential-item">
            <span class="sa-label">Temporary Password:</span>
            <div style="display: flex; align-items: center; gap: 10px;">
                <code id="display-temp-password" class="sa-temp-pass">Generating...</code>
                <button type="button" class="sa-copy-btn" onclick="copyPassword()">Copy</button>
            </div>
        </div>
        <div class="sa-credential-item">
            <span class="sa-label">Login URL:</span>
            <div id="sample-login-link" class="sa-link-sample"></div>
        </div>
    </div>

    <div class="sa-success-actions">
        <button id="btn-resend-email" class="sa-btn sa-btn-outline">Resend Email</button>
        <button id="btn-go-tenants" class="sa-btn sa-btn-success">Go to Tenant List</button>
    </div>
    <div id="resend-note" class="sa-note" style="display:none;">A resend has been simulated for this clinic's login email.</div>
</div>
            </div>
        </section>
    </main>
</div>
<div id="sa-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); z-index:100; align-items:center; justify-content:center;">
    <div class="sa-card" style="width: 100%; max-width: 500px; animation: modalSlide 0.3s ease;">
        <div class="sa-card-header">
            <div class="sa-card-title">Confirm Registration</div>
        </div>
        <p class="sa-note" style="margin-bottom: 20px;">Please verify these details with the tenant over the phone before proceeding.</p>
        
        <div id="modal-review-content" style="background: var(--sa-bg); padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem;">
            </div>

        <div class="sa-form-actions">
            <button id="modal-cancel" class="sa-btn sa-btn-outline">Edit Details</button>
            <button id="modal-confirm" class="sa-btn sa-btn-success">Finalize & Save</button>
        </div>
    </div>
</div>
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
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <strong>Address:</strong> <span id="dt-address"></span>
                </div>
            </div>
            <hr>
            <div class="sa-note">Registration Date: <span id="dt-date"></span></div>
        </div>

        <div class="sa-form-actions">
            <button class="sa-btn sa-btn-outline" onclick="closeDetailsModal()">Close</button>
            <button class="sa-btn sa-btn-success" id="btn-login-as"></button>
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

    // Close dropdown when clicking on external links
    document.querySelectorAll('a:not([data-section])').forEach(link => {
        if (link.hasAttribute('href') && !link.classList.contains('menu-dropdown-item')) {
            link.addEventListener('click', function() {
                const dropdownItems = document.querySelector('.menu-dropdown-items');
                const dropdownToggle = document.querySelector('.menu-dropdown-toggle');
                if (dropdownItems) dropdownItems.style.display = 'none';
                if (dropdownToggle) dropdownToggle.classList.remove('active');
            });
        }
    });

    // Sidebar navigation between sections
    (function () {
        const menuItems = document.querySelectorAll('.menu-item[data-section]');
        const sections = document.querySelectorAll('.sa-section');

        menuItems.forEach(item => {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                const target = this.getAttribute('data-section');

                // Close dropdown
                const dropdownItems = document.querySelector('.menu-dropdown-items');
                const dropdownToggle = document.querySelector('.menu-dropdown-toggle');
                if (dropdownItems) dropdownItems.style.display = 'none';
                if (dropdownToggle) dropdownToggle.classList.remove('active');

                menuItems.forEach(mi => mi.classList.remove('active'));
                this.classList.add('active');

                sections.forEach(sec => {
                    if (sec.id === target) {
                        sec.classList.add('active-section');
                    } else {
                        sec.classList.remove('active-section');
                    }
                });
            });
        });

        // Handle URL hash on page load
        window.addEventListener('load', function() {
            const hash = window.location.hash.substring(1); // Remove the '#'
            if (hash) {
                const targetSection = document.getElementById(hash);
                if (targetSection) {
                    // Remove active from all menu items
                    document.querySelectorAll('.menu-item').forEach(mi => mi.classList.remove('active'));
                    // Add active to the corresponding menu item
                    const menuItem = document.querySelector(`.menu-item[data-section="${hash}"]`);
                    if (menuItem) {
                        menuItem.classList.add('active');
                    }
                    // Show the target section
                    document.querySelectorAll('.sa-section').forEach(sec => sec.classList.remove('active-section'));
                    targetSection.classList.add('active-section');
                }
            }
        });
    })();

    // Dropdown menu toggle
    (function () {
        const dropdownToggle = document.querySelector('.menu-dropdown-toggle');
        const dropdownItems = document.querySelector('.menu-dropdown-items');

        if (dropdownToggle && dropdownItems) {
            dropdownToggle.addEventListener('click', function (e) {
                e.preventDefault();
                const isVisible = dropdownItems.style.display !== 'none';
                dropdownItems.style.display = isVisible ? 'none' : 'flex';
                dropdownItems.style.flexDirection = 'column';
                dropdownToggle.classList.toggle('active');
            });

            // Close dropdown when clicking on dropdown items
            dropdownItems.addEventListener('click', function(e) {
                if (e.target.closest('.menu-dropdown-item')) {
                    dropdownItems.style.display = 'none';
                    dropdownToggle.classList.remove('active');
                }
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
        const monthAgo = new Date();
        monthAgo.setDate(monthAgo.getDate() - 30);
        const newMonth = data.filter(t => new Date(t.created_at) >= monthAgo).length;

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
                for (let i = 11; i >= 0; i--) {
                    const date = new Date();
                    date.setMonth(date.getMonth() - i);
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
                for (let i = 6; i >= 0; i--) {
                    const date = new Date();
                    date.setDate(date.getDate() - i);
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
        for (let i = 6; i >= 0; i--) {
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
        for (let i = 6; i >= 0; i--) {
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

            fetch(`get_tenant_details.php?id=${tenantId}`)
                .then(res => res.json())
                .then(tenant => {
                    document.getElementById('modal-clinic-name').textContent = tenant.company_name;
                    document.getElementById('dt-owner').textContent = tenant.owner_name;
                    document.getElementById('dt-email').textContent = tenant.contact_email;
                    document.getElementById('dt-phone').textContent = tenant.phone;
                    document.getElementById('dt-status').textContent = tenant.status;
                    document.getElementById('dt-address').textContent = `${tenant.address}, ${tenant.city}, ${tenant.province}`;
                    const date = new Date(tenant.created_at).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' });
                    document.getElementById('dt-date').textContent = date;
                    document.getElementById('details-modal').style.display = 'flex';
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    showToast('Error loading clinic details.');
                });
        });
    });

    function closeDetailsModal() {
        document.getElementById('details-modal').style.display = 'none';
    }

    function viewTenantProfile(tenantId) {
        fetch(`get_tenant_details.php?id=${tenantId}`)
            .then(res => res.json())
            .then(tenant => {
                document.getElementById('modal-clinic-name').textContent = tenant.company_name;
                document.getElementById('dt-owner').textContent = tenant.owner_name;
                document.getElementById('dt-email').textContent = tenant.contact_email;
                document.getElementById('dt-phone').textContent = tenant.phone;
                document.getElementById('dt-status').textContent = tenant.status;
                document.getElementById('dt-address').textContent = `${tenant.address}, ${tenant.city}, ${tenant.province}`;
                const date = new Date(tenant.created_at).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' });
                document.getElementById('dt-date').textContent = date;
                document.getElementById('details-modal').style.display = 'flex';
            })
            .catch(err => {
                console.error('Fetch error:', err);
                showToast('Error loading clinic details.');
            });
    }

    // Register clinic form logic (IIFE with modal workflow)
    (function () {
        const form = document.getElementById('register-form');
        const modalOverlay = document.getElementById('sa-modal-overlay');
        const modalReviewContent = document.getElementById('modal-review-content');
        const modalCancel = document.getElementById('modal-cancel');
        const modalConfirm = document.getElementById('modal-confirm');
        const tierSelect = document.getElementById('clinic-tier');
        const tierDetails = document.getElementById('tier-details');
        
        const successPanel = document.getElementById('registration-success');
        const sampleLinkEl = document.getElementById('sample-login-link');
        const resendNote = document.getElementById('resend-note');

        if (!form) return;

        // Tier definitions from PHP
        const tierDefinitions = <?php echo json_encode(getAllTiers()); ?>;

        // Show tier details when a tier is selected
        if (tierSelect) {
            tierSelect.addEventListener('change', function() {
                if (this.value && tierDefinitions[this.value]) {
                    const tier = tierDefinitions[this.value];
                    let detailsHTML = '<strong>' + tier['display_name'] + '</strong><br>';
                    detailsHTML += '<small style="color: #475569;">' + tier['description'] + '</small><br><br>';
                    detailsHTML += '<strong style="color: #0d3b66;">Key Features:</strong><ul style="margin: 5px 0; padding-left: 20px; font-size: 0.85rem;">';
                    
                    const features = tier['features'];
                    detailsHTML += '<li>Max Dentists: ' + features['max_dentists'] + '</li>';
                    detailsHTML += '<li>Max Receptionists: ' + features['max_receptionists'] + '</li>';
                    detailsHTML += '<li>Max Patients: ' + features['max_patients'] + '</li>';
                    detailsHTML += '<li>Storage: ' + features['max_storage_gb'] + ' GB</li>';
                    detailsHTML += '<li>Dental Chart: ' + (features['dental_chart_tracking'] ? '✓ Yes' : '✗ No') + '</li>';
                    detailsHTML += '<li>SMS Notifications: ' + (features['sms_notifications'] ? '✓ Yes' : '✗ No') + '</li>';
                    detailsHTML += '</ul>';
                    
                    tierDetails.innerHTML = detailsHTML;
                    tierDetails.style.display = 'block';
                } else {
                    tierDetails.style.display = 'none';
                }
            });
        }

        // STEP 1: Intercept the form submission and show the modal
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // Get values for review
            const tierValue = document.getElementById('clinic-tier').value;
            const tierName = tierValue && tierDefinitions[tierValue] ? tierDefinitions[tierValue]['display_name'] : 'Not selected';
            
            const reviewData = {
                'Clinic Name': document.getElementById('clinic-name').value,
                'Owner': document.getElementById('owner-name').value,
                'Email': document.getElementById('owner-email').value,
                'Phone': document.getElementById('clinic-phone').value,
                'Location': `${document.getElementById('clinic-city').value}, ${document.getElementById('clinic-province').value}`,
                'Subscription Tier': tierName
            };

            // Build the review list inside the modal
            modalReviewContent.innerHTML = Object.entries(reviewData)
                .map(([label, value]) => `<p style="margin: 5px 0;"><strong>${label}:</strong> ${value}</p>`)
                .join('');

            // Show the modal
            modalOverlay.style.display = 'flex';
        });

        // STEP 2: Handle "Edit Details" (Cancel)
        modalCancel.addEventListener('click', () => {
            modalOverlay.style.display = 'none';
        });

        // STEP 3: Handle "Finalize & Save" (Confirm)
        modalConfirm.addEventListener('click', function () {
            // Disable button to prevent double submission
            modalConfirm.disabled = true;
            modalConfirm.textContent = 'Saving...';

            const formData = new FormData();
            formData.append('clinicName', document.getElementById('clinic-name').value.trim());
            formData.append('ownerName', document.getElementById('owner-name').value.trim());
            formData.append('email', document.getElementById('owner-email').value.trim());
            formData.append('phone', document.getElementById('clinic-phone').value.trim());
            formData.append('address', document.getElementById('clinic-address').value.trim());
            formData.append('city', document.getElementById('clinic-city').value.trim());
            formData.append('province', document.getElementById('clinic-province').value);
            formData.append('tier', document.getElementById('clinic-tier').value);

            fetch('register_clinic.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    refreshTenantList();

                    if (sampleLinkEl) {
                        const baseUrl = window.location.origin;
                        sampleLinkEl.textContent = `${baseUrl}/tenant/${encodeURIComponent(data.slug)}/login`;
                    }

                    const passField = document.getElementById('display-temp-password');
                    if (passField) {
                        passField.textContent = data.temp_password;
                    }

                    if (successPanel) {
                        successPanel.style.display = 'block';
                        if (resendNote) resendNote.style.display = 'none';
                    }

                    if (data.email_sent === false) {
                        showToast('Clinic saved, but email failed to send. Check console for error.', 6500);
                        if (data.email_error) console.warn('Email error:', data.email_error);
                    } else {
                        showToast('Clinic saved! Email sent.');
                    }
                    form.reset();
                    if (tierDetails) tierDetails.style.display = 'none';
                    modalOverlay.style.display = 'none'; // Close modal on success
                } else {
                    showToast('Database Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to connect to the server.');
            })
            .finally(() => {
                // Re-enable buttons for the next time
                modalConfirm.disabled = false;
                modalConfirm.textContent = 'Finalize & Save';
            });
        });
    })();

    // Initialize Sales Trends Chart
    (function() {
        const chartCanvas = document.getElementById('dashboardSalesChart');
        if (!chartCanvas) return;

        // Generate last 12 months labels
        const labels = [];
        for (let i = 11; i >= 0; i--) {
            const date = new Date();
            date.setMonth(date.getMonth() - i);
            labels.push(date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' }));
        }

        // Fetch sales data
        fetch('get_sales_data.php')
            .then(response => response.json())
            .then(data => {
                new Chart(chartCanvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Monthly Revenue',
                            data: data.monthlyRevenue || [],
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
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₱' + value.toFixed(0);
                                    }
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

