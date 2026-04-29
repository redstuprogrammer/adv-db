<?php
/**
 * Migration Script: Fix Tenants Table and Populate Homepage URLs
 * Run this script by visiting it in your browser (e.g., http://localhost/adv%20db/fix_tenants_table.php)
 */
require_once __DIR__ . '/includes/connect.php';

echo "<h1>Tenant Table Fixer</h1>";

// 1. Add homepage_url column if missing
echo "Checking if 'homepage_url' column exists in 'tenants' table... ";
$result = $conn->query("SHOW COLUMNS FROM tenants LIKE 'homepage_url'");
if ($result->num_rows === 0) {
    echo "<span style='color: blue;'>Missing.</span><br>Adding column... ";
    if ($conn->query("ALTER TABLE tenants ADD COLUMN homepage_url VARCHAR(255) DEFAULT NULL AFTER subdomain_slug")) {
        echo "<span style='color: green;'>Success.</span><br>";
    } else {
        echo "<span style='color: red;'>Error: " . $conn->error . "</span><br>";
    }
} else {
    echo "<span style='color: green;'>Already exists.</span><br>";
}

// 2. Populate URLs for existing tenants
echo "Updating homepage URLs for existing tenants...<br>";
$tenants = $conn->query("SELECT tenant_id, subdomain_slug FROM tenants");
$count = 0;
while ($row = $tenants->fetch_assoc()) {
    $id = $row['tenant_id'];
    $slug = $row['subdomain_slug'];
    $homepage_url = "Landing Page/tenant_homepage.php?tenant=" . $slug;
    
    $stmt = $conn->prepare("UPDATE tenants SET homepage_url = ? WHERE tenant_id = ?");
    $stmt->bind_param("si", $homepage_url, $id);
    if ($stmt->execute()) {
        echo " - Tenant #$id ($slug): <a href='$homepage_url' target='_blank'>$homepage_url</a> <span style='color: green;'>Updated.</span><br>";
        $count++;
    } else {
        echo " - Tenant #$id ($slug): <span style='color: red;'>Failed: " . $conn->error . "</span><br>";
    }
}

echo "<br><b>Total tenants updated: $count</b><br>";
echo "<p>You can now delete this file for security.</p>";
