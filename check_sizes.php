<?php
$host     = "oralsync-db.mysql.database.azure.com";
$user     = "oralsync";
$pass     = "Oralsync1";
$db       = "oral";
$port     = 3306;
$ssl_cert = dirname(__DIR__) . '/azure-combined-2026.pem';

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
if ($ssl_cert && file_exists($ssl_cert)) {
    mysqli_ssl_set($conn, null, null, $ssl_cert, null, null);
    mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    mysqli_real_connect($conn, $host, $user, $pass, $db, $port, null, MYSQLI_CLIENT_SSL);
} else {
    mysqli_real_connect($conn, $host, $user, $pass, $db, $port, null, 0);
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully\n";

$res = $conn->query("SELECT doc_id, file_path, file_size FROM patient_documents WHERE file_size = 0 OR file_size IS NULL LIMIT 10");
if ($res) {
    echo "Patient docs with missing size:\n";
    while($row = $res->fetch_assoc()) {
        echo "Patient Doc ID: " . $row['doc_id'] . " Path: " . $row['file_path'] . " Size: " . $row['file_size'] . "\n";
    }
}

$res = $conn->query("SELECT document_id, file_path, file_size FROM tenant_documents WHERE file_size = 0 OR file_size IS NULL LIMIT 10");
if ($res) {
    echo "Tenant docs with missing size:\n";
    while($row = $res->fetch_assoc()) {
        echo "Tenant Doc ID: " . $row['document_id'] . " Path: " . $row['file_path'] . " Size: " . $row['file_size'] . "\n";
    }
}
