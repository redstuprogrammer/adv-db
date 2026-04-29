<?php
/**
 * API DB Config - Compatible with includes/connect.php
 */
require_once __DIR__ . '/../includes/connect.php';

if (!isset($pdo)) {
    die_json(500, 'PDO unavailable');
}
?>

