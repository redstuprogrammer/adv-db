<?php
// Azure MySQL Database Connection Settings
$host = "oralsync-server2.mysql.database.azure.com";
$user = "oralsyncSA";
$pass = "Oralsync1";
$db   = "oral";
$port = 3306;

// ===== MySQLi Connection (with SSL for Azure) =====
$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
// SSL is enforced by Azure, set to true for verification
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);

if (!mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL)) {
    header('Content-Type: application/json');
    die(json_encode(["success" => false, "message" => "MySQLi connection failed: " . mysqli_connect_error()]));
}

mysqli_set_charset($conn, "utf8mb4");

// ===== PDO Connection (with SSL for Azure) =====
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4;sslmode=REQUIRE";
    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt'
        ]
    );
} catch (PDOException $e) {
    header('Content-Type: application/json');
    die(json_encode(["success" => false, "message" => "PDO connection failed: " . $e->getMessage()]));
}