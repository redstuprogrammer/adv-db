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
if (!mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, $flags)) {
    error_log('MySQLi connection failed: ' . mysqli_connect_error());
    header('Content-Type: application/json');
    die(json_encode(["success" => false, "message" => "Database connection failed"]));
}

mysqli_set_charset($conn, "utf8mb4");

// PDO Connection for compatibility with other scripts
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
    header('Content-Type: application/json');
    die(json_encode(["success" => false, "message" => "PDO connection failed: " . $e->getMessage()]));
}