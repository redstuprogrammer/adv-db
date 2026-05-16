<?php
// ============================================================
// FILE TYPE: API ENDPOINT
// PATH on server: /api/upload_patient_id.php
// ============================================================
// POST multipart/form-data:
//   patient_id  (int,  required)
//   tenant_id   (int,  required)
//   id_photo    (file, required) — image file (jpg/png/heic)
//
// What it does:
//   1. Validates file type and size (max 5MB)
//   2. Saves file to /uploads/patient_ids/{tenant_id}/
//   3. Sets patient.id_verified = 'pending'
//   4. Sets patient.id_photo_url to the saved path
//
// Returns:
//   { success, message, id_photo_url, id_verified }
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

$patient_id = $_POST['patient_id'] ?? null;
$tenant_id  = $_POST['tenant_id']  ?? null;

if (!$patient_id || !$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'patient_id and tenant_id are required.']);
    exit;
}

// ─── Validate file ─────────────────────────────────────────
if (!isset($_FILES['id_photo']) || $_FILES['id_photo']['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => 'File too large (server limit).',
        UPLOAD_ERR_FORM_SIZE  => 'File too large.',
        UPLOAD_ERR_PARTIAL    => 'File upload incomplete.',
        UPLOAD_ERR_NO_FILE    => 'No file uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server write error.',
    ];
    $err_code = $_FILES['id_photo']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['success' => false, 'message' => $upload_errors[$err_code] ?? 'Upload error.']);
    exit;
}

$file      = $_FILES['id_photo'];
$max_size  = 5 * 1024 * 1024; // 5MB

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
    exit;
}

// Validate MIME type (don't trust extension alone)
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime      = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed_mimes = ['image/jpeg', 'image/png', 'image/heic', 'image/webp'];
if (!in_array($mime, $allowed_mimes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload a JPG, PNG, HEIC, or WebP image.']);
    exit;
}

$ext_map = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/heic' => 'heic',
    'image/webp' => 'webp',
];
$ext = $ext_map[$mime];

// ─── Build destination path ────────────────────────────────
$upload_dir = __DIR__ . '/../uploads/patient_ids/' . intval($tenant_id) . '/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Filename: patient_{id}_{timestamp}.{ext}
$filename    = 'patient_' . intval($patient_id) . '_' . time() . '.' . $ext;
$destination = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file. Please try again.']);
    exit;
}

// Relative URL stored in DB (served via your API base)
$id_photo_url = '/uploads/patient_ids/' . intval($tenant_id) . '/' . $filename;

// ─── Update patient record ─────────────────────────────────
$upd = $conn->prepare("
    UPDATE patient
    SET id_photo_url = ?, id_verified = 'pending'
    WHERE patient_id = ?
");
$upd->bind_param("si", $id_photo_url, $patient_id);

if (!$upd->execute()) {
    echo json_encode(['success' => false, 'message' => 'File saved but DB update failed: ' . $upd->error]);
    $upd->close(); $conn->close(); exit;
}
$upd->close();
$conn->close();

echo json_encode([
    'success'      => true,
    'message'      => 'ID uploaded successfully. The clinic will verify your ID shortly.',
    'id_photo_url' => $id_photo_url,
    'id_verified'  => 'pending',
]);
?>