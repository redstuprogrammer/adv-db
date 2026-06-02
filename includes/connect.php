<?php
date_default_timezone_set('Asia/Manila');

// ── Environment detection ────────────────────────────────────────────────────
$is_local = (
    gethostname() === 'DESKTOP-' . substr(gethostname(), 8) ||
    strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false
);

if ($is_local) {
    $host     = "localhost";
    $user     = "root";
    $pass     = "";
    $db       = "oral";
    $port     = 3306;
    $ssl_cert = null;
} else {
    // Read from Azure Environment Variables if the user set them, otherwise fallback to hardcoded
    $host     = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: "oralsync-db.mysql.database.azure.com");
    $user     = getenv('DB_USER') ?: (getenv('MYSQL_USER') ?: "oralsync");
    $pass     = getenv('DB_PASS') ?: (getenv('MYSQL_PASSWORD') ?: "Oralsync1");
    $db       = getenv('DB_NAME') ?: (getenv('MYSQL_DATABASE') ?: "oral");
    $port     = getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: 3306);
    $ssl_cert = dirname(__DIR__) . '/azure-combined-2026.pem';
}

// ── API detection (use REQUEST_URI — reliable on Azure) ──────────────────────
$request_uri = $_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? '';
$is_api      = strpos($request_uri, '/api/') !== false;

// ── Helper: die with JSON error ───────────────────────────────────────────────
function die_json($code, $message) {
    // Clear any accidental output so the body is clean JSON
    if (ob_get_level()) ob_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => $message]));
}

// ── mysqli init ───────────────────────────────────────────────────────────────
// Set PHP socket timeout to prevent 230s hangs on Azure if DB firewall blocks connection
ini_set('default_socket_timeout', 5);
ini_set('mysqlnd.net_read_timeout', 5);

$conn = mysqli_init();

if (!$conn) {
    if ($is_api) {
        die_json(500, 'mysqli_init() failed — server misconfiguration');
    }
    http_response_code(500);
    die('Server error: could not initialise database driver.');
}

mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

// ── SSL setup (must call mysqli_ssl_set BEFORE real_connect) ─────────────────
$use_ssl = $ssl_cert && file_exists($ssl_cert);

if ($use_ssl) {
    // ca = cert file, everything else null (no client cert needed for Azure)
    mysqli_ssl_set($conn, null, null, $ssl_cert, null, null);
    mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
}

$flags = $use_ssl ? MYSQLI_CLIENT_SSL : 0;

// ── Connect ───────────────────────────────────────────────────────────────────
if (!mysqli_real_connect($conn, $host, $user, $pass, $db, $port, null, $flags)) {
    $err = mysqli_connect_error();
    error_log('MySQLi connection failed: ' . $err);

    if ($is_api) {
        die_json(503, 'Database service unavailable: ' . $err);
    }

    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    die('
    <!DOCTYPE html><html><head><title>Service Unavailable</title></head>
    <body style="font-family:Arial;text-align:center;margin:50px">
        <h1 style="color:#d32f2f">Service Temporarily Unavailable</h1>
        <p>Database service is temporarily unavailable. Please try again shortly.</p>
        <div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; display: inline-block; text-align: left; max-width: 600px;">
            <p style="margin: 0; color: #555; font-size: 14px;"><strong>Debug Info (Admin Only):</strong><br>' . htmlspecialchars($err) . '</p>
        </div>
    </body></html>
    ');
}

mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET time_zone = '+08:00'");


// ── PDO (for scripts that need it) ────────────────────────────────────────────
try {
    $pdoOptions = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    if ($use_ssl) {
        $pdoOptions[PDO::MYSQL_ATTR_SSL_CA]                  = $ssl_cert;
        $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]  = false;
    }

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        $pdoOptions
    );
} catch (PDOException $e) {
    error_log('PDO connection failed: ' . $e->getMessage());
    // PDO failure is non-fatal — scripts using only $conn still work
    $pdo = null;
}