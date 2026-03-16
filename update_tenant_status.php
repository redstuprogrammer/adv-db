<?php
// update_tenant_status.php
header('Content-Type: application/json');

// Disable error reporting from displaying HTML to the screen
ini_set('display_errors', 0); 
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "", "oralsyncc");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}

$tenant_id = $_POST['tenant_id'] ?? null;
$status = $_POST['status'] ?? null;

if ($tenant_id && $status) {
    // Make sure column names match your SQL: tenant_id and status
    $stmt = $conn->prepare("UPDATE tenants SET status = ? WHERE tenant_id = ?");
    $stmt->bind_param("si", $status, $tenant_id);

    if ($stmt->execute()) {
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