<?php
header('Content-Type: application/json');

$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api/';

if (strpos($requestUri, $basePath) === 0) {
    $relPath = substr($requestUri, strlen($basePath));
    $relPath = explode('?', $relPath)[0];
    
    // Prevent directory traversal and self-inclusion
    if (!empty($relPath) && $relPath !== 'index.php' && preg_match('/^[a-zA-Z0-9_\-\.]+\.php$/', $relPath)) {
        $filePath = __DIR__ . DIRECTORY_SEPARATOR . $relPath;
        
        if (file_exists($filePath)) {
            try {
                include $filePath;
                exit;
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Execution error', 'details' => $e->getMessage()]);
                exit;
            }
        }
    }
}

http_response_code(404);
echo json_encode(['error' => 'Endpoint not found', 'path' => $requestUri]);