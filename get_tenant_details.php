<?php
require_once __DIR__ . '/includes/connect.php';
$id = intval($_GET['id']);
$result = mysqli_query($conn, "SELECT * FROM tenants WHERE tenant_id = $id");
$tenant = mysqli_fetch_assoc($result);

if ($tenant) {
    $docs_result = mysqli_query($conn, "SELECT * FROM tenant_documents WHERE tenant_id = $id");
    $tenant['documents'] = [];
    while ($doc = mysqli_fetch_assoc($docs_result)) {
        $tenant['documents'][] = $doc;
    }
}

echo json_encode($tenant);
