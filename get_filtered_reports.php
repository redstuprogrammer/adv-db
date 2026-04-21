<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

// Allow both superadmin and logged in tenant to query reports appropriately
if (empty($_SESSION['superadmin_authed']) && empty($_SESSION['tenant_id'])) {
    header('Location: ' . (empty($_SESSION['superadmin_authed']) ? 'superadmin_login.php' : 'tenant_login.php'));
    exit;
}

$isSuperAdmin = !empty($_SESSION['superadmin_authed']);
$tenantSessionId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';
$activity_type = $_GET['activity_type'] ?? '';

if ($date_from && $date_to && $date_to < $date_from) {
    echo json_encode(['success' => false, 'error' => 'Date To cannot be earlier than Date From.']);
    exit;
}

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
        if (!$isSuperAdmin && $tenantSessionId > 0) {
            $query .= " AND tal.tenant_id = ?";
            $params[] = $tenantSessionId;
        } elseif ($tenant_id) {
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
                'Date' => formatDateReadable($row['log_date']),
                'Activity Type' => $row['activity_type'],
                'Description' => $row['activity_description'],
                'Count' => $row['activity_count'],
                'Tenant' => $row['company_name'] ?? ($tenant_id ? 'Selected Tenant' : 'All Tenants')
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
                'Registration Date' => formatDateReadable($row['created_at']),
                'Clinic Name' => $row['company_name'],
                'Owner' => $row['owner_name'],
                'Email' => $row['contact_email'],
                'Status' => $row['status']
            ];
        }
    } elseif ($type === 'revenue') {
        $query = "SELECT p.first_name, p.last_name, COALESCE(s.service_name, 'General Service') AS service, py.amount, a.appointment_date
                  FROM payment py
                  LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
                  LEFT JOIN patient p ON a.patient_id = p.patient_id
                  LEFT JOIN service s ON a.service_id = s.service_id AND s.tenant_id = py.tenant_id
                  WHERE py.status = 'Paid'";

        $params = [];

        if ($date_from) {
            $query .= " AND a.appointment_date >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $query .= " AND a.appointment_date <= ?";
            $params[] = $date_to;
        }
        if (!$isSuperAdmin && $tenantSessionId > 0) {
            $query .= " AND py.tenant_id = ?";
            $params[] = $tenantSessionId;
        } elseif ($tenant_id) {
            $query .= " AND py.tenant_id = ?";
            $params[] = $tenant_id;
        }

        $query .= " ORDER BY a.appointment_date DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        while ($row = $stmt->fetch()) {
            $data[] = [
                'appointment_date' => $row['appointment_date'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'service' => $row['service'],
                'amount' => $row['amount']
            ];
        }
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
    } elseif ($type === 'revenue') {
        $query = "SELECT p.first_name, p.last_name, COALESCE(py.procedures_json, 'General Service') AS service, py.amount, a.appointment_date as appointment_date, t.company_name as clinic_name
                  FROM payment py
                  JOIN appointment a ON py.appointment_id = a.appointment_id
                  JOIN patient p ON a.patient_id = p.patient_id
                  LEFT JOIN tenants t ON py.tenant_id = t.tenant_id
                  WHERE py.status = 'Paid'";

        $params = [];

        if (!$isSuperAdmin && $tenantSessionId > 0) {
            $query .= " AND py.tenant_id = ?";
            $params[] = $tenantSessionId;
        }

        if ($date_from) {
            $query .= " AND a.appointment_date >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $query .= " AND a.appointment_date <= ?";
            $params[] = $date_to;
        }

        $query .= " ORDER BY a.appointment_date DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        while ($row = $stmt->fetch()) {
            $data[] = [
                'appointment_date' => $row['appointment_date'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'service' => $row['service'],
                'amount' => $row['amount'],
                'clinic_name' => $row['clinic_name'] ?? 'Unknown Clinic'
            ];
        }
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
