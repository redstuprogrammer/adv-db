<?php
function try_connect($host, $user, $pass, $db) {
    try {
        $conn = new mysqli($host, $user, $pass, $db);
        if (!$conn->connect_error) return $conn;
    } catch (Exception $e) {}
    return null;
}

$conn = try_connect('localhost', 'root', '', 'oral');
if (!$conn) {
    echo "Local connection failed, trying Azure...\n";
    $conn = try_connect('oralsync-db.mysql.database.azure.com', 'oralsync', 'Oralsync1', 'oral');
}

if (!$conn) {
    die("Could not connect to any database.\n");
}

echo "Connected to " . $conn->host_info . "\n";

echo "Checking if homepage_url column exists...\n";
$result = $conn->query("SHOW COLUMNS FROM tenants LIKE 'homepage_url'");
if ($result && $result->num_rows === 0) {
    echo "Adding homepage_url column...\n";
    if ($conn->query("ALTER TABLE tenants ADD COLUMN homepage_url VARCHAR(255) DEFAULT NULL AFTER subdomain_slug")) {
        echo "Successfully added homepage_url column.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "homepage_url column already exists or table not found.\n";
}

echo "Updating existing tenants...\n";
$tenants = $conn->query("SELECT tenant_id, subdomain_slug FROM tenants");
if ($tenants) {
    while ($row = $tenants->fetch_assoc()) {
        $id = $row['tenant_id'];
        $slug = $row['subdomain_slug'];
        $homepage_url = "Landing Page/tenant_homepage.php?tenant=" . $slug;
        
        $stmt = $conn->prepare("UPDATE tenants SET homepage_url = ? WHERE tenant_id = ?");
        $stmt->bind_param("si", $homepage_url, $id);
        $stmt->execute();
        echo "Updated tenant $id ($slug) -> $homepage_url\n";
    }
}
echo "Done.\n";
