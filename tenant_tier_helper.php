<?php
/**
 * Tenant Tier and Feature Management
 * 
 * Provides functions to check tenant tier features and limits
 */

require_once __DIR__ . '/includes/subscription_tiers.php';

/**
 * Get the subscription tier for a specific tenant
 * @param int $tenantId The tenant ID
 * @param mysqli $conn Database connection
 * @return string|null The tier key (e.g., 'startup', 'professional') or null
 */
function getTenantTier(int $tenantId, $conn): ?string {
    if (!$conn || $tenantId <= 0) {
        return null;
    }
    
    $stmt = $conn->prepare('SELECT subscription_tier FROM tenants WHERE tenant_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ? $row['subscription_tier'] : null;
}

/**
 * Check if a tenant has access to a specific feature
 * @param int $tenantId The tenant ID
 * @param string $feature The feature name
 * @param mysqli $conn Database connection
 * @return bool True if the tenant's tier includes this feature
 */
function tenantHasTierFeature(int $tenantId, string $feature, $conn): bool {
    $tier = getTenantTier($tenantId, $conn);
    if (!$tier) {
        return false;
    }
    return tierHasFeature($tier, $feature);
}

/**
 * Get the limit value for a tenant's tier
 * @param int $tenantId The tenant ID
 * @param string $limitKey The limit key (e.g., 'max_patients', 'max_dentists')
 * @param mysqli $conn Database connection
 * @return int|null The limit value or null if not found
 */
function getTenantTierLimit(int $tenantId, string $limitKey, $conn): ?int {
    $tier = getTenantTier($tenantId, $conn);
    if (!$tier) {
        return null;
    }
    return getTierLimit($tier, $limitKey);
}

/**
 * Get the tier information for a tenant (full tier definition)
 * @param int $tenantId The tenant ID
 * @param mysqli $conn Database connection
 * @return array|null The full tier definition or null if not found
 */
function getTenantTierInfo(int $tenantId, $conn): ?array {
    $tier = getTenantTier($tenantId, $conn);
    if (!$tier) {
        return null;
    }
    return getTierByKey($tier);
}

/**
 * Get tenant registration date
 * @param int $tenantId The tenant ID
 * @param mysqli $conn Database connection
 * @return string|null Created at timestamp or null
 */
function getTenantCreatedDate(int $tenantId, $conn): ?string {
    if (!$conn || $tenantId <= 0) {
        return null;
    }
    
    $stmt = $conn->prepare('SELECT created_at FROM tenants WHERE tenant_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ? $row['created_at'] : null;
}

/**
 * Check if tenant is within their allocated limits
 * @param int $tenantId The tenant ID
 * @param string $resource The resource name (e.g., 'patients', 'dentists')
 * @param int $currentCount The current count of the resource
 * @param mysqli $conn Database connection
 * @return bool True if within limits
 */
function isTenantWithinLimits(int $tenantId, string $resource, int $currentCount, $conn): bool {
    $limitKey = 'max_' . $resource;
    $limit = getTenantTierLimit($tenantId, $limitKey, $conn);
    
    if ($limit === null) {
        return true; // No limit defined
    }
    
    return $currentCount < $limit;
}

/**
 * Check if trial has expired for a tenant
 * @param int $tenantId The tenant ID
 * @param mysqli $conn Database connection
 * @return array ['is_trial' => bool, 'expired' => bool, 'days_remaining' => int|null]
 */
function checkTrialStatus(int $tenantId, $conn): array {
    $tier = getTenantTier($tenantId, $conn);
    
    if ($tier !== 'trial') {
        return [
            'is_trial' => false,
            'expired' => false,
            'days_remaining' => null
        ];
    }
    
    // Try to get explicit trial_end_date first (requires migration_add_trial_tracking.sql)
    $stmt = $conn->prepare('SELECT trial_end_date, created_at FROM tenants WHERE tenant_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row && $row['trial_end_date']) {
            // Use explicit trial_end_date if available
            $trialEndTime = strtotime($row['trial_end_date']);
        } elseif ($row && $row['created_at']) {
            // Fall back to 14-day calculation from creation date
            $createdTime = strtotime($row['created_at']);
            $trialEndTime = strtotime('+14 days', $createdTime);
        } else {
            return [
                'is_trial' => false,
                'expired' => false,
                'days_remaining' => null
            ];
        }
    } else {
        return [
            'is_trial' => false,
            'expired' => false,
            'days_remaining' => null
        ];
    }
    
    $nowTime = time();
    $daysRemaining = floor(($trialEndTime - $nowTime) / 86400);
    $expired = $daysRemaining < 0;
    
    return [
        'is_trial' => true,
        'expired' => $expired,
        'days_remaining' => max(0, $daysRemaining)
    ];
}

/**
 * Check if tenant is on trial
 * @param int $tenantId The tenant ID
 * @param mysqli $conn Database connection
 * @return bool True if tenant is on trial tier
 */
function isTenantOnTrial(int $tenantId, $conn): bool {
    $tier = getTenantTier($tenantId, $conn);
    return $tier === 'trial';
}

/**
 * Get trial expiration date for a tenant
 * @param int $tenantId The tenant ID
 * @param mysqli $conn Database connection
 * @return string|null ISO format date or null
 */
function getTrialExpirationDate(int $tenantId, $conn): ?string {
    $tier = getTenantTier($tenantId, $conn);
    $created = getTenantCreatedDate($tenantId, $conn);
    
    if ($tier !== 'trial' || !$created) {
        return null;
    }
    
    $expirationTime = strtotime('+14 days', strtotime($created));
    return date('Y-m-d H:i:s', $expirationTime);
}

/**
 * Get current storage usage for a tenant in bytes
 * @param int $tenantId The tenant ID
 * @param mysqli $conn Database connection
 * @return int Total bytes used
 */
function getTenantStorageUsage(int $tenantId, $conn): int {
    if (!$conn || $tenantId <= 0) {
        return 0;
    }
    
    // Sum from tenant_documents
    $total = 0;
    $stmt = $conn->prepare('SELECT SUM(file_size) as total_size FROM tenant_documents WHERE tenant_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total += (int)($row['total_size'] ?? 0);
        $stmt->close();
    }
    
    // Sum from patient_documents
    $stmt2 = $conn->prepare('SELECT SUM(file_size) as total_size FROM patient_documents WHERE tenant_id = ?');
    if ($stmt2) {
        $stmt2->bind_param('i', $tenantId);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $row2 = $result2->fetch_assoc();
        $total += (int)($row2['total_size'] ?? 0);
        $stmt2->close();
    }
    
    return $total;
}

/**
 * Check if a tenant has enough storage space for a new file
 * @param int $tenantId The tenant ID
 * @param int $newFileSizeBytes Size of the file to be uploaded in bytes
 * @param mysqli $conn Database connection
 * @return bool True if within limits
 */
function isTenantWithinStorageLimit(int $tenantId, int $newFileSizeBytes, $conn): bool {
    $limitGb = getTenantTierLimit($tenantId, 'max_storage_gb', $conn);
    
    if ($limitGb === null) {
        return true; // No limit defined
    }
    
    $limitBytes = (float)$limitGb * 1024 * 1024 * 1024;
    $currentUsage = getTenantStorageUsage($tenantId, $conn);
    
    return ($currentUsage + $newFileSizeBytes) <= $limitBytes;
}
/**
 * Check if a tenant has mobile access and exit if not
 * Used for API gating for mobile-specific requests
 * @param int $tenantId The tenant ID
 * @param mysqli $conn Database connection
 */
function requireMobileAccess(int $tenantId, $conn): void {
    // Mobile Dashboard is now available for all subscription plans
    // No restriction check needed
    return;
}
?>
