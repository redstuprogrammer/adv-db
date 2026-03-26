<?php
session_start();
require_once __DIR__ . '/security_headers.php';
if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadmin_login.php');
    exit;
}
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/tenant_utils.php';
require_once __DIR__ . '/subscription_tiers.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Sales Report</title>
    <link rel="stylesheet" href="style1.css">
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
        }

        .sa-profile span {
            font-weight: 600;
        }

        .sa-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .sa-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .sa-card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .sa-card-subtitle {
            font-size: 0.875rem;
            color: var(--sa-muted);
            margin: 0;
        }

        .sa-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .sa-metric {
            text-align: center;
            background: #f8fafc;
            padding: 24px;
            border-radius: 8px;
            border: 1px solid var(--sa-border);
        }

        .sa-metric-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--sa-primary);
            margin: 0 0 12px 0;
        }

        .sa-metric-label {
            font-size: 0.875rem;
            color: var(--sa-muted);
            margin: 0;
            line-height: 1.5;
        }

        .sa-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .sa-table th {
            text-align: left;
            padding: 12px 16px;
            border-bottom: 1px solid var(--sa-border);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--sa-muted);
            background: #f9fafb;
        }

        .sa-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .sa-table tbody tr:hover {
            background: #f8fafc;
        }

        .sa-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .sa-pill-paid {
            background: #dcfce7;
            color: #166534;
        }

        .sa-pill-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .sa-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .sa-form-group {
            display: flex;
            flex-direction: column;
        }

        .sa-form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 5px;
        }

        .sa-form-group input,
        .sa-form-group select {
            padding: 8px 12px;
            border: 1px solid var(--sa-border);
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .sa-btn {
            background: var(--sa-primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
        }

        .sa-btn:hover {
            background: #0a2f52;
        }

        .sa-btn-outline {
            background: white;
            color: var(--sa-primary);
            border: 1px solid var(--sa-border);
        }

        .sa-btn-outline:hover {
            background: var(--sa-bg);
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 30px;
        }

        .currency {
            color: #16a34a;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="main-logo">
                    <rect width="32" height="32" rx="8" fill="#0d3b66"/>
                    <text x="16" y="22" font-size="20" font-weight="bold" fill="white" text-anchor="middle">O</text>
                </svg>
            </div>
            <nav class="menu">
                <a href="superadmin_dash.php" class="menu-item"><span>🛡️</span> Dashboard</a>
                <a href="superadmin_dash.php#tenant-section" class="menu-item"><span>🏥</span> Tenant List</a>
                <a href="superadmin_dash.php#register-section" class="menu-item"><span>➕</span> Register Clinic</a>
                <a href="superadmin_reports.php" class="menu-item"><span>📊</span> Reports</a>
                <a href="superadmin_sales_report.php" class="menu-item active"><span>💰</span> Sales Report</a>
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
                <h1>Sales Report</h1>
                <span>Financial and transaction-related data</span>
            </div>
            <div class="sa-profile">
                <span>Welcome, <strong>Super Admin</strong></span>
                <div class="sa-profile-avatar">🛡️</div>
            </div>
        </header>

        <!-- Sales Summary -->
        <div class="sa-card">
            <div class="sa-card-header">
                <div>
                    <div class="sa-card-title">Revenue Overview</div>
                    <div class="sa-card-subtitle">Total sales and active subscriptions</div>
                </div>
            </div>

            <div class="sa-grid">
                <?php
                try {
                    // Total revenue
                    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM tenant_subscription_revenue WHERE status = 'paid'");
                    $total_revenue = $stmt->fetch()['total'] ?? 0;

                    // This month revenue
                    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM tenant_subscription_revenue WHERE status = 'paid' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
                    $month_revenue = $stmt->fetch()['total'] ?? 0;

                    // Active subscriptions
                    $stmt = $pdo->query("SELECT COUNT(DISTINCT tenant_id) as count FROM tenants WHERE status = 'active'");
                    $active_subscriptions = $stmt->fetch()['count'] ?? 0;

                    // Average revenue per tenant
                    $avg_revenue = $active_subscriptions > 0 ? $total_revenue / $active_subscriptions : 0;
                } catch (Exception $e) {
                    $total_revenue = $month_revenue = $active_subscriptions = $avg_revenue = 0;
                }
                ?>
                <div class="sa-metric">
                    <div class="sa-metric-value currency">$<?php echo number_format($total_revenue, 2); ?></div>
                    <div class="sa-metric-label">Total Revenue (All Time)</div>
                </div>
                <div class="sa-metric">
                    <div class="sa-metric-value currency">$<?php echo number_format($month_revenue, 2); ?></div>
                    <div class="sa-metric-label">This Month Revenue</div>
                </div>
                <div class="sa-metric">
                    <div class="sa-metric-value"><?php echo $active_subscriptions; ?></div>
                    <div class="sa-metric-label">Active Subscriptions</div>
                </div>
                <div class="sa-metric">
                    <div class="sa-metric-value currency">$<?php echo number_format($avg_revenue, 2); ?></div>
                    <div class="sa-metric-label">Average Revenue per Tenant</div>
                </div>
            </div>
        </div>

        <!-- Sales Trends Chart -->
        <div class="sa-card">
            <div class="sa-card-header">
                <div>
                    <div class="sa-card-title">Revenue Trends</div>
                    <div class="sa-card-subtitle">Monthly revenue over time</div>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Sales by Tier -->
        <div class="sa-card">
            <div class="sa-card-header">
                <div>
                    <div class="sa-card-title">Sales by Subscription Tier</div>
                    <div class="sa-card-subtitle">Revenue breakdown by tier</div>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="tierChart"></canvas>
            </div>
        </div>

        <!-- Top Performing Tenants -->
        <div class="sa-card">
            <div class="sa-card-header">
                <div>
                    <div class="sa-card-title">Top Performing Tenants</div>
                    <div class="sa-card-subtitle">Highest revenue generating clinics</div>
                </div>
            </div>

            <table class="sa-table">
                <thead>
                    <tr>
                        <th>Clinic Name</th>
                        <th>Tier</th>
                        <th>Total Revenue</th>
                        <th>Months Active</th>
                        <th>Monthly Average</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT 
                                t.company_name,
                                t.subscription_tier,
                                SUM(tsr.amount) as total_revenue,
                                COUNT(DISTINCT MONTH(tsr.payment_date)) as months_active,
                                AVG(tsr.amount) as avg_revenue
                            FROM tenant_subscription_revenue tsr
                            JOIN tenants t ON tsr.tenant_id = t.tenant_id
                            WHERE tsr.status = 'paid'
                            GROUP BY tsr.tenant_id, t.company_name, t.subscription_tier
                            ORDER BY total_revenue DESC
                            LIMIT 10
                        ");
                        
                        while ($row = $stmt->fetch()) {
                            $tierName = getTierByKey($row['subscription_tier'])['display_name'] ?? $row['subscription_tier'];
                            echo "<tr>
                                    <td>{$row['company_name']}</td>
                                    <td><span class='sa-pill sa-pill-paid'>{$tierName}</span></td>
                                    <td><span class='currency'>$" . number_format($row['total_revenue'], 2) . "</span></td>
                                    <td>{$row['months_active']}</td>
                                    <td><span class='currency'>$" . number_format($row['avg_revenue'], 2) . "</span></td>
                                  </tr>";
                        }
                    } catch (Exception $e) {
                        echo "<tr><td colspan='5' style='text-align: center; color: var(--sa-muted);'>No data available</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Transaction History -->
        <div class="sa-card">
            <div class="sa-card-header">
                <div>
                    <div class="sa-card-title">Recent Transactions</div>
                    <div class="sa-card-subtitle">Latest subscription payments</div>
                </div>
            </div>

            <table class="sa-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Clinic</th>
                        <th>Tier</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT 
                                tsr.payment_date,
                                t.company_name,
                                tsr.subscription_tier,
                                tsr.amount,
                                tsr.status
                            FROM tenant_subscription_revenue tsr
                            JOIN tenants t ON tsr.tenant_id = t.tenant_id
                            ORDER BY tsr.payment_date DESC
                            LIMIT 20
                        ");
                        
                        while ($row = $stmt->fetch()) {
                            $statusClass = $row['status'] === 'paid' ? 'sa-pill-paid' : 'sa-pill-pending';
                            echo "<tr>
                                    <td>" . formatDateTimeReadable($row['payment_date']) . "</td>
                                    <td>{$row['company_name']}</td>
                                    <td>" . getTierByKey($row['subscription_tier'])['display_name'] . "</td>
                                    <td><span class='currency'>$" . number_format($row['amount'], 2) . "</span></td>
                                    <td><span class='sa-pill {$statusClass}'>" . ucfirst($row['status']) . "</span></td>
                                  </tr>";
                        }
                    } catch (Exception $e) {
                        echo "<tr><td colspan='5' style='text-align: center; color: var(--sa-muted);'>No transactions found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<script>
    // Revenue trends chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: [
                <?php
                // Generate last 12 months
                for ($i = 11; $i >= 0; $i--) {
                    $date = date('M Y', strtotime("-{$i} months"));
                    echo "'" . $date . "',";
                }
                ?>
            ],
            datasets: [{
                label: 'Monthly Revenue',
                data: [
                    <?php
                    try {
                        for ($i = 11; $i >= 0; $i--) {
                            $month = date('Y-m', strtotime("-{$i} months"));
                            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM tenant_subscription_revenue WHERE status = 'paid' AND DATE_FORMAT(payment_date, '%Y-%m') = ?");
                            $stmt->execute([$month]);
                            $result = $stmt->fetch();
                            echo $result['total'] . ",";
                        }
                    } catch (Exception $e) {
                        echo "0,0,0,0,0,0,0,0,0,0,0,0";
                    }
                    ?>
                ],
                borderColor: '#0d3b66',
                backgroundColor: 'rgba(13, 59, 102, 0.1)',
                tension: 0.3,
                fill: true
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
                            return '$' + value.toFixed(0);
                        }
                    }
                }
            }
        }
    });

    // Sales by tier chart
    const tierCtx = document.getElementById('tierChart').getContext('2d');
    new Chart(tierCtx, {
        type: 'doughnut',
        data: {
            labels: [
                <?php
                try {
                    $colors = [];
                    foreach (getAllTiers() as $tierKey => $tier) {
                        echo "'" . htmlspecialchars($tier['display_name']) . "',";
                    }
                } catch (Exception $e) {
                    echo "'Startup','Professional'";
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php
                    try {
                        $tiers = getAllTiers();
                        foreach ($tiers as $tierKey => $tier) {
                            $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM tenant_subscription_revenue WHERE status = 'paid' AND subscription_tier = '" . $pdo->quote($tierKey) . "'");
                            $total = $stmt->fetch()['total'];
                            echo $total . ",";
                        }
                    } catch (Exception $e) {
                        echo "0,0";
                    }
                    ?>
                ],
                backgroundColor: ['#60a5fa', '#10b981', '#f59e0b', '#8b5cf6']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                }
            }
        }
    });
</script>

</body>
</html>
