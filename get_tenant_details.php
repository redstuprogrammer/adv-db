<?php
require_once __DIR__ . '/includes/connect.php';
$id = intval($_GET['id']);
$result = mysqli_query($conn, "SELECT * FROM tenants WHERE tenant_id = $id");
echo json_encode(mysqli_fetch_assoc($result));
