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

$isSuperAdmin    = !empty($_SESSION['superadmin_authed']);
$tenantSessionId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;

header('Content-Type: application/json');

$type          = $_GET['type']          ?? '';
$date_from     = $_GET['date_from']     ?? '';
$date_to       = $_GET['date_to']       ?? '';
$tenant_id     = $_GET['tenant_id']     ?? '';
$activity_type = $_GET['activity_type'] ?? '';
$page          = isset($_GET['page'])     ? (int)$_GET['page']     : null;
$per_page      = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset        = ($page !== null) ? ($page - 1) * $per_page : 0;

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

/**
 * Execute a mysqli prepared statement with a dynamic param list.
 * $types  – mysqli bind_param type string, e.g. 'ssii'
 * $params – array of values (must match $types length)
 * Returns the mysqli_result on success or throws RuntimeException.
 */
function mysqli_run(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_result|bool
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error . ' | SQL: ' . $sql);
    }
    if ($types !== '' && count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException('Execute failed: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

/** Fetch a single scalar value (first column of first row). */
function mysqli_scalar(mysqli $conn, string $sql, string $types = '', array $params = []): mixed
{
    $result = mysqli_run($conn, $sql, $types, $params);
    if (!$result) return null;
    $row = $result->fetch_row();
    return $row[0] ?? null;
}

/** Build bind_param type string: 's' for strings/dates, 'i' for ints. */
function infer_types(array $params): string
{
    $types = '';
    foreach ($params as $v) {
        $types .= is_int($v) ? 'i' : 's';
    }
    return $types;
}

try {
    $data        = [];
    $total_count = 0;
    $grand_total = 0.0;

    // -------------------------------------------------------------------------
    // Tenant Activity
    // -------------------------------------------------------------------------
    if ($type === 'tenant_activity') {
        $where  = '';
        $params = [];

        if ($date_from)                              { $where .= ' AND tal.log_date >= ?';     $params[] = $date_from; }
        if ($date_to)                                { $where .= ' AND tal.log_date <= ?';     $params[] = $date_to; }
        if (!$isSuperAdmin && $tenantSessionId > 0)  { $where .= ' AND tal.tenant_id = ?';    $params[] = $tenantSessionId; }
        elseif ($tenant_id)                          { $where .= ' AND tal.tenant_id = ?';    $params[] = (int)$tenant_id; }
        if ($activity_type)                          { $where .= ' AND tal.activity_type = ?'; $params[] = $activity_type; }

        $baseFrom = 'FROM tenant_activity_logs tal LEFT JOIN tenants t ON tal.tenant_id = t.tenant_id WHERE 1=1' . $where;

        $total_count = (int)mysqli_scalar($conn,
            'SELECT COUNT(*) ' . $baseFrom,
            infer_types($params), $params
        );

        $mainSql = 'SELECT tal.log_id, tal.log_time, tal.log_date, tal.activity_type,
                           tal.activity_description, tal.activity_count, t.company_name
                    ' . $baseFrom . ' ORDER BY tal.log_date DESC';

        $mainParams = $params;
        $mainTypes  = infer_types($mainParams);
        if ($page !== null) {
            $mainSql     .= ' LIMIT ? OFFSET ?';
            $mainParams[] = $per_page;
            $mainParams[] = $offset;
            $mainTypes   .= 'ii';
        }

        $result = mysqli_run($conn, $mainSql, $mainTypes, $mainParams);
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'log_id'               => $row['log_id'],
                'log_time'             => $row['log_time'],
                'log_date'             => $row['log_date'],
                'activity_type'        => $row['activity_type'],
                'activity_description' => $row['activity_description'],
                'activity_count'       => $row['activity_count'],
            ];
        }

    // -------------------------------------------------------------------------
    // Usage Statistics
    // -------------------------------------------------------------------------
    } elseif ($type === 'usage_statistics') {
        $where  = '';
        $params = [];

        if ($tenant_id) { $where .= ' AND t.tenant_id = ?'; $params[] = (int)$tenant_id; }

        $total_count = (int)mysqli_scalar($conn,
            'SELECT COUNT(*) FROM tenants t WHERE 1=1' . $where,
            infer_types($params), $params
        );

        $mainSql = "SELECT t.company_name AS Tenant,
                           t.subscription_tier AS Tier,
                           CONCAT('₱', FORMAT(COALESCE(pym.total_amount, 0), 2)) AS Sales,
                           COALESCE(logs.activity_count, 0) AS Activities
                    FROM tenants t
                    LEFT JOIN (SELECT tenant_id, SUM(amount) total_amount FROM payment GROUP BY tenant_id) pym
                           ON t.tenant_id = pym.tenant_id
                    LEFT JOIN (SELECT tenant_id, COUNT(*) activity_count FROM tenant_activity_logs GROUP BY tenant_id) logs
                           ON t.tenant_id = logs.tenant_id
                    WHERE 1=1{$where}
                    ORDER BY t.company_name";

        $mainParams = $params;
        $mainTypes  = infer_types($mainParams);
        if ($page !== null) {
            $mainSql     .= ' LIMIT ? OFFSET ?';
            $mainParams[] = $per_page;
            $mainParams[] = $offset;
            $mainTypes   .= 'ii';
        }

        $result = mysqli_run($conn, $mainSql, $mainTypes, $mainParams);
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

    // -------------------------------------------------------------------------
    // User Registration
    // -------------------------------------------------------------------------
    } elseif ($type === 'user_registration') {
        $where  = '';
        $params = [];

        if ($date_from) { $where .= ' AND DATE(created_at) >= ?'; $params[] = $date_from; }
        if ($date_to)   { $where .= ' AND DATE(created_at) <= ?'; $params[] = $date_to; }

        $total_count = (int)mysqli_scalar($conn,
            'SELECT COUNT(*) FROM tenants WHERE 1=1' . $where,
            infer_types($params), $params
        );

        $mainSql = 'SELECT company_name, owner_name, contact_email, status, created_at
                    FROM tenants WHERE 1=1' . $where . ' ORDER BY created_at DESC';

        $mainParams = $params;
        $mainTypes  = infer_types($mainParams);
        if ($page !== null) {
            $mainSql     .= ' LIMIT ? OFFSET ?';
            $mainParams[] = $per_page;
            $mainParams[] = $offset;
            $mainTypes   .= 'ii';
        }

        $result = mysqli_run($conn, $mainSql, $mainTypes, $mainParams);
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'Registration Date' => formatDateReadable($row['created_at']),
                'Clinic Name'       => $row['company_name'],
                'Owner'             => $row['owner_name'],
                'Email'             => $row['contact_email'],
                'Status'            => $row['status'],
            ];
        }

    // -------------------------------------------------------------------------
    // Revenue (Sales Performance)
    // -------------------------------------------------------------------------
    } elseif ($type === 'revenue') {
        $where  = '';
        $params = [];

        if ($date_from)                              { $where .= ' AND a.appointment_date >= ?'; $params[] = $date_from; }
        if ($date_to)                                { $where .= ' AND a.appointment_date <= ?'; $params[] = $date_to; }
        if (!$isSuperAdmin && $tenantSessionId > 0)  { $where .= ' AND py.tenant_id = ?';       $params[] = $tenantSessionId; }
        elseif ($tenant_id)                          { $where .= ' AND py.tenant_id = ?';       $params[] = (int)$tenant_id; }

        $baseFrom = "FROM billing py
                     LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
                     LEFT JOIN patient p     ON a.patient_id      = p.patient_id
                     LEFT JOIN service s     ON a.service_id      = s.service_id AND s.tenant_id = py.tenant_id
                     WHERE py.payment_status IN ('paid', 'partial')" . $where;

        $total_count = (int)mysqli_scalar($conn,
            'SELECT COUNT(*) ' . $baseFrom,
            infer_types($params), $params
        );

        $grand_total = (float)(mysqli_scalar($conn,
            'SELECT COALESCE(SUM(py.amount_paid), 0) ' . $baseFrom,
            infer_types($params), $params
        ) ?? 0);

        $mainSql = "SELECT p.first_name, p.last_name,
                           COALESCE(s.service_name, 'General Service') AS service,
                           py.amount_paid AS amount, a.appointment_date,
                           py.payment_type, py.payment_status, py.source
                    " . $baseFrom . ' ORDER BY a.appointment_date DESC';

        $mainParams = $params;
        $mainTypes  = infer_types($mainParams);
        if ($page !== null) {
            $mainSql     .= ' LIMIT ? OFFSET ?';
            $mainParams[] = $per_page;
            $mainParams[] = $offset;
            $mainTypes   .= 'ii';
        }

        // grand_total needs its own param copy — reuse $params (already bound above separately)
        $result = mysqli_run($conn, $mainSql, $mainTypes, $mainParams);
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'appointment_date' => $row['appointment_date'],
                'first_name'       => $row['first_name'],
                'last_name'        => $row['last_name'],
                'service'          => $row['service'],
                'amount'           => $row['amount'],
                'payment_type'     => $row['payment_type'],
                'payment_status'   => $row['payment_status'],
                'source'           => $row['source'],
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data'    => $data,
        'pagination' => ($page !== null) ? [
            'total_count'  => (int)$total_count,
            'total_pages'  => (int)ceil($total_count / $per_page),
            'current_page' => $page,
            'per_page'     => $per_page,
            'grand_total'  => $grand_total,
        ] : null,
    ]);

} catch (Exception $e) {
    error_log('get_filtered_reports error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
