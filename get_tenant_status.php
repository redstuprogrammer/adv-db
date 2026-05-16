<?php
/**
 * get_tenant_status.php - Small API to check tenant status by slug
 */
require_once __DIR__ . '/includes/connect.php';

header('Content-Type: application/json');

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    echo json_encode(['error' => 'Missing slug']);
    exit;
}

$stmt = $conn->prepare("SELECT status FROM tenants WHERE subdomain_slug = ? LIMIT 1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode([
    'status' => $row['status'] ?? 'unknown'
]);
