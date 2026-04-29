<?php
/**
 * tenant_utils.php - Multi-tenant utilities
 */

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
        // Resolve the base path of the application relative to the web root.
        // If the app is in /adv db/, this returns '/adv db'.
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        return rtrim(str_replace('\\', '/', $scriptDir), '/');
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

if (!function_exists('getTenantConfig')) {
    function getTenantConfig(int $tenantId, string $key, $default = null) {
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
