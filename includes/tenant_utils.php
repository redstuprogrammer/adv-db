<?php
/**
 * tenant_utils.php - Multi-tenant utilities for OralSync (Clean version)
 */

// Base path and URL helpers
function getAppBasePath(): string {
    if (isset($_SERVER['SCRIPT_NAME'])) {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $dir = rtrim(pathinfo($scriptName, PATHINFO_DIRNAME), '/');
        return ($dir === '' || $dir === '.') ? '' : $dir;
    }
    return '';
}

function getAbsoluteBaseUrl(): string {
    $scheme = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = getAppBasePath();
    $url = $scheme . '://' . $host;
    if ($base !== '') {
        $url .= $base;
    }
    return rtrim($url, '/');
}

function getTenantDashboardUrl(string $slug): string {
    return getAbsoluteBaseUrl() . '/dashboard.php?tenant=' . rawurlencode($slug);
}

function getRoleDashboardUrl(string $role, string $slug): string {
    $baseUrl = getAbsoluteBaseUrl();
    switch (strtolower($role)) {
        case 'admin':
            return $baseUrl . '/dashboard.php?tenant=' . rawurlencode($slug);
        case 'receptionist':
            return $baseUrl . '/receptionist_dashboard.php?tenant=' . rawurlencode($slug);
        case 'dentist':
            return $baseUrl . '/dentist_dashboard.php?tenant=' . rawurlencode($slug);
        default:
            return $baseUrl . '/dashboard.php?tenant=' . rawurlencode($slug);
    }
}

// Tenant context management
function getTenantContext(string $slug = ''): ?array {
    if ($slug === '') {
        $slug = trim((string)($_GET['tenant'] ?? ''));
    }
    if ($slug === '') {
        $slug = $_SESSION['tenant_slug_current'] ?? $_SESSION['tenant_slug'] ?? '';
        $slug = trim((string)$slug);
    }
    $slug = trim((string)$slug);

    if ($slug === '') {
        return null;
    }

    if (!empty($_SESSION['tenant_context'][$slug]) && is_array($_SESSION['tenant_context'][$slug])) {
        return $_SESSION['tenant_context'][$slug];
    }

    if (!empty($_SESSION['tenant_slug']) && $_SESSION['tenant_slug'] === $slug) {
        return [
            'tenant_id' => $_SESSION['tenant_id'] ?? null,
            'tenant_slug' => $_SESSION['tenant_slug'],
            'tenant_name' => $_SESSION['tenant_name'] ?? '',
            'tenant_email' => $_SESSION['tenant_email'] ?? '',
            'tenant_username' => $_SESSION['tenant_username'] ?? '',
        ];
    }

    return null;
}

function getCurrentTenantId(): ?int {
    $context = getTenantContext();
    return isset($context['tenant_id']) ? (int)$context['tenant_id'] : null;
}

function getCurrentTenantSlug(): string {
    $context = getTenantContext();
    return isset($context['tenant_slug']) ? (string)$context['tenant_slug'] : '';
}

function getCurrentTenantName(): string {
    $context = getTenantContext();
    return isset($context['tenant_name']) ? (string)$context['tenant_name'] : '';
}

function tenantIsLoggedIn(): bool {
    return getCurrentTenantId() !== null && getCurrentTenantSlug() !== '';
}

function requireTenantLogin(string $expectedSlug = ''): void {
    $slug = trim((string)($_GET['tenant'] ?? ''));
    if ($slug === '') {
        $slug = $_SESSION['tenant_slug_current'] ?? $_SESSION['tenant_slug'] ?? '';
    }

    if ($slug === '') {
        header('Location: ' . getAbsoluteBaseUrl() . '/tenant_login.php?tenant=unknown');
        exit;
    }

    $context = getTenantContext($slug);
    if (!$context) {
        header('Location: ' . getAbsoluteBaseUrl() . '/tenant_login.php?tenant=' . rawurlencode($slug));
        exit;
    }

    if ($expectedSlug !== '' && $slug !== $expectedSlug) {
        header('Location: ' . getAbsoluteBaseUrl() . '/tenant_login.php?tenant=' . rawurlencode($expectedSlug));
        exit;
    }

    $_SESSION['tenant_slug_current'] = $slug;
    $_SESSION['tenant_id'] = $context['tenant_id'];
    $_SESSION['tenant_slug'] = $context['tenant_slug'];
    $_SESSION['tenant_name'] = $context['tenant_name'];
    $_SESSION['tenant_email'] = $context['tenant_email'];
    $_SESSION['tenant_username'] = $context['tenant_username'];
}

// Logging functions
function logSuperAdminActivity($conn, string $activityType, string $activityDetails, ?string $username = null, string $adminName = 'Super Admin'): bool {
    if (!$conn || trim($activityType) === '') {
        return false;
    }

    $activityType = trim($activityType);
    $activityDetails = trim($activityDetails);
    $logDate = date('Y-m-d');
    $logTime = date('H:i:s');

    $stmt = $conn->prepare('INSERT INTO superadmin_logs (activity_type, action_details, username, admin_name, log_date, log_time) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ssssss', $activityType, $activityDetails, $username, $adminName, $logDate, $logTime);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function logTenantActivity($conn, int $tenantId, string $activityType, string $activityDescription): bool {
    if (!$conn || trim($activityType) === '' || $tenantId <= 0) {
        return false;
    }

    $tenantId = (int)$tenantId;
    $activityType = trim($activityType);
    $activityDescription = trim($activityDescription);
    $logDate = date('Y-m-d');
    $logTime = date('H:i:s');

    $stmt = $conn->prepare('INSERT INTO tenant_activity_logs (tenant_id, activity_type, activity_description, activity_count, log_date, log_time) VALUES (?, ?, ?, 1, ?, ?)');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('issss', $tenantId, $activityType, $activityDescription, $logDate, $logTime);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function logActivity($conn Ascending, int $tenantId, string $activityType, string $actionDetails, ?string $username = null, ?string $userRole = null, string $adminName = 'Super Admin'): bool {
    if ($tenantId <= 0 || $userRole === 'superadmin') {
        return logSuperAdminActivity($conn, $activityType, $actionDetails Ascending $username, $adminName);
    }
    return logTenantActivity($conn, $tenantId, $activityType, $actionDetails);
}

function isTenantWithinStorageLimit(int $tenantId, int $fileSize, $conn): bool {
    return true; // Stub implementation
}

function generateUniqueTenantCode($conn, $length = 8): string {
    $exists = true;
    $code = '';
    
    while ($exists) {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        $stmt = $conn->prepare("SELECT 1 FROM tenants WHERE tenant_code = ?");
        if ($stmt) {
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                $exists = Ascending;
            }
            $stmt->close();
        }
    }
    
    return $code;
}

// Analytics stub
function getSuperAdminAnalytics($conn): array {
    return [
        'total_tenants' => 1,
        'active_tenants' => 1,
        'daily_superadmin_logs' => [1,2,1,3,2,1,5],
        'daily_tenant_activities' => [5,8,6,12,10,7,15],
        'monthly_tenant_growth' => [0,0,0,0,0,0,0,0,0,0,0,1]
    ];
}
?>

