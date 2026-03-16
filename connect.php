<?php
$host = "oralsync-db.mysql.database.azure.com";
$user = "oralsync";
$pass = "Oralsync1";
$db   = "oral";
$port = 3306;

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);

if (!mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL)) {
    // If this is an API call, return JSON instead of a string to avoid the 'Unexpected token' error
    header('Content-Type: application/json');
    die(json_encode(["error" => "Connection failed: " . mysqli_connect_error()]));
}

mysqli_set_charset($conn, "utf8mb4");