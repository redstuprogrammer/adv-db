<?php
// test_db.php
// Pure DB connection test - NO SESSIONS, NO INCLUDES.

$host     = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: "oralsync-db.mysql.database.azure.com");
$user     = getenv('DB_USER') ?: (getenv('MYSQL_USER') ?: "oralsync");
$pass     = getenv('DB_PASS') ?: (getenv('MYSQL_PASSWORD') ?: "Oralsync1");
$db       = getenv('DB_NAME') ?: (getenv('MYSQL_DATABASE') ?: "oral");
$port     = getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: 3306);
$ssl_cert = __DIR__ . '/azure-combined-2026.pem';

echo "Attempting to connect to: $host as $user...<br>";

// Force 5 second timeout
ini_set('default_socket_timeout', 5);
ini_set('mysqlnd.net_read_timeout', 5);

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

$use_ssl = $ssl_cert && file_exists($ssl_cert);
if ($use_ssl) {
    echo "SSL Certificate found! Configuring SSL...<br>";
    mysqli_ssl_set($conn, null, null, $ssl_cert, null, null);
    mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
} else {
    echo "WARNING: SSL Certificate NOT found! Proceeding without SSL.<br>";
}

$flags = $use_ssl ? MYSQLI_CLIENT_SSL : 0;

$start = microtime(true);
$success = mysqli_real_connect($conn, $host, $user, $pass, $db, $port, null, $flags);
$end = microtime(true);
$time = round($end - $start, 2);

if ($success) {
    echo "<h2 style='color:green;'>SUCCESS! Connected in {$time}s</h2>";
} else {
    echo "<h2 style='color:red;'>FAILED! Time taken: {$time}s</h2>";
    echo "<pre>Error: " . mysqli_connect_error() . "</pre>";
}
?>
