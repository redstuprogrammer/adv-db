<?php
/**
 * Database Connection Test Script
 * Used to verify Azure MySQL Database connectivity
 * Deploy to web server and access: https://oralsync3-g6hpg2fhdyfuagdy.eastasia-01.azurewebsites.net/adv%20db/test_connection.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$startTime = microtime(true);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h3 { color: #555; margin-top: 20px; }
        hr { margin: 15px 0; border: none; border-top: 1px solid #ddd; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .section { margin: 15px 0; padding: 10px; background: #f9f9f9; border-left: 4px solid #007bff; }
    </style>
</head>
<body>
<div class="container">
    <h2>🔍 Database Connection Test - Azure MySQL</h2>
    <hr>

<?php

// Test 1: MySQLi Connection
echo "<h3>1. Testing MySQLi Connection</h3>";
echo "<div class='section'>";
try {
    require_once __DIR__ . '/includes/connect.php';
    
    $result = mysqli_query($conn, "SELECT 1 as connection_test, NOW() as server_time");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "<span class='success'>✅ MySQLi Connection: SUCCESS</span><br>";
        echo "Server Time: " . $row['server_time'] . "<br>";
        echo "Test Query: " . json_encode($row) . "<br>";
    } else {
        echo "<span class='error'>❌ MySQLi Connection: FAILED</span><br>";
        echo "Error: " . mysqli_error($conn) . "<br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ MySQLi Connection: FAILED</span><br>";
    echo "Error: " . $e->getMessage() . "<br>";
}
echo "</div>";

// Test 2: PDO Connection
echo "<h3>2. Testing PDO Connection</h3>";
echo "<div class='section'>";
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT 1 as connection_test, NOW() as server_time");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<span class='success'>✅ PDO Connection: SUCCESS</span><br>";
        echo "Server Time: " . $result['server_time'] . "<br>";
        echo "Test Query: " . json_encode($result) . "<br>";
    } else {
        echo "<span class='error'>❌ PDO Connection: NOT INITIALIZED</span><br>";
    }
} catch (PDOException $e) {
    echo "<span class='error'>❌ PDO Connection: FAILED</span><br>";
    echo "Error: " . $e->getMessage() . "<br>";
}
echo "</div>";

// Test 3: Database Selection
echo "<h3>3. Testing Database Selection</h3>";
echo "<div class='section'>";
try {
    $result = mysqli_query($conn, "SELECT DATABASE() as current_db, USER() as current_user");
    $row = mysqli_fetch_assoc($result);
    echo "<span class='success'>✅ Current Database:</span> " . $row['current_db'] . "<br>";
    echo "<span class='success'>✅ Connected User:</span> " . $row['current_user'] . "<br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Error:</span> " . $e->getMessage() . "<br>";
}
echo "</div>";

// Test 4: Table Access
echo "<h3>4. Testing Table Access</h3>";
echo "<div class='section'>";
try {
    $result = mysqli_query($conn, "SHOW TABLES");
    $tableCount = mysqli_num_rows($result);
    if ($tableCount > 0) {
        echo "<span class='success'>✅ Tables Found:</span> $tableCount tables<br>";
        echo "<pre>";
        while ($row = mysqli_fetch_row($result)) {
            echo "- " . $row[0] . "\n";
        }
        echo "</pre>";
    } else {
        echo "<span class='warning'>⚠️ No tables found in database</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error:</span> " . $e->getMessage() . "<br>";
}
echo "</div>";

// Test 5: Connection Details
echo "<h3>5. Connection Configuration</h3>";
echo "<div class='section'>";
echo "<pre>";
echo "Hostname: oralsync-server2.mysql.database.azure.com\n";
echo "Username: oralsyncSA\n";
echo "Database: oral\n";
echo "Port: 3306\n";
echo "SSL: ENABLED (required for Azure)\n";
echo "PHP Version: " . phpversion() . "\n";
echo "MySQLi Extension: " . (extension_loaded('mysqli') ? 'Loaded ✓' : 'NOT LOADED ✗') . "\n";
echo "PDO MySQL Extension: " . (extension_loaded('pdo_mysql') ? 'Loaded ✓' : 'NOT LOADED ✗') . "\n";
echo "</pre>";
echo "</div>";

$executionTime = microtime(true) - $startTime;
echo "<hr>";
echo "<p><small>Execution time: " . round($executionTime * 1000, 2) . "ms</small></p>";

?>
</div>
</body>
</html>