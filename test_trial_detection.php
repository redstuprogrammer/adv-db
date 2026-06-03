<?php
/**
 * Test script to verify trial tier detection
 */

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_tier_helper.php';

// Get the first trial account from the database
$stmt = $conn->prepare('SELECT tenant_id, company_name, subscription_tier, created_at FROM tenants WHERE subscription_tier = "trial" LIMIT 1');
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $trial_account = $result->fetch_assoc();
    $stmt->close();
    
    if ($trial_account) {
        $tenantId = $trial_account['tenant_id'];
        echo "<h2>Testing Trial Account Detection</h2>";
        echo "<p><strong>Tenant:</strong> {$trial_account['company_name']}</p>";
        echo "<p><strong>DB subscription_tier:</strong> {$trial_account['subscription_tier']}</p>";
        echo "<p><strong>created_at:</strong> {$trial_account['created_at']}</p>";
        
        // Calculate what the effective tier should be
        $createdTime = strtotime($trial_account['created_at']);
        $trialEndTime = strtotime('+14 days', $createdTime);
        $nowTime = time();
        $daysRemaining = floor(($trialEndTime - $nowTime) / 86400);
        
        echo "<p><strong>Trial end time:</strong> " . date('Y-m-d H:i:s', $trialEndTime) . "</p>";
        echo "<p><strong>Now:</strong> " . date('Y-m-d H:i:s', $nowTime) . "</p>";
        echo "<p><strong>Days remaining in trial:</strong> $daysRemaining days</p>";
        
        // Get the effective tier
        $effectiveTier = getTenantEffectiveTier($tenantId, $conn);
        echo "<p><strong>Effective tier detected:</strong> $effectiveTier</p>";
        
        // Get the effective max file size
        $maxFileSize = getTenantEffectiveMaxFileSize($tenantId, $conn);
        echo "<p><strong>Max file size (MB):</strong> $maxFileSize</p>";
        
        if ($effectiveTier === 'trial' && $maxFileSize === 2) {
            echo "<p style='color: green;'><strong>✅ CORRECT: Trial account detected with 2MB limit</strong></p>";
        } else {
            echo "<p style='color: red;'><strong>❌ ERROR: Trial account NOT detected properly!</strong></p>";
            echo "<p style='color: red;'>Expected: trial tier with 2MB, Got: $effectiveTier with {$maxFileSize}MB</p>";
        }
    } else {
        echo "<p>No trial accounts found</p>";
    }
} else {
    echo "Error preparing statement: " . $conn->error;
}

// Also test with subscription_tier = NULL
echo "<hr />";
echo "<h2>Testing Account with subscription_tier = NULL</h2>";

$stmt2 = $conn->prepare('SELECT tenant_id, company_name, subscription_tier, created_at FROM tenants WHERE subscription_tier IS NULL LIMIT 1');
if ($stmt2) {
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $null_account = $result2->fetch_assoc();
    $stmt2->close();
    
    if ($null_account) {
        $tenantId = $null_account['tenant_id'];
        echo "<p><strong>Tenant:</strong> {$null_account['company_name']}</p>";
        echo "<p><strong>DB subscription_tier:</strong> " . ($null_account['subscription_tier'] ? $null_account['subscription_tier'] : 'NULL') . "</p>";
        echo "<p><strong>created_at:</strong> {$null_account['created_at']}</p>";
        
        $createdTime = strtotime($null_account['created_at']);
        $trialEndTime = strtotime('+14 days', $createdTime);
        $nowTime = time();
        $daysRemaining = floor(($trialEndTime - $nowTime) / 86400);
        
        echo "<p><strong>Trial end time:</strong> " . date('Y-m-d H:i:s', $trialEndTime) . "</p>";
        echo "<p><strong>Days remaining:</strong> $daysRemaining days</p>";
        
        $effectiveTier = getTenantEffectiveTier($tenantId, $conn);
        $maxFileSize = getTenantEffectiveMaxFileSize($tenantId, $conn);
        echo "<p><strong>Effective tier detected:</strong> $effectiveTier</p>";
        echo "<p><strong>Max file size (MB):</strong> $maxFileSize</p>";
        
        if ($daysRemaining >= 0) {
            if ($effectiveTier === 'trial' && $maxFileSize === 2) {
                echo "<p style='color: green;'><strong>✅ CORRECT: Account within trial period detected as trial with 2MB limit</strong></p>";
            } else {
                echo "<p style='color: red;'><strong>❌ ERROR: Account should be trial but isn't!</strong></p>";
            }
        } else {
            echo "<p style='color: blue;'>Account is beyond trial period, should default to startup tier</p>";
            if ($effectiveTier === 'startup' && $maxFileSize === 5) {
                echo "<p style='color: green;'><strong>✅ CORRECT: Defaulted to startup (5MB)</strong></p>";
            }
        }
    } else {
        echo "<p>No accounts with NULL subscription_tier found</p>";
    }
} else {
    echo "Error preparing statement: " . $conn->error;
}
?>
