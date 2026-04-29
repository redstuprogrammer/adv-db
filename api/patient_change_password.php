<?php
/**
 * =============================================================================
 * PATIENT CHANGE PASSWORD — FINAL (Phase 1.4)
 * =============================================================================
 * Endpoint: POST /api/patient_change_password.php
 *
 * Used for both:
 * → Forced first-login password change (clears must_change_password flag)
 * → Voluntary password change from Profile tab
 * =============================================================================
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$patient_id       = (int)($input['patient_id']       ?? 0);
$current_password = $input['current_password']        ?? '';
$new_password     = $input['new_password']            ?? '';

if (!$patient_id || empty($current_password) || empty($new_password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'patient_id, current_password and new_password are required']);
    exit;
}

if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters']);
    exit;
}

if ($current_password === $new_password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password must be different from your current password']);
    exit;
}

try {
    $stmt = $pdo->prepare('
        SELECT patient_id, password_hash FROM patient WHERE patient_id = ? LIMIT 1
    ');
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit;
    }

    if (!password_verify($current_password, $patient['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }

    $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare('
        UPDATE patient
        SET password_hash        = ?,
            must_change_password = 0
        WHERE patient_id = ?
    ');
    $stmt->execute([$new_hash, $patient_id]);

    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);

} catch (\PDOException $e) {
    error_log('Change password error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
}
?>