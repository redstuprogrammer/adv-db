<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';

// Check if superadmin or tenant admin
$isSuperAdmin = !empty($_SESSION['superadmin_authed']);
$isTenantAdmin = isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin';

if (!$isSuperAdmin && !$isTenantAdmin) {
    header('Location: ' . ($isSuperAdmin ? 'superadmin_login.php' : 'tenant_login.php'));
    exit;
}

require_once __DIR__ . '/csv_generator.php';
require_once __DIR__ . '/get_filtered_reports.php'; // Reuse the data fetching logic

// Get parameters
$type = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';
$activity_type = $_GET['activity_type'] ?? '';

if (empty($type)) {
    http_response_code(400);
    echo 'Report type is required';
    exit;
}

// Fetch data using existing logic
$queryParams = [
    'type' => $type,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'tenant_id' => $tenant_id,
    'activity_type' => $activity_type
];

$queryString = http_build_query($queryParams);
$url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/get_filtered_reports.php?' . $queryString;

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Cookie: ' . session_name() . '=' . session_id() . "\r\n"
    ]
]);

$response = file_get_contents($url, false, $context);
$data = json_decode($response, true);

if (!$data || !isset($data['data'])) {
    http_response_code(500);
    echo 'Failed to fetch report data';
    exit;
}

$reportData = $data['data'];

// Generate filename
$filename = 'oralsync_' . $type . '_report_' . date('Y-m-d') . '.csv';

// Generate and output CSV
$generator = new OralSyncCSVGenerator();
$generator->generateCSV($reportData, null, $filename);
?>