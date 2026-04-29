<?php
require_once __DIR__ . '/includes/connect.php';

echo "Checking if homepage_url column exists...\n";
$result = $conn->query("SHOW COLUMNS FROM tenants LIKE 'homepage_url'");
if ($result->num_rows === 0) {
    echo "Adding homepage_url column...\n";
    if ($conn->query("ALTER TABLE tenants ADD COLUMN homepage_url VARCHAR(255) DEFAULT NULL AFTER subdomain_slug")) {
        echo "Successfully added homepage_url column.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "homepage_url column already exists.\n";
}

echo "Updating existing tenants with default homepage URLs...\n";
$tenants = $conn->query("SELECT tenant_id, subdomain_slug FROM tenants");
while ($row = $tenants->fetch_assoc()) {
    $id = $row['tenant_id'];
    $slug = $row['subdomain_slug'];
    // Construct the URL. Assuming the app is at the root or we use a relative path for now.
    // However, superadmin_dash seems to expect a full URL or at least something it can display.
    $homepage_url = "Landing Page/tenant_homepage.php?tenant=" . $slug;
    
    $stmt = $conn->prepare("UPDATE tenants SET homepage_url = ? WHERE tenant_id = ?");
    $stmt->bind_param("si", $homepage_url, $id);
    $stmt->execute();
    echo "Updated tenant $id with URL: $homepage_url\n";
}
echo "Done.\n";
