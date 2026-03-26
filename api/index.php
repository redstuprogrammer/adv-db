<?php
/**
 * API Router Proxy
 * 
 * Routes all /api/* requests to their respective PHP files
 * Workaround for Azure Free tier blocking POST to /api/*.php directly
 * 
 * Usage: POST /api/patient_login.php → handled by this file
 */

// Get the requested file from the query string or path
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api/';

// Extract the PHP file name from the request
// e.g., /api/patient_login.php → patient_login.php
if (strpos($requestUri, $basePath) === 0) {
    $relPath = substr($requestUri, strlen($basePath));
    // Remove query string
    $relPath = explode('?', $relPath)[0];
    
    // Ensure it's a PHP file and prevent directory traversal
    if (!empty($relPath) && preg_match('/^[a-zA-Z0-9_\-\.]+\.php$/', $relPath)) {
        $filePath = __DIR__ . '/' . $relPath;
        
        // Security: Make sure the file exists and is in /api directory
        if (file_exists($filePath) && realpath($filePath) === realpath(__DIR__) . DIRECTORY_SEPARATOR . $relPath) {
            // Include the actual API file
            include $filePath;
            exit;
        }
    }
}

// File not found or invalid request
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'API endpoint not found']);
?>
