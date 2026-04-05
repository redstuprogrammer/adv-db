<?php
session_start();
require_once __DIR__ . '/../includes/security_headers.php';

if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadmin_login.php');
    exit;
}

require_once __DIR__ . '/../includes/connect.php';

try {
    // Check tables and record counts
    $tables_to_check = [
        'tenant_subscription_revenue' => 'SELECT COUNT(*) as count FROM tenant_subscription_revenue',
        'tenant_activity_logs' => 'SELECT COUNT(*) as count FROM tenant_activity_logs',
        'superadmin_logs' => 'SELECT COUNT(*) as count FROM superadmin_logs',
        'tenants' => 'SELECT COUNT(*) as count FROM tenants WHERE status = "active"'
    ];
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Data Check</title>
        <style>
            body { font-family: Arial; padding: 20px; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
            th { background: #0d3b66; color: white; }
            .empty { color: red; font-weight: bold; }
            .has-data { color: green; font-weight: bold; }
        </style>
    </head>
    <body>
    <h1>Database Data Check</h1>
    <table>
        <tr>
            <th>Table</th>
            <th>Record Count</th>
            <th>Status</th>
        </tr>";
    
    foreach ($tables_to_check as $table => $query) {
        try {
            $stmt = $pdo->query($query);
            $result = $stmt->fetch();
            $count = $result['count'];
            $status = $count > 0 ? '<span class="has-data">✓ Has Data</span>' : '<span class="empty">✗ No Data</span>';
            echo "<tr>
                <td><strong>$table</strong></td>
                <td>$count</td>
                <td>$status</td>
            </tr>";
        } catch (Exception $e) {
            echo "<tr>
                <td><strong>$table</strong></td>
                <td>-</td>
                <td><span class='empty'>✗ Table not found</span></td>
            </tr>";
        }
    }
    
    echo "</table>
    <hr>
    <p><a href='seed_sample_data.php'>Click here to seed sample data</a></p>
    <p><a href='superadmin_dash.php'>Back to Dashboard</a></p>
    </body>
    </html>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

