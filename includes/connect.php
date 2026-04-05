<?php
// Database configuration - Local vs Azure
if (gethostname() === 'DESKTOP-' . substr(gethostname(), 8) || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) {
    // Local development
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "oral";
    $port = 3306;
    $ssl_cert = null;
} else {
    // Azure production
    $host = "oralsync-db.mysql.database.azure.com";
    $user = "oralsync";
    $pass = "Oralsync1";
    $db   = "oral";
    $port = 3306;
    $ssl_cert = dirname(__DIR__) . '/azure-combined-2026.pem';
}

$conn = mysqli_init();

if ($ssl_cert && file_exists($ssl_cert)) {
    mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
}

mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

$flags = $ssl_cert ? MYSQLI_CLIENT_SSL : 0;
$connection_failed = false;

if (!mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, $flags)) {
    $connection_error = mysqli_connect_error();
    error_log('MySQLi connection failed: ' . $connection_error);
    $connection_failed = true;
    
    // Check if this is an API request
    $is_api = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
    
    if ($is_api) {
        header('Content-Type: application/json');
        http_response_code(503);
        die(json_encode(["success" => false, "message" => "Database service unavailable"]));
    } else {
        // For regular pages, show maintenance message
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        die('
        <!DOCTYPE html>
        <html>
        <head>
            <title>Service Temporarily Unavailable</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 50px; text-align: center; }
                .message { max-width: 500px; margin: 0 auto; }
                h1 { color: #d32f2f; }
                p { color: #666; }
            </style>
        </head>
        <body>
            <div class="message">
                <h1>Service Temporarily Unavailable</h1>
                <p>Database service is temporarily unavailable. Please try again in a few moments.</p>
            </div>
        </body>
        </html>
        ');
    }
}


mysqli_set_charset($conn, "utf8mb4");

// PDO Connection for compatibility with other scripts
if (!$connection_failed) {
    try {
        $pdoOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5
        ];

        if ($ssl_cert && file_exists($ssl_cert)) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $ssl_cert;
            $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            $user,
            $pass,
            $pdoOptions
        );
    } catch (PDOException $e) {
        error_log('PDO connection failed: ' . $e->getMessage());
        $connection_failed = true;
    }
}