<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadminsuperadmin_login.php');
    exit;
}

require_once __DIR__ . '/pdf_generator.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['data'])) {
    http_response_code(400);
    echo 'Invalid data';
    exit;
}

$reportData = $data['data'];
$title = $data['title'] ?? 'OralSync Report';
$type = $data['type'] ?? 'standard';

generatePDF($reportData, $title, 'oralsync_report.pdf', $type);
?>
