<?php
/**
 * Migration: Add registration_status column to tenants.
 *
 * This migration adds the missing registration_status column used by
 * register_clinic.php, api/create_paymongo_link.php, and superadmin_review_documents.php.
 */

require_once __DIR__ . '/../config/db.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    echo "Migration failed: PDO connection unavailable.\n";
    exit(1);
}

try {
    $columnExists = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'registration_status'")->fetch();

    if ($columnExists) {
        echo "Migration skipped: registration_status already exists on tenants.\n";
        exit(0);
    }

    $sql = "ALTER TABLE tenants ADD COLUMN registration_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING'";
    $pdo->exec($sql);

    echo "Migration complete: registration_status column added to tenants.\n";
    exit(0);
} catch (PDOException $ex) {
    echo "Migration failed: " . $ex->getMessage() . "\n";
    exit(1);
}
