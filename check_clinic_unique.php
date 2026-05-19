<?php
/**
 * check_clinic_unique.php - Public API to check uniqueness of owner email or clinic username
 */
header('Content-Type: application/json');
require_once __DIR__ . '/includes/connect.php';

$field = $_GET['field'] ?? '';
$value = trim($_GET['value'] ?? '');

$response = ['exists' => false];

if (empty($field) || empty($value)) {
    echo json_encode($response);
    exit;
}

if ($field === 'email') {
    $stmt = $conn->prepare("SELECT 1 FROM tenants WHERE contact_email = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $value);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $response['exists'] = true;
        }
        $stmt->close();
    }
} elseif ($field === 'username') {
    $stmt = $conn->prepare("SELECT 1 FROM tenants WHERE username = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $value);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $response['exists'] = true;
        }
        $stmt->close();
    }
}

echo json_encode($response);
exit;
