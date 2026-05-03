<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/tenant_tier_helper.php';

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
$page = isset($_GET['page']) ? (int)$_GET['page'] : null;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page !== null) ? ($page - 1) * $per_page : 0;

if (!$isSuperAdmin && $tenantSessionId > 0) {
    if (!tenantHasTierFeature($tenantSessionId, 'basic_reporting', $conn)) {
        echo json_encode(['success' => false, 'error' => 'Reporting is not available on your current plan.']);
        exit;
    }
    if ($type === 'revenue' && !tenantHasTierFeature($tenantSessionId, 'advanced_reporting', $conn)) {
        echo json_encode(['success' => false, 'error' => 'Advanced revenue reporting is only available on Professional plan.']);
        exit;
    }
}

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

        // Get total count for pagination
        $countQuery = "SELECT COUNT(*) FROM tenant_activity_logs tal WHERE 1=1";
        $countParams = [];
        if ($date_from) { $countQuery .= " AND tal.log_date >= ?"; $countParams[] = $date_from; }
        if ($date_to) { $countQuery .= " AND tal.log_date <= ?"; $countParams[] = $date_to; }
        if (!$isSuperAdmin && $tenantSessionId > 0) { $countQuery .= " AND tal.tenant_id = ?"; $countParams[] = $tenantSessionId; }
        elseif ($tenant_id) { $countQuery .= " AND tal.tenant_id = ?"; $countParams[] = $tenant_id; }
        if ($activity_type) { $countQuery .= " AND tal.activity_type = ?"; $countParams[] = $activity_type; }
        
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($countParams);
        $total_count = $countStmt->fetchColumn();

        $stmt = $pdo->prepare($query . ($page !== null ? " LIMIT ? OFFSET ?" : ""));
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        if ($page !== null) {
            $stmt->bindValue(count($params) + 1, $per_page, PDO::PARAM_INT);
            $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $data[] = [
                'Date' => formatDateReadable($row['log_date']),
                'Activity Type' => $row['activity_type'],
                'Description' => $row['activity_description'],
                'Count' => $row['activity_count'],
                'Tenant' => $row['company_name'] ?? ($tenant_id ? 'Selected Tenant' : 'All Tenants')
            ];
        }
    } elseif ($type === 'usage_statistics') {
        $query = "
            SELECT 
                t.company_name AS Tenant,
                t.subscription_tier AS Tier,
                COALESCE(pt.cnt, 0) AS Patients,
                COALESCE(ap.cnt, 0) AS Appointments,
                COALESCE(st.cnt, 0) AS Staff,
                COALESCE(dt.cnt, 0) AS Dentists,
                COALESCE(pym.total_amount, 0) AS Revenue,
                COALESCE(notes.cnt, 0) AS Notes,
                COALESCE(logs.activity_count, 0) AS Activities
            FROM tenants t
            LEFT JOIN (SELECT tenant_id, COUNT(*) cnt FROM patient GROUP BY tenant_id) pt ON t.tenant_id = pt.tenant_id
            LEFT JOIN (SELECT tenant_id, COUNT(*) cnt FROM appointment GROUP BY tenant_id) ap ON t.tenant_id = ap.tenant_id
            LEFT JOIN (SELECT tenant_id, COUNT(*) cnt FROM staff_details GROUP BY tenant_id) st ON t.tenant_id = st.tenant_id
            LEFT JOIN (SELECT tenant_id, COUNT(*) cnt FROM dentist GROUP BY tenant_id) dt ON t.tenant_id = dt.tenant_id
            LEFT JOIN (SELECT tenant_id, SUM(amount) total_amount FROM payment GROUP BY tenant_id) pym ON t.tenant_id = pym.tenant_id
            LEFT JOIN (SELECT tenant_id, COUNT(*) cnt FROM clinical_notes GROUP BY tenant_id) notes ON t.tenant_id = notes.tenant_id
            LEFT JOIN (SELECT tenant_id, COUNT(*) activity_count FROM tenant_activity_logs GROUP BY tenant_id) logs ON t.tenant_id = logs.tenant_id
            WHERE 1=1";

        $params = [];
        if ($date_from) {
            $query .= " AND logs.log_date >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $query .= " AND logs.log_date <= ?";
            $params[] = $date_to;
        }
        if ($tenant_id) {
            $query .= " AND t.tenant_id = ?";
            $params[] = $tenant_id;
        }
        $query .= " ORDER BY t.company_name";

        // For usage stats, the count is simply the number of tenants matching filters (if any)
        $countQuery = "SELECT COUNT(*) FROM tenants t WHERE 1=1";
        $countParams = [];
        if ($tenant_id) { $countQuery .= " AND t.tenant_id = ?"; $countParams[] = $tenant_id; }
        
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($countParams);
        $total_count = $countStmt->fetchColumn();

        $stmt = $pdo->prepare($query . ($page !== null ? " LIMIT ? OFFSET ?" : ""));
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        if ($page !== null) {
            $stmt->bindValue(count($params) + 1, $per_page, PDO::PARAM_INT);
            $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $data[] = $row;
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

        // Get total count
        $countQuery = "SELECT COUNT(*) FROM tenants WHERE 1=1";
        $countParams = [];
        if ($date_from) { $countQuery .= " AND DATE(created_at) >= ?"; $countParams[] = $date_from; }
        if ($date_to) { $countQuery .= " AND DATE(created_at) <= ?"; $countParams[] = $date_to; }
        
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($countParams);
        $total_count = $countStmt->fetchColumn();

        $stmt = $pdo->prepare($query . ($page !== null ? " LIMIT ? OFFSET ?" : ""));
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        if ($page !== null) {
            $stmt->bindValue(count($params) + 1, $per_page, PDO::PARAM_INT);
            $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        }
        $stmt->execute();

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
        $query = "SELECT p.first_name, p.last_name, COALESCE(s.service_name, 'General Service') AS service, 
                         py.amount_paid as amount, a.appointment_date, py.payment_type, py.payment_status, py.source
                  FROM billing py
                  LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
                  LEFT JOIN patient p ON a.patient_id = p.patient_id
                  LEFT JOIN service s ON a.service_id = s.service_id AND s.tenant_id = py.tenant_id
                  WHERE py.payment_status IN ('paid', 'partial')";

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

        // Get total count
        $countQuery = "SELECT COUNT(*) FROM billing py LEFT JOIN appointment a ON py.appointment_id = a.appointment_id WHERE py.payment_status IN ('paid', 'partial')";
        $countParams = [];
        if ($date_from) { $countQuery .= " AND a.appointment_date >= ?"; $countParams[] = $date_from; }
        if ($date_to) { $countQuery .= " AND a.appointment_date <= ?"; $countParams[] = $date_to; }
        if (!$isSuperAdmin && $tenantSessionId > 0) { $countQuery .= " AND py.tenant_id = ?"; $countParams[] = $tenantSessionId; }
        elseif ($tenant_id) { $countQuery .= " AND py.tenant_id = ?"; $countParams[] = $tenant_id; }
        
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($countParams);
        $total_count = $countStmt->fetchColumn();

        $stmt = $pdo->prepare($query . ($page !== null ? " LIMIT ? OFFSET ?" : ""));
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        if ($page !== null) {
            $stmt->bindValue(count($params) + 1, $per_page, PDO::PARAM_INT);
            $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        }
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $data[] = [
                'appointment_date' => $row['appointment_date'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'service' => $row['service'],
                'amount' => $row['amount'],
                'payment_type' => $row['payment_type'],
                'payment_status' => $row['payment_status'],
                'source' => $row['source']
            ];
        }
    }

    echo json_encode([
        'success' => true, 
        'data' => $data,
        'pagination' => ($page !== null) ? [
            'total_count' => (int)$total_count,
            'total_pages' => ceil($total_count / $per_page),
            'current_page' => $page,
            'per_page' => $per_page
        ] : null
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
