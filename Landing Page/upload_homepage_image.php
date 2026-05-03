<?php
header('Content-Type: application/json');
require_once '../includes/session_utils.php';
require_once '../includes/connect.php';

$session = SessionManager::getInstance();
if (!$session->isTenantUser()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$tenant_id = $session->getTenantId();
if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'No tenant ID']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowed)]);
        exit;
    }

    if ($file['size'] > 50 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 50MB)']);
        exit;
    }

    $upload_dir = '../uploads/homepage/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $safe_name = 'tenant_' . $tenant_id . '_' . uniqid() . '.' . $ext;
    $dest_path = $upload_dir . $safe_name;

    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        // Return relative path from 'Landing Page' directory
        // Since 'Landing Page' is one level down, and 'uploads' is in root
        $relative_url = '../uploads/homepage/' . $safe_name;
        echo json_encode(['success' => true, 'url' => $relative_url]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
