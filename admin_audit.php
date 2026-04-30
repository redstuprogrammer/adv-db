<?php
require_once __DIR__ . '/includes/connect.php';

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f172a;
            --secondary: #334155;
            --accent: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body {
            background-color: var(--bg);
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            margin: 0;
            padding: 40px 20px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
            letter-spacing: -0.5px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card);
            padding: 24px;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .stat-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .audit-card {
            background: var(--card);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            color: var(--primary);
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
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .module-table td {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
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
            color: var(--primary);
        }

        .tier-badge {
            font-size: 11px;
            color: var(--text-muted);
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
            color: var(--primary);
        }

        .date-cell {
            color: var(--text-muted);
        }

        .ref-id {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 12px;
            color: var(--text-muted);
            background: #f8fafc;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .empty-state {
            padding: 60px;
            text-align: center;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Platform Audit</h1>
        <div style="font-size: 14px; color: var(--text-muted);">
            Last updated: <?php echo date('M d, Y H:i'); ?>
        </div>
    </div>

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
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">₱<?php echo number_format($totalRev, 2); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Subscriptions</div>
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
        </div>
        <table class="module-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Clinic Details</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Payment Mode</th>
                    <th>Session ID</th>
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
                        <td style="color: var(--secondary); font-weight: 500;">
                            <?php echo htmlspecialchars($row['mode']); ?>
                        </td>
                        <td>
                            <span class="ref-id"><?php echo $row['paymongo_link_id']; ?></span>
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
</div>

</body>
</html>