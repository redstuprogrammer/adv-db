<?php
require_once __DIR__ . '/connect.php';

function getTenantSetting($tenantId, $key) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = ?");
        $stmt->execute([$tenantId, $key]);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        return null;
    }
}

function setTenantSetting($tenantId, $key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO tenant_settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$tenantId, $key, $value, $value]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function getAllTenantSettings($tenantId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        return [];
    }
}
?>