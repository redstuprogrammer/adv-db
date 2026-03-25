<?php
session_start();
require_once __DIR__ . '/security_headers.php';
if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadmin_login.php');
    exit;
}
require_once __DIR__ . '/connect.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';
$activity_type = $_GET['activity_type'] ?? '';

try {
    $data = [];

    if ($type === 'tenant_activity') {
        $query = "SELECT tal.activity_type, tal.activity_description, tal.activity_count, tal.log_date, t.company_name
                  FROM tenant_activity_logs tal
                  LEFT JOIN tenants t ON tal.tenant_id = t.tenant_id
                  WHERE 1=1";

        $params = [];

        if ($date_from) {
            $query .= " AND tal.log_date >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $query .= " AND tal.log_date <= ?";
            $params[] = $date_to;
        }
        if ($tenant_id) {
            $query .= " AND tal.tenant_id = ?";
            $params[] = $tenant_id;
        }
        if ($activity_type) {
            $query .= " AND tal.activity_type = ?";
            $params[] = $activity_type;
        }

        $query .= " ORDER BY tal.log_date DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        while ($row = $stmt->fetch()) {
            $data[] = [
                'Date' => $row['log_date'],
                'Activity Type' => $row['activity_type'],
                'Description' => $row['activity_description'],
                'Count' => $row['activity_count'],
                'Tenant' => $tenant_id ? 'Selected Tenant' : 'All Tenants' // Privacy
            ];
        }
    } elseif ($type === 'user_registration') {
        $query = "SELECT company_name, owner_name, contact_email, status, created_at
                  FROM tenants
                  WHERE 1=1";

        $params = [];

        if ($date_from) {
            $query .= " AND DATE(created_at) >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $query .= " AND DATE(created_at) <= ?";
            $params[] = $date_to;
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        while ($row = $stmt->fetch()) {
            $data[] = [
                'Registration Date' => $row['created_at'],
                'Clinic Name' => $row['company_name'],
                'Owner' => $row['owner_name'],
                'Email' => $row['contact_email'],
                'Status' => $row['status']
            ];
        }
    } elseif ($type === 'usage_statistics') {
        // Aggregate usage stats
        $query = "SELECT
                    COUNT(DISTINCT tal.tenant_id) as active_tenants,
                    SUM(tal.activity_count) as total_activities,
                    tal.activity_type,
                    tal.log_date
                  FROM tenant_activity_logs tal
                  WHERE 1=1";

        $params = [];

        if ($date_from) {
            $query .= " AND tal.log_date >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $query .= " AND tal.log_date <= ?";
            $params[] = $date_to;
        }

        $query .= " GROUP BY tal.activity_type, tal.log_date ORDER BY tal.log_date DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $stats = [
            'total_active_tenants' => 0,
            'total_activities' => 0,
            'activities_by_type' => []
        ];

        while ($row = $stmt->fetch()) {
            $stats['total_active_tenants'] = max($stats['total_active_tenants'], $row['active_tenants']);
            $stats['total_activities'] += $row['total_activities'];
            if (!isset($stats['activities_by_type'][$row['activity_type']])) {
                $stats['activities_by_type'][$row['activity_type']] = 0;
            }
            $stats['activities_by_type'][$row['activity_type']] += $row['total_activities'];
        }

        $data = [
            ['Metric', 'Value'],
            ['Total Active Tenants', $stats['total_active_tenants']],
            ['Total Activities', $stats['total_activities']]
        ];

        foreach ($stats['activities_by_type'] as $type => $count) {
            $data[] = [$type . ' Activities', $count];
        }
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>