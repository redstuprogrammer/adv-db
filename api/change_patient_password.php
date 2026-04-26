<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

// Parse JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

$patient_id      = isset($data['patient_id'])      ? (int) $data['patient_id']      : 0;
$tenant_id       = isset($data['tenant_id'])       ? (int) $data['tenant_id']       : 0;
$current_password = trim($data['current_password'] ?? '');
$new_password    = trim($data['new_password']      ?? '');

// Validation
if ($patient_id <= 0 || $tenant_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid patient_id and tenant_id are required']);
    exit;
}

if (empty($current_password)) {
    echo json_encode(['success' => false, 'message' => 'Current password is required']);
    exit;
}

if (empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'New password is required']);
    exit;
}

if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters']);
    exit;
}

if ($current_password === $new_password) {
    echo json_encode(['success' => false, 'message' => 'New password must be different from your current password']);
    exit;
}

// Fetch the current password hash
$stmt = $conn->prepare("SELECT password_hash FROM patient WHERE patient_id = ? AND tenant_id = ?");
$stmt->bind_param("ii", $patient_id, $tenant_id);
$stmt->execute();
$stmt->bind_result($password_hash);

if (!$stmt->fetch()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}
$stmt->close();

// Verify current password against stored hash
if (!password_verify($current_password, $password_hash)) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    exit;
}

// Hash the new password using bcrypt (same algo as registration)
$new_hash = password_hash($new_password, PASSWORD_BCRYPT);

// Update
$update = $conn->prepare("UPDATE patient SET password_hash = ? WHERE patient_id = ? AND tenant_id = ?");
$update->bind_param("sii", $new_hash, $patient_id, $tenant_id);

if ($update->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update password. Please try again.'
    ]);
}

$update->close();
$conn->close();
?>