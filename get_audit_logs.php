<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/tenant_utils.php';
if (empty($_SESSION['superadmin_authed'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/includes/connect.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

// Filters
$date_filter = $_GET['date'] ?? '';
$user_type_filter = $_GET['user_type'] ?? '';
$action_filter = $_GET['action'] ?? '';

try {
    // Build the base query with individual WHERE clauses for each part
    $whereSuperAdmin = [];
    $superAdminParams = [];

    if ($date_filter) {
        $whereSuperAdmin[] = "DATE(CONCAT(log_date, ' ', log_time)) = ?";
        $superAdminParams[] = $date_filter;
    }

    if ($action_filter) {
        $whereSuperAdmin[] = "activity_type LIKE ?";
        $superAdminParams[] = "%$action_filter%";
    }

    $whereSuperAdminClause = !empty($whereSuperAdmin) ? " WHERE " . implode(" AND ", $whereSuperAdmin) : "";
    $whereSuperAdminClause = !empty($whereSuperAdmin) ? " WHERE " . implode(" AND ", $whereSuperAdmin) : "";

    $baseQuery = "
        SELECT 
            CONCAT(log_date, ' ', log_time) as timestamp,
            COALESCE(username, admin_name, 'System') as user,
            activity_type as action_type,
            COALESCE(action_details, 'N/A') as details,
            'Super Admin' as source
        FROM superadmin_logs
        $whereSuperAdminClause
    ";
    $params = $superAdminParams;

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM ($baseQuery) as combined_logs";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalRecords = $stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);

    // Add ORDER BY and LIMIT for data (LIMIT and OFFSET must not use placeholders in PDO)
    $dataQuery = "$baseQuery ORDER BY timestamp DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    $stmt = $pdo->prepare($dataQuery);
    $stmt->execute($params);

    $logs = [];
    while ($log = $stmt->fetch()) {
        $logs[] = [
            'timestamp' => formatTo12Hour($log['timestamp'], 'M d, Y g:i A'),
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
