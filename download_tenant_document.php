<?php
ini_set('session.gc_maxlifetime', 86400 * 7);
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

define('ROOT_PATH', __DIR__ . '/');
require_once ROOT_PATH . 'includes/security_headers.php';
require_once ROOT_PATH . 'includes/session_utils.php';
$sessionManager = SessionManager::getInstance();
$sessionManager->requireSuperAdmin();
require_once ROOT_PATH . 'includes/connect.php';

$documentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($documentId <= 0) {
    http_response_code(400);
    echo 'Invalid document ID.';
    exit;
}

$stmt = $conn->prepare('SELECT document_name, file_path, file_type FROM tenant_documents WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $documentId);
$stmt->execute();
$result = $stmt->get_result();
$document = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$document) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

$filePath = $document['file_path'];
if (empty($filePath)) {
    http_response_code(404);
    echo 'Document path is missing.';
    exit;
}

$absolutePath = realpath(__DIR__ . '/' . ltrim($filePath, '/\\'));
$uploadsBase = realpath(__DIR__ . '/uploads/tenant_docs');

if (!$absolutePath || !$uploadsBase || strpos($absolutePath, $uploadsBase) !== 0 || !is_file($absolutePath)) {
    http_response_code(404);
    echo 'Document file is unavailable.';
    exit;
}

$documentName = basename($document['document_name'] ?: $absolutePath);
$fileType = $document['file_type'] ?: mime_content_type($absolutePath);
if (!$fileType) {
    $fileType = 'application/octet-stream';
}

$inlineTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
];

header('Content-Description: File Transfer');
header('Content-Type: ' . $fileType);
header('Content-Length: ' . filesize($absolutePath));
header('Cache-Control: private, max-age=10800, must-revalidate');
header('Pragma: public');

if (in_array($fileType, $inlineTypes, true)) {
    header('Content-Disposition: inline; filename="' . addslashes($documentName) . '"');
} else {
    header('Content-Disposition: attachment; filename="' . addslashes($documentName) . '"');
}

readfile($absolutePath);
exit;
