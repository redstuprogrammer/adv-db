<?php
session_start();
require_once __DIR__ . '/security_headers.php';
if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadmin_login.php');
    exit;
}
require_once __DIR__ . '/connect.php';

header('Content-Type: application/json');

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

// Filters
$date_filter = $_GET['date'] ?? '';
$user_filter = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';

try {
    // Build the base query with individual WHERE clauses for each UNION part
    $whereSuperAdmin = [];
    $whereTenant = [];
    $params = [];

    if ($date_filter) {
        $whereSuperAdmin[] = "DATE(CONCAT(log_date, ' ', log_time)) = ?";
        $whereTenant[] = "DATE(CONCAT(log_date, ' ', log_time)) = ?";
        $params[] = $date_filter;
        $params[] = $date_filter;
    }

    if ($user_filter) {
        $whereSuperAdmin[] = "COALESCE(username, admin_name, 'System') LIKE ?";
        $whereTenant[] = "'Tenant' LIKE ?";
        $params[] = "%$user_filter%";
        $params[] = "%$user_filter%";
    }

    if ($action_filter) {
        $whereSuperAdmin[] = "activity_type LIKE ?";
        $whereTenant[] = "activity_type LIKE ?";
        $params[] = "%$action_filter%";
        $params[] = "%$action_filter%";
    }

    $whereSuperAdminClause = !empty($whereSuperAdmin) ? " WHERE " . implode(" AND ", $whereSuperAdmin) : "";
    $whereTenantClause = !empty($whereTenant) ? " WHERE " . implode(" AND ", $whereTenant) : "";

    $baseQuery = "
        SELECT 
            CONCAT(log_date, ' ', log_time) as timestamp,
            COALESCE(username, admin_name, 'System') as user,
            activity_type as action_type,
            COALESCE(action_details, activity_description, 'N/A') as details,
            'Super Admin' as source
        FROM superadmin_logs
        $whereSuperAdminClause
        UNION ALL
        SELECT 
            CONCAT(log_date, ' ', log_time) as timestamp,
            'Tenant' as user,
            activity_type as action_type,
            CASE 
                WHEN activity_type = 'Patient Created' THEN 'Tenant created a patient record'
                WHEN activity_type = 'Appointment Scheduled' THEN 'Tenant booked an appointment'
                WHEN activity_type = 'Payment Received' THEN 'Tenant received a payment'
                WHEN activity_type = 'Staff Added' THEN 'Tenant added staff member'
                WHEN activity_type = 'Clinical Notes' THEN 'Tenant added clinical notes'
                ELSE CONCAT('Tenant performed: ', activity_type)
            END as details,
            'Tenant' as source
        FROM tenant_activity_logs
        $whereTenantClause
    ";

    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM ($baseQuery) as combined_logs";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);

    // Get paginated data
    $dataQuery = "$baseQuery ORDER BY timestamp DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($dataQuery);
    $stmt->execute($params);

    $logs = [];
    while ($log = $stmt->fetch()) {
        $logs[] = [
            'timestamp' => $log['timestamp'],
            'user' => $log['user'],
            'action_type' => $log['action_type'],
            'details' => $log['details']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $logs,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'limit' => $limit
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>