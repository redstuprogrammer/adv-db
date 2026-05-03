<?php
ob_start(); 
header('Content-Type: application/json');
require_once __DIR__ . '/includes/connect.php'; 
require_once __DIR__ . '/includes/session_utils.php';

// Check auth
$sessionManager = SessionManager::getInstance();
$sessionManager->requireSuperAdmin();

$response = [
    'success' => false, 
    'message' => 'An unexpected error occurred.'
];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tenantId = (int)($_POST['tenant_id'] ?? 0);
        
        if ($tenantId <= 0) {
            throw new Exception("Invalid tenant ID.");
        }

        // Verify tenant exists
        $checkTenant = mysqli_query($conn, "SELECT company_name FROM tenants WHERE tenant_id = $tenantId");
        $tenant = mysqli_fetch_assoc($checkTenant);
        if (!$tenant) {
            throw new Exception("Tenant not found.");
        }

        $validDocsCount = 0;
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $maxSizeMB = 50; // Increased to 50MB
        $maxSizeBytes = $maxSizeMB * 1024 * 1024;

        if (!isset($_FILES['documents']) || !is_array($_FILES['documents']['tmp_name'])) {
            throw new Exception("No documents provided.");
        }

        $upload_dir = __DIR__ . '/uploads/tenant_docs/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                 throw new Exception("Failed to create upload directory.");
            }
        }
        
        $uploadedFiles = [];
        foreach ($_FILES['documents']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                if ($_FILES['documents']['size'][$key] > $maxSizeBytes) {
                    continue; // Skip oversized files
                }

                $original_name = mysqli_real_escape_string($conn, $_FILES['documents']['name'][$key]);
                $file_type = $_FILES['documents']['type'][$key];
                $file_size = $_FILES['documents']['size'][$key];
                $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $safe_name = uniqid('doc_' . $tenantId . '_') . '.' . $ext;
                    $dest_path = $upload_dir . $safe_name;
                    
                    if (move_uploaded_file($tmp_name, $dest_path)) {
                        $db_path = 'uploads/tenant_docs/' . $safe_name;
                        $doc_sql = "INSERT INTO tenant_documents (tenant_id, document_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)";
                        $doc_stmt = mysqli_prepare($conn, $doc_sql);
                        mysqli_stmt_bind_param($doc_stmt, "isssi", $tenantId, $original_name, $db_path, $file_type, $file_size);
                        if (mysqli_stmt_execute($doc_stmt)) {
                            $validDocsCount++;
                            $uploadedFiles[] = $original_name;
                        }
                        mysqli_stmt_close($doc_stmt);
                    }
                }
            }
        }

        if ($validDocsCount > 0) {
            // Log activity
            require_once __DIR__ . '/includes/tenant_utils.php';
            logSuperAdminActivity($conn, 'Upload', "Uploaded $validDocsCount document(s) for: " . $tenant['company_name'], 'System', 'Super Admin');

            $response = [
                'success' => true,
                'message' => "Successfully uploaded $validDocsCount document(s).",
                'uploaded_count' => $validDocsCount
            ];
        } else {
            throw new Exception("No valid documents were uploaded. Ensure files are under 50MB and are PDF, Word, or Images.");
        }
    } else {
        throw new Exception("Invalid request method.");
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
exit;
