<?php
$host = "oralsync-db.mysql.database.azure.com";
$user = "oralsync";
$pass = "Oralsync1";
$db   = "oral";
$port = 3306;

$conn = mysqli_init();

$ssl_cert = __DIR__ . "/../azure-combined-2026.pem";

mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);

if (!mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL)) {
    // If this is an API call, return JSON instead of a string to avoid the 'Unexpected token' error
    header('Content-Type: application/json');
    die(json_encode(["error" => "Connection failed: " . mysqli_connect_error()]));
}

mysqli_set_charset($conn, "utf8mb4");

// Create PDO connection for modern database operations
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_CA => $ssl_cert,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ]);
} catch (PDOException $e) {
    // If this is an API call, return JSON instead of a string
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json');
        die(json_encode(["error" => "PDO connection failed: " . $e->getMessage()]));
    }
    die("Database connection failed: " . $e->getMessage());
}