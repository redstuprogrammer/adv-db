<?php
require_once __DIR__ . '/connect.php';

function getSetting($key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn();
}

function setSetting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$key, $value, $value]);
}

function getAllSettings() {
    global $pdo;
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
?>