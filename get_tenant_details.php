<?php
require_once __DIR__ . '/includes/connect.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM tenants WHERE tenant_id = ? AND status = 'active'");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$tenant = mysqli_fetch_assoc($result);

if ($tenant) {
    $docs_result = mysqli_query($conn, "SELECT * FROM tenant_documents WHERE tenant_id = $id");
    $tenant['documents'] = [];
    while ($doc = mysqli_fetch_assoc($docs_result)) {
        $tenant['documents'][] = $doc;
    }
}

echo json_encode($tenant);
