<?php
$host = "oralsync-db.mysql.database.azure.com";
$user = "oralsync";
$pass = "Oralsync1";
$db   = "oral";
$port = 3306;

$conn = mysqli_init();
// Point this to the absolute path just to be safe
$ssl_cert = "/home/site/wwwroot/azure-combined-2026.pem";

mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);

// Set a timeout so it doesn't spin forever
mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

if (!mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL)) {
    header('Content-Type: application/json');
    die(json_encode(["success" => false, "message" => "Database connection failed"]));
}

mysqli_set_charset($conn, "utf8mb4");