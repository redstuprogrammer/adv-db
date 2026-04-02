<?php
session_start();
require_once __DIR__ . '/security_headers.php';

if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadmin_login.php');
    exit;
}

require_once __DIR__ . '/connect.php';

// Test connection and data availability
$status = [
    'pdo_connection' => false,
    'tables_exist' => [],
    'data_counts' => []
];

try {
    // Test PDO connection
    $test = $pdo->query("SELECT 1");
    $status['pdo_connection'] = true;
} catch (Exception $e) {
    $status['pdo_connection'] = false;
    $error_msg = $e->getMessage();
}

// Check table counts
$tables = [
    'tenant_subscription_revenue' => 'Sales Data',
    'tenant_activity_logs' => 'Audit Logs',
    'superadmin_logs' => 'Admin Logs',
    'tenants' => 'Registered Clinics'
];

foreach ($tables as $table => $label) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $result = $stmt->fetch();
        $status['data_counts'][$label] = $result['count'] ?? 0;
    } catch (Exception $e) {
        $status['tables_exist'][$label] = false;
    }
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>System Status Check</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; background: #f8fafc; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; padding: 24px; margin: 16px 0; border: 1px solid #e2e8f0; }
        .header { color: #0d3b66; font-size: 24px; font-weight: 800; margin-bottom: 24px; }
        .status-row { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #e2e8f0; }
        .status-label { flex: 1; font-weight: 600; color: #0f172a; }
        .status-badge { padding: 6px 12px; border-radius: 999px; font-weight: 600; font-size: 0.9rem; }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .action-btn { background: #0d3b66; color: white; padding: 10px 20px; border-radius: 999px; border: none; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; }
        .action-btn:hover { background: #0a2f52; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='card'>
            <div class='header'>🔍 System Status Check</div>
            
            <div class='status-row'>
                <span class='status-label'>PDO Database Connection</span>
                <span class='status-badge " . ($status['pdo_connection'] ? 'badge-ok' : 'badge-error') . "'>" . 
                    ($status['pdo_connection'] ? '✓ Connected' : '✗ Failed') . 
                "</span>
            </div>";

echo "<div class='status-row' style='font-weight: 700; color: #0d3b66; margin-top: 20px; margin-bottom: 12px;'>Data Counts:</div>";

foreach ($status['data_counts'] as $label => $count) {
    $badge_class = $count > 0 ? 'badge-ok' : 'badge-warning';
    $status_text = $count > 0 ? "✓ " . number_format($count) . " records" : "⚠ No data yet";
    echo "<div class='status-row'>
        <span class='status-label'>$label</span>
        <span class='status-badge $badge_class'>$status_text</span>
    </div>";
}

echo "        </div>
        <div class='card' style='text-align: center;'>
            <h3 style='color: #0d3b66; margin-bottom: 16px;'>Next Steps</h3>";

// Check if seeding is needed
$need_seed = array_sum($status['data_counts']) < 10;
if ($need_seed) {
    echo "<p style='color: #64748b; margin-bottom: 16px;'>No sample data detected. Seed data to populate reports:</p>
    <a href='seed_sample_data.php' class='action-btn'>Seed Sample Data</a>";
} else {
    echo "<p style='color: #22c55e; font-weight: 700;'>✓ Data is available!</p>";
}

echo "            <p style='margin-top: 20px;'>
                <a href='superadmin_dash.php' class='action-btn' style='background: #22c55e;'>Go to Dashboard</a> &nbsp;
                <a href='superadmin_sales_report.php' class='action-btn' style='background: #3b82f6;'>Sales Reports</a> &nbsp;
                <a href='superadmin_audit_logs.php' class='action-btn' style='background: #f59e0b;'>Audit Logs</a>
            </p>
        </div>
    </div>
</body>
</html>";
?>
