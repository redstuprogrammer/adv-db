<?php
/**
 * check_clinic_unique.php
 * AJAX endpoint used by the Super Admin Register Clinic form.
 * Checks whether a given email or username is already taken in the `tenants` table.
 *
 * Query params:
 *   field = 'email' | 'username'
 *   value = the value to check
 *
 * Returns JSON: { "exists": true|false }
 */

define('ROOT_PATH', __DIR__ . '/');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}
require_once ROOT_PATH . 'includes/security_headers.php';
require_once ROOT_PATH . 'includes/session_utils.php';

header('Content-Type: application/json');

// Only super admins may use this endpoint
$sessionManager = SessionManager::getInstance();
if (!$sessionManager->isSuperAdmin()) {
    echo json_encode(['exists' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once ROOT_PATH . 'includes/connect.php';

$field = $_GET['field'] ?? '';
$value = trim($_GET['value'] ?? '');

if ($value === '' || !in_array($field, ['email', 'username'], true)) {
    echo json_encode(['exists' => false]);
    exit;
}

$column = ($field === 'email') ? 'contact_email' : 'username';

$stmt = $conn->prepare("SELECT tenant_id FROM tenants WHERE $column = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['exists' => false, 'error' => 'Query error']);
    exit;
}

$stmt->bind_param('s', $value);
$stmt->execute();
$result = $stmt->get_result();
$exists = ($result && $result->num_rows > 0);
$stmt->close();

echo json_encode(['exists' => $exists]);
