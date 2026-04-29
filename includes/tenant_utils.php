<?php
/**
 * tenant_utils.php - Multi-tenant utilities
 * Restored with Super Admin and helper functions.
 */

require_once __DIR__ . '/session_utils.php';

if (!function_exists('requireTenantLogin')) {
    function requireTenantLogin(?string $slug = null): void {
        SessionManager::getInstance()->requireTenantUser();
    }
}

if (!function_exists('getCurrentTenantName')) {
    function getCurrentTenantName(): string {
        $data = SessionManager::getInstance()->getTenantData();
        return $data['tenant_name'] ?? 'OralSync Clinic';
    }
}

if (!function_exists('getCurrentTenantId')) {
    function getCurrentTenantId(): ?int {
        return SessionManager::getInstance()->getTenantId();
    }
}

if (!function_exists('getAbsoluteBaseUrl')) {
    function getAbsoluteBaseUrl(): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return rtrim($protocol . $host, '/');
    }
}

if (!function_exists('envOrNull')) {
    function envOrNull(string $key): ?string {
        $val = getenv($key);
        return $val === false ? null : $val;
    }
}

if (!function_exists('getAppBasePath')) {
    function getAppBasePath(): string {
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        return rtrim(str_replace('\\', '/', $scriptDir), '/');
    }
}

/**
 * Super Admin Settings & Analytics
 */

if (!function_exists('getAllSettings')) {
    function getAllSettings(): array {
        global $conn;
        $settings = [];
        if (!$conn) return $settings;
        
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
}

if (!function_exists('setSetting')) {
    function setSetting(string $key, string $value): bool {
        global $conn;
        if (!$conn) return false;
        
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP");
        if ($stmt) {
            $stmt->bind_param('sss', $key, $value, $value);
            $success = $stmt->execute();
            $stmt->close();
            return $success;
        }
        return false;
    }
}

if (!function_exists('getSuperAdminAnalytics')) {
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
        if (!$conn) return $metrics;

        $queries = [
            'total_tenants' => 'SELECT COUNT(*) AS c FROM tenants',
            'active_tenants' => 'SELECT COUNT(*) AS c FROM tenants WHERE status = "active"',
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
        $metrics['inactive_tenants'] = $metrics['total_tenants'] - $metrics['active_tenants'];

        // Daily logs for last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            
            $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM superadmin_logs WHERE log_date = ?');
            $stmt->bind_param('s', $date);
            $stmt->execute();
            $metrics['daily_superadmin_logs'][] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();

            $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM tenant_activity_logs WHERE log_date = ?');
            $stmt->bind_param('s', $date);
            $stmt->execute();
            $metrics['daily_tenant_activities'][] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();
        }

        // Monthly growth
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM tenants WHERE DATE_FORMAT(created_at, "%Y-%m") = ?');
            $stmt->bind_param('s', $month);
            $stmt->execute();
            $metrics['monthly_tenant_growth'][] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();
        }

        return $metrics;
    }
}

/**
 * Tenant Configurations (Polymorphic)
 */

if (!function_exists('getTenantConfig')) {
    function getTenantConfig(int $tenantId): array {
        global $conn;
        $configs = [];
        if (!$conn || $tenantId <= 0) return $configs;
        
        $stmt = $conn->prepare("SELECT config_key, config_value FROM tenant_configs WHERE tenant_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $configs[$row['config_key']] = $row['config_value'];
            }
            $stmt->close();
        }
        return $configs;
    }
}

if (!function_exists('getTenantConfigValue')) {
    function getTenantConfigValue(int $tenantId, string $key, $default = null) {
        global $conn;
        if (!$conn || $tenantId <= 0) return $default;
        
        $stmt = $conn->prepare("SELECT config_value FROM tenant_configs WHERE tenant_id = ? AND config_key = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $tenantId, $key);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row ? $row['config_value'] : $default;
        }
        return $default;
    }
}

if (!function_exists('saveTenantConfig')) {
    function saveTenantConfig(int $tenantId, array $values): bool {
        global $conn;
        if (!$conn || $tenantId <= 0) return false;
        
        $success = true;
        foreach ($values as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO tenant_configs (tenant_id, config_key, config_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE config_value = ?");
            if ($stmt) {
                $stmt->bind_param('isss', $tenantId, $key, $value, $value);
                if (!$stmt->execute()) $success = false;
                $stmt->close();
            } else {
                $success = false;
            }
        }
        return $success;
    }
}

/**
 * Formatting Helpers
 */

if (!function_exists('formatTo12Hour')) {
    function formatTo12Hour($dateTime, string $format = 'M d, Y g:i A'): string {
        if (empty($dateTime)) return 'N/A';
        $timestamp = is_string($dateTime) ? strtotime($dateTime) : (int)$dateTime;
        return ($timestamp === false) ? 'N/A' : date($format, $timestamp);
    }
}

if (!function_exists('formatDateReadable')) {
    function formatDateReadable($date): string {
        return formatTo12Hour($date, 'M d, Y');
    }
}

if (!function_exists('formatDateTimeReadable')) {
    function formatDateTimeReadable($dateTime): string {
        return formatTo12Hour($dateTime, 'M d, Y g:i A');
    }
}

/**
 * Dashboard Metric Helpers
 */

if (!function_exists('getTenantPatientCount')) {
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
}

if (!function_exists('generateUniqueTenantCode')) {
    function generateUniqueTenantCode($conn, $length = 8) {
        $exists = true;
        $code = '';
        while ($exists) {
            $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[rand(0, strlen($chars) - 1)];
            }
            $stmt = $conn->prepare("SELECT 1 FROM tenants WHERE tenant_code = ?");
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) $exists = false;
            $stmt->close();
        }
        return $code;
    }
}

if (!function_exists('syncDentistRecordFromUser')) {
    function syncDentistRecordFromUser($conn, int $userId): bool {
        if (!$conn || $userId <= 0) return false;
        $stmt = $conn->prepare('SELECT tenant_id, username, email, password, role, first_name, last_name FROM users WHERE user_id = ? LIMIT 1');
        if (!$stmt) return false;
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$user || strcasecmp(trim((string)$user['role']), 'Dentist') !== 0) return false;
        
        $updateStmt = $conn->prepare('UPDATE dentist SET tenant_id = ?, first_name = ?, last_name = ?, username = ?, email = ?, password_hash = ? WHERE dentist_id = ?');
        if ($updateStmt) {
            $updateStmt->bind_param('isssssi', $user['tenant_id'], $user['first_name'], $user['last_name'], $user['username'], $user['email'], $user['password'], $userId);
            if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
                $updateStmt->close();
                return true;
            }
            $updateStmt->close();
        }
        
        $insertStmt = $conn->prepare('INSERT INTO dentist (dentist_id, tenant_id, first_name, last_name, username, email, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if ($insertStmt) {
            $insertStmt->bind_param('iisssss', $userId, $user['tenant_id'], $user['first_name'], $user['last_name'], $user['username'], $user['email'], $user['password']);
            $res = $insertStmt->execute();
            $insertStmt->close();
            return $res;
        }
        return false;
    }
}
