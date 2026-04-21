<?php
// 1. Set headers first to ensure the browser knows to expect JSON
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/includes/session_utils.php';
$sessionManager = SessionManager::getInstance();
if (!$sessionManager->isSuperAdmin()) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}
require_once __DIR__ . '/includes/connect.php';

// 3. Check if connection exists
if (!$conn) {
    echo json_encode(["error" => "Connection failed"]);
    exit;
}

$sql = "SELECT * FROM tenants ORDER BY created_at DESC";
$result = $conn->query($sql);

$tenants = [];

if ($result) {
    while($row = $result->fetch_assoc()) {
        // Ensure numeric values are clean and nulls are handled
        $tenants[] = $row;
    }
    // 4. Output the array (even if empty, it will return [] which is valid JSON)
    echo json_encode($tenants);
} else {
    // 5. If the query fails (e.g., table 'tenants' does not exist), return a JSON error
    echo json_encode(["error" => "Query failed: " . $conn->error]);
}

$conn->close();
