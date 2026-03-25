<?php

// tenant_utils.php
// Shared tenant session/login utilities for multi-tenant isolation.

function getAppBasePath(): string {
    // Improved base path detection for Azure/XAMPP/nginx
    if (isset($_SERVER['SCRIPT_NAME'])) {
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $scriptName = str_replace('\\', '/', $scriptName);
        $dir = rtrim(pathinfo($scriptName, PATHINFO_DIRNAME), '/');
        if ($dir === '' || $dir === '.') {
            return '';
        }
        return $dir;
    }
    // Fallback for CLI/edge cases
    return '';
}

function getTenantDashboardUrl(string $slug): string {
    $base = getAppBasePath();
    $path = ($base !== '' ? $base . '/' : '') . 'tenant_dashboard.php?tenant=' . rawurlencode($slug);
    error_log("Tenant Dashboard URL generated: " . $path . " (base: '" . $base . "')");
    return $path;
}

function getCurrentTenantId(): ?int {
    return isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
}

function getCurrentTenantSlug(): string {
    return isset($_SESSION['tenant_slug']) ? (string)$_SESSION['tenant_slug'] : '';
}

function getCurrentTenantName(): string {
    return isset($_SESSION['tenant_name']) ? (string)$_SESSION['tenant_name'] : '';
}

function tenantIsLoggedIn(): bool {
    return getCurrentTenantId() !== null && getCurrentTenantSlug() !== '';
}

function requireTenantLogin(string $expectedSlug = ''): void {
    $slug = trim((string)($_GET['tenant'] ?? ''));
    $sessionSlug = getCurrentTenantSlug();

    if (!tenantIsLoggedIn() || $slug === '' || $sessionSlug === '' || ($expectedSlug !== '' && $slug !== $expectedSlug) || ($expectedSlug === '' && $slug !== $sessionSlug)) {
        $base = getAppBasePath();
        $redirectSlug = $slug ?: $sessionSlug ?: 'unknown';
        $redirect = ($base !== '' ? $base . '/' : '') . 'tenant_login.php?tenant=' . rawurlencode($redirectSlug);
        error_log("Tenant login required, redirecting to: " . $redirect);
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Log superadmin activity to superadmin_logs table
 */
function logSuperAdminActivity($conn, string $activityType, string $actionDetails, ?string $username = null, string $adminName = 'Super Admin'): bool {
    if (!$conn || trim($activityType) === '') {
        return false;
    }

    $activityType = trim($activityType);
    $actionDetails = trim($actionDetails);
    $logDate = date('Y-m-d');
    $logTime = date('H:i:s');

    $stmt = $conn->prepare('INSERT INTO superadmin_logs (activity_type, action_details, username, admin_name, log_date, log_time) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ssssss', $activityType, $actionDetails, $username, $adminName, $logDate, $logTime);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Log tenant activity to tenant_activity_logs table (privacy-safe, no personal details)
 */
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

    $stmt->bind_param('isss', $tenantId, $activityType, $activityDescription, $logDate, $logTime);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Legacy function route for backwards compatibility - redirects to appropriate table
 */
function logActivity($conn, int $tenantId, string $activityType, string $actionDetails, ?string $username = null, ?string $userRole = null, string $adminName = 'Super Admin'): bool {
    // Superadmin actions go to superadmin_logs
    if ($tenantId <= 0 || $userRole === 'superadmin') {
        return logSuperAdminActivity($conn, $activityType, $actionDetails, $username, $adminName);
    }
    // Tenant actions go to tenant_activity_logs
    return logTenantActivity($conn, $tenantId, $activityType, $actionDetails);
}

function getSuperAdminAnalytics($conn): array {
    $metrics = [
        'total_tenants' => 0,
        'active_tenants' => 0,
        'inactive_tenants' => 0,
        'last_7_days_superadmin_logs' => 0,
        'last_7_days_tenant_activities' => 0,
        'today_superadmin_logs' => 0,
        'today_tenant_activities' => 0,
        'daily_superadmin_logs' => [],
        'daily_tenant_activities' => [],
        'monthly_tenant_growth' => [],
    ];

    if (!$conn) {
        return $metrics;
    }

    $queries = [
        'total_tenants' => 'SELECT COUNT(*) AS c FROM tenants',
        'active_tenants' => 'SELECT COUNT(*) AS c FROM tenants WHERE status = "active"',
        'inactive_tenants' => 'SELECT COUNT(*) AS c FROM tenants WHERE status != "active"',
        'last_7_days_superadmin_logs' => 'SELECT COUNT(*) AS c FROM superadmin_logs WHERE log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
        'last_7_days_tenant_activities' => 'SELECT COUNT(*) AS c FROM tenant_activity_logs WHERE log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
        'today_superadmin_logs' => 'SELECT COUNT(*) AS c FROM superadmin_logs WHERE log_date = CURDATE()',
        'today_tenant_activities' => 'SELECT COUNT(*) AS c FROM tenant_activity_logs WHERE log_date = CURDATE()',
    ];

    foreach ($queries as $k => $q) {
        $stmt = $conn->prepare($q);
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $metrics[$k] = (int)($row['c'] ?? 0);
            $stmt->close();
        }
    }

    // Daily logs for last 7 days
    $daily_sa = [];
    $daily_tenant = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM superadmin_logs WHERE log_date = ?');
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $daily_sa[] = (int)($row['c'] ?? 0);
        $stmt->close();

        $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM tenant_activity_logs WHERE log_date = ?');
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $daily_tenant[] = (int)($row['c'] ?? 0);
        $stmt->close();
    }
    $metrics['daily_superadmin_logs'] = $daily_sa;
    $metrics['daily_tenant_activities'] = $daily_tenant;

    // Monthly tenant growth for last 12 months
    $monthly_tenants = [];
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-{$i} months"));
        $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM tenants WHERE DATE_FORMAT(created_at, "%Y-%m") = ?');
        $stmt->bind_param('s', $month);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $monthly_tenants[] = (int)($row['c'] ?? 0);
        $stmt->close();
    }
    $metrics['monthly_tenant_growth'] = $monthly_tenants;

    return $metrics;
}

function tenantWhereClause(): string {
    // Simple helper for your queries later
    return 'tenant_id = ?';
}

function getTenantQueryBindings(): array {
    // Useful for building prepared statements in the main app
    return [getCurrentTenantId()];
}

/**
 * Metric Helpers for Dashboard
 * Returns NULL if database error or no tenant context
 */

function getTenantPatientCount(?int $tenantId): ?int {
    if (!$tenantId) return null;
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM patient WHERE tenant_id = ?');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] ?? 0;
}

function getTenantUpcomingAppointmentCount(?int $tenantId): ?int {
    if (!$tenantId) return null;
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM appointment WHERE tenant_id = ? AND appointment_date >= DATE(NOW())');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] ?? 0;
}

function getTenantOutstandingInvoiceCount(?int $tenantId): ?int {
    if (!$tenantId) return null;
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM payment WHERE tenant_id = ? AND status != "paid"');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] ?? 0;
}

function getTenantTodayRevenue(?int $tenantId): ?float {
    if (!$tenantId) return null;
    global $conn;
    $stmt = $conn->prepare('SELECT COALESCE(SUM(amount), 0) as total FROM payment WHERE tenant_id = ? AND DATE(payment_date) = DATE(NOW()) AND status = "paid"');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return (float)($row['total'] ?? 0);
}

