<?php
/**
 * Script to update existing tenants with usernames based on clinic name
 * Run once: php update_existing_usernames.php
 */
require_once __DIR__ . '/connect.php';

// Query all tenants without usernames
$query = "SELECT tenant_id, company_name, owner_name, contact_email FROM tenants WHERE username IS NULL OR username = ''";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

$updated = 0;
$errors = [];

while ($row = mysqli_fetch_assoc($result)) {
    $tenantId = (int)$row['tenant_id'];
    $companyName = $row['company_name'];
    
    // Generate username from company name: lowercase, replace spaces with underscores
    $baseUsername = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $companyName)));
    
    // Ensure it's not too long
    $baseUsername = substr($baseUsername, 0, 30);
    
    if (strlen($baseUsername) === 0) {
        $baseUsername = 'clinic';
    }
    
    // Check if username already exists, if so, append a number
    $username = $baseUsername;
    $counter = 1;
    while (true) {
        $checkStmt = mysqli_prepare($conn, "SELECT tenant_id FROM tenants WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($checkStmt, "s", $username);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_num_rows($checkResult) === 0) {
            break; // Username is unique
        }
        
        // Append counter
        $username = $baseUsername . $counter;
        $counter++;
        
        if ($counter > 1000) {
            $errors[] = "Could not generate unique username for tenant {$tenantId}";
            break;
        }
    }
    
    if ($counter <= 1000) {
        // Update the tenant with the new username
        $updateStmt = mysqli_prepare($conn, "UPDATE tenants SET username = ? WHERE tenant_id = ?");
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, "si", $username, $tenantId);
            if (mysqli_stmt_execute($updateStmt)) {
                $updated++;
                echo "✓ Tenant {$tenantId} ({$companyName}): username set to '{$username}'\n";
            } else {
                $errors[] = "Failed to update tenant {$tenantId}: " . mysqli_error($conn);
            }
        }
    }
}

echo "\n=== Summary ===\n";
echo "Updated: {$updated} tenants\n";
echo "Errors: " . count($errors) . "\n";

if ($errors) {
    echo "\nErrors:\n";
    foreach ($errors as $err) {
        echo "- {$err}\n";
    }
} else {
    echo "\n✓ All tenants have been updated with usernames!\n";
}

mysqli_close($conn);
?>
