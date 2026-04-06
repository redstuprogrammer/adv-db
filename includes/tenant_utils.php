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

function getAbsoluteBaseUrl(): string {
    // Detect protocol - check X-Forwarded-Proto first (for Azure/proxy), then HTTPS
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $scheme = (strtolower($forwardedProto) === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';
    
    // Get host
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Get base path
    $base = getAppBasePath();
    
    $url = $scheme . '://' . $host;
    if ($base !== '') {
        $url .= $base;
    }
    
    return rtrim($url, '/');
}

function getTenantDashboardUrl(string $slug): string {
    $baseUrl = getAbsoluteBaseUrl();
    $url = $baseUrl . '/dashboard.php?tenant=' . rawurlencode($slug);
    error_log("Tenant Dashboard URL generated: " . $url);
    return $url;
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
            return $baseUrl . '/dashboard.php?tenant=' . rawurlencode($slug); // fallback
    }
}

function getTenantContext(string $slug = ''): ?array {
    if ($slug === '') {
        $slug = $_GET['tenant'] ?? $_SESSION['tenant_slug_current'] ?? $_SESSION['tenant_slug'] ?? '';
    }
    $slug = trim((string)$slug);

    if ($slug === '') {
        return null;
    }

    if (!empty($_SESSION['tenant_context'][$slug]) && is_array($_SESSION['tenant_context'][$slug])) {
        return $_SESSION['tenant_context'][$slug];
    }

    // Fallback to legacy values
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
        // If no query value, consider current session-cached slug
        $slug = $_SESSION['tenant_slug_current'] ?? $_SESSION['tenant_slug'] ?? '';
    }

    if ($slug === '') {
        $redirect = getAbsoluteBaseUrl() . '/tenant_login.php?tenant=unknown';
        header('Location: ' . $redirect);
        exit;
    }

    $context = getTenantContext($slug);
    if (!$context) {
        $redirect = getAbsoluteBaseUrl() . '/tenant_login.php?tenant=' . rawurlencode($slug);
        header('Location: ' . $redirect);
        exit;
    }

    // If expectedSlug provided, enforce it explicitly
    if ($expectedSlug !== '' && $slug !== $expectedSlug) {
        $redirect = getAbsoluteBaseUrl() . '/tenant_login.php?tenant=' . rawurlencode($expectedSlug);
        header('Location: ' . $redirect);
        exit;
    }

    // Make this tenant the current working context
    $_SESSION['tenant_slug_current'] = $slug;
    $_SESSION['tenant_id'] = $context['tenant_id'];
    $_SESSION['tenant_slug'] = $context['tenant_slug'];
    $_SESSION['tenant_name'] = $context['tenant_name'];
    $_SESSION['tenant_email'] = $context['tenant_email'];
    $_SESSION['tenant_username'] = $context['tenant_username'];
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

    $stmt->bind_param('issss', $tenantId, $activityType, $activityDescription, $logDate, $logTime);
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

/**
 * Ensure a Dentist row exists and is synced for a users table dentist account.
 */
function syncDentistRecordFromUser($conn, int $userId): bool {
    if (!$conn || $userId <= 0) {
        return false;
    }

    $stmt = $conn->prepare('SELECT tenant_id, username, email, password, role, first_name, last_name FROM users WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user || strcasecmp(trim((string)$user['role']), 'Dentist') !== 0) {
        return false;
    }

    $tenantId = (int)$user['tenant_id'];
    $username = trim((string)$user['username']);
    $email = trim((string)$user['email']);
    $passwordHash = trim((string)$user['password']);
    $firstName = trim((string)$user['first_name']);
    $lastName = trim((string)$user['last_name']);

    $updateStmt = $conn->prepare('UPDATE dentist SET tenant_id = ?, first_name = ?, last_name = ?, username = ?, email = ?, password_hash = ? WHERE dentist_id = ?');
    if ($updateStmt) {
        $updateStmt->bind_param('isssssi', $tenantId, $firstName, $lastName, $username, $email, $passwordHash, $userId);
        if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
            $updateStmt->close();
            return true;
        }
        $updateStmt->close();
    }

    $insertStmt = $conn->prepare('INSERT INTO dentist (dentist_id, tenant_id, first_name, last_name, username, email, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$insertStmt) {
        return false;
    }

    $insertStmt->bind_param('iisssss', $userId, $tenantId, $firstName, $lastName, $username, $email, $passwordHash);
    $insertResult = $insertStmt->execute();
    $insertStmt->close();

    return $insertResult;
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

/**
 * Format timestamp to 12-hour format with AM/PM
 * Handles both date strings and timestamps
 * 
 * @param string|int $dateTime - Date string or timestamp
 * @param string $format - Date format (default: 'M d, Y g:i A')
 * @return string Formatted datetime in 12-hour format
 */
function formatTo12Hour($dateTime, string $format = 'M d, Y g:i A'): string {
    if (empty($dateTime)) {
        return 'N/A';
    }
    
    // Convert to timestamp if it's a string
    if (is_string($dateTime)) {
        $timestamp = strtotime($dateTime);
    } else {
        $timestamp = (int)$dateTime;
    }
    
    if ($timestamp === false) {
        return 'N/A';
    }
    
    return date($format, $timestamp);
}

/**
 * Format date only to readable format (e.g., "Mar 15, 2026")
 * 
 * @param string|int $date - Date string or timestamp
 * @return string Formatted date
 */
function formatDateReadable($date): string {
    if (empty($date)) {
        return 'N/A';
    }
    
    if (is_string($date)) {
        $timestamp = strtotime($date);
    } else {
        $timestamp = (int)$date;
    }
    
    if ($timestamp === false) {
        return 'N/A';
    }
    
    return date('M d, Y', $timestamp);
}

/**
 * Format datetime with time to readable format (e.g., "Mar 15, 2026 2:30 PM")
 * 
 * @param string|int $dateTime - Date string or timestamp
 * @return string Formatted datetime
 */
function formatDateTimeReadable($dateTime): string {
    return formatTo12Hour($dateTime, 'M d, Y g:i A');
}

/**
 * Get all global settings from the settings table
 * 
 * @return array Array of setting_key => setting_value pairs
 */
function getAllSettings(): array {
    global $conn;
    $settings = [];
    
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $stmt->close();
    }
    
    return $settings;
}

/**
 * Set a global setting in the settings table
 * 
 * @param string $key Setting key
 * @param string $value Setting value
 * @return bool True on success, false on failure
 */
function setSetting(string $key, string $value): bool {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP");
    if ($stmt) {
        $stmt->bind_param('sss', $key, $value, $value);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    return false;
}

/**
 * Get tenant configuration from tenant_configs table
 * 
 * @param int $tenantId Tenant ID
 * @return array Configuration array with defaults
 */
function getTenantConfig(int $tenantId): array {
    global $conn;
    
    $config = [
        'brand_bg_color' => '#001f3f',
        'brand_text_color' => '#ffffff',
        'primary_btn_color' => '#22c55e',
        'link_color' => '#2563eb',
        'login_title' => 'Clinic Login',
        'login_description' => 'Please sign in to access your clinic portal.',
        'brand_subtitle' => 'Powered by OralSync',
        'brand_logo_path' => '',
        'brand_bg_image_path' => ''
    ];
    
    $stmt = $conn->prepare("SELECT * FROM tenant_configs WHERE tenant_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            // Merge database values with defaults
            $config = array_merge($config, array_filter($row, function($value) {
                return $value !== null;
            }));
        }
        $stmt->close();
    }
    
    return $config;
}

/**
 * Save tenant configuration to tenant_configs table
 * 
 * @param int $tenantId Tenant ID
 * @param array $values Configuration values to save
 * @return bool True on success, false on failure
 */
function saveTenantConfig(int $tenantId, array $values): bool {
    global $conn;
    
    // Build dynamic update query
    $columns = [];
    $params = [];
    $types = '';
    
    foreach ($values as $key => $value) {
        $columns[] = "`$key` = ?";
        $params[] = $value;
        $types .= 's';
    }
    
    $params[] = $tenantId;
    $types .= 'i';
    
    $sql = "INSERT INTO tenant_configs (tenant_id, " . implode(', ', array_keys($values)) . ") 
            VALUES (?," . str_repeat('?,', count($values) - 1) . "?) 
            ON DUPLICATE KEY UPDATE " . implode(', ', $columns);
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...array_merge([$tenantId], $params));
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    return false;
}
