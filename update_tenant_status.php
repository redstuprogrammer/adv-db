<?php
// update_tenant_status.php
header('Content-Type: application/json');

// Disable error reporting from displaying HTML to the screen
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/tenant_utils.php';

$tenant_id = $_POST['tenant_id'] ?? null;
$status = $_POST['status'] ?? null;

if ($tenant_id && $status) {
    // Make sure column names match your SQL: tenant_id and status
    $stmt = $conn->prepare("UPDATE tenants SET status = ? WHERE tenant_id = ?");
    $stmt->bind_param("si", $status, $tenant_id);

    if ($stmt->execute()) {
        // Record activity
        logActivity($conn, (int)$tenant_id, 'Tenant Status Change', "Tenant status changed to $status", null, 'superadmin', 'Super Admin');

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
}

$conn->close();
?>
