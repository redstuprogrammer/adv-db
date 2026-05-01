<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

// Check auth state
$sessionManager = SessionManager::getInstance();
$sessionManager->requireSuperAdmin();

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/settings.php';

// Load settings for logo display
try {
    $currentSettings = getAllSettings();
} catch (Exception $e) {
    $currentSettings = [];
}

// SQL to get only Platform Subscription payments
$query = "SELECT 
            p.*, 
            t.company_name,
            t.subscription_tier as current_tier
          FROM payment p
          INNER JOIN tenants t ON p.tenant_id = t.tenant_id
          WHERE p.procedures_json LIKE '%Platform Subscription Renewal%'
          ORDER BY p.payment_date DESC"; 

$res = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OralSync | Subscription Audit</title>
    <link rel="stylesheet" href="style1.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sa-primary: #0d3b66;
            --sa-muted: #64748b;
            --sa-border: #e2e8f0;
            --sa-bg: #f8fafc;
            --success: #10b981;
            --warning: #f59e0b;
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

        .sa-profile-avatar {
            width: 35px;
            height: 35px;
            border-radius: 999px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid var(--sa-border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .stat-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--sa-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--sa-primary);
        }

        .audit-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--sa-border);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 24px;
            border-bottom: 1px solid var(--sa-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            color: var(--sa-primary);
        }

        .module-table {
            width: 100%;
            border-collapse: collapse;
        }

        .module-table th {
            background: #f1f5f9;
            padding: 14px 24px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .module-table td {
            padding: 18px 24px;
            border-bottom: 1px solid var(--sa-border);
            font-size: 14px;
        }

        .module-table tr:last-child td {
            border-bottom: none;
        }

        .module-table tr:hover {
            background: #fcfdfe;
        }

        .clinic-info {
            display: flex;
            flex-direction: column;
        }

        .clinic-name {
            font-weight: 600;
            color: var(--sa-primary);
        }

        .tier-badge {
            font-size: 11px;
            color: var(--sa-muted);
            margin-top: 2px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-paid {
            background-color: #dcfce7;
            color: #15803d;
        }

        .status-pending {
            background-color: #fef9c3;
            color: #a16207;
        }

        .amount {
            font-weight: 700;
            color: var(--sa-primary);
        }

        .date-cell {
            color: var(--sa-muted);
        }

        .ref-id {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 12px;
            color: var(--sa-muted);
            background: #f8fafc;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .empty-state {
            padding: 60px;
            text-align: center;
            color: var(--sa-muted);
        }
    </style>
</head>
<body>

<div class="container">
    <?php include __DIR__ . '/includes/sidebar_superadmin.php'; ?>

    <main class="main-content">
        <header class="sa-main-header">
            <div>
                <h1>Subscription Audit</h1>
                <span>Track platform subscription revenue and transactions.</span>
            </div>
            <div class="sa-profile">
                <span>Welcome, <strong>Super Admin</strong></span>
                <div class="sa-profile-avatar">🛡️</div>
            </div>
        </header>

        <div class="stats-grid">
            <?php
            $totalRev = 0;
            $paidCount = 0;
            $all_rows = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $all_rows[] = $row;
                    if ($row['status'] === 'paid') {
                        $totalRev += (float)$row['amount'];
                        $paidCount++;
                    }
                }
            }
            ?>
            <div class="stat-card">
                <div class="stat-label">Total Platform Revenue</div>
                <div class="stat-value">₱<?php echo number_format($totalRev, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Successful Subscriptions</div>
                <div class="stat-value"><?php echo $paidCount; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value"><?php echo count($all_rows); ?></div>
            </div>
        </div>

        <div class="audit-card">
            <div class="table-header">
                <h2>Subscription Transactions</h2>
                <div style="font-size: 14px; color: var(--sa-muted);">
                    Last updated: <?php echo date('M d, Y H:i'); ?>
                </div>
            </div>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Clinic Details</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment Mode</th>
                        <th>Reference ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_rows) > 0): ?>
                        <?php foreach ($all_rows as $row): ?>
                        <tr>
                            <td class="date-cell">
                                <?php echo date('M d, Y', strtotime($row['payment_date'])); ?><br>
                                <span style="font-size: 11px; opacity: 0.7;"><?php echo date('h:i A', strtotime($row['payment_date'])); ?></span>
                            </td>
                            <td>
                                <div class="clinic-info">
                                    <span class="clinic-name"><?php echo htmlspecialchars($row['company_name']); ?></span>
                                    <span class="tier-badge">Tier: <?php echo ucfirst($row['current_tier']); ?></span>
                                </div>
                            </td>
                            <td class="amount">₱<?php echo number_format($row['amount'], 2); ?></td>
                            <td>
                                <span class="status-pill status-<?php echo strtolower($row['status']); ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td style="color: #334155; font-weight: 500;">
                                <?php echo htmlspecialchars($row['mode'] ?? 'N/A'); ?>
                            </td>
                            <td>
                                <span class="ref-id"><?php echo htmlspecialchars($row['paymongo_link_id'] ?? 'N/A'); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    No subscription transactions found.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>