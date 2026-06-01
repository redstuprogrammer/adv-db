<?php
/**
 * Migration: Add OCR Verification Columns and Tables
 * 
 * This script adds the necessary database schema for Groq Vision OCR verification:
 * - Adds columns to tenants table for OCR results and registration status
 * - Creates document_review_queue table for manual admin reviews
 * 
 * Run this from your admin panel or CLI:
 *   php db_migrations/001_add_ocr_verification.php
 */

// Include database configuration
require_once(__DIR__ . '/../config/db_config.php'); // Adjust path to your DB config

try {
    // ========== ALTER TENANTS TABLE ==========
    
    // Check if registration_status column already exists
    $check_status = $conn->query("SHOW COLUMNS FROM tenants LIKE 'registration_status'");
    if ($check_status->num_rows == 0) {
        $sql1 = "ALTER TABLE tenants ADD COLUMN registration_status ENUM('PENDING', 'APPROVED', 'PENDING_ADMIN_REVIEW') DEFAULT 'PENDING' AFTER id";
        if ($conn->query($sql1)) {
            echo "[OK] Added registration_status column to tenants<br>";
        } else {
            throw new Exception("Error adding registration_status: " . $conn->error);
        }
    } else {
        echo "[SKIP] registration_status column already exists<br>";
    }

    // Check if ocr_extracted column already exists
    $check_ocr = $conn->query("SHOW COLUMNS FROM tenants LIKE 'ocr_extracted'");
    if ($check_ocr->num_rows == 0) {
        $sql2 = "ALTER TABLE tenants ADD COLUMN ocr_extracted JSON DEFAULT NULL AFTER registration_status";
        if ($conn->query($sql2)) {
            echo "[OK] Added ocr_extracted column to tenants<br>";
        } else {
            throw new Exception("Error adding ocr_extracted: " . $conn->error);
        }
    } else {
        echo "[SKIP] ocr_extracted column already exists<br>";
    }

    // Check if ocr_confidence column already exists
    $check_conf = $conn->query("SHOW COLUMNS FROM tenants LIKE 'ocr_confidence'");
    if ($check_conf->num_rows == 0) {
        $sql3 = "ALTER TABLE tenants ADD COLUMN ocr_confidence FLOAT DEFAULT NULL AFTER ocr_extracted";
        if ($conn->query($sql3)) {
            echo "[OK] Added ocr_confidence column to tenants<br>";
        } else {
            throw new Exception("Error adding ocr_confidence: " . $conn->error);
        }
    } else {
        echo "[SKIP] ocr_confidence column already exists<br>";
    }

    // Check if ocr_raw_text column already exists
    $check_raw = $conn->query("SHOW COLUMNS FROM tenants LIKE 'ocr_raw_text'");
    if ($check_raw->num_rows == 0) {
        $sql4 = "ALTER TABLE tenants ADD COLUMN ocr_raw_text LONGTEXT DEFAULT NULL AFTER ocr_confidence";
        if ($conn->query($sql4)) {
            echo "[OK] Added ocr_raw_text column to tenants<br>";
        } else {
            throw new Exception("Error adding ocr_raw_text: " . $conn->error);
        }
    } else {
        echo "[SKIP] ocr_raw_text column already exists<br>";
    }

    // Check if ocr_processed_at column already exists
    $check_at = $conn->query("SHOW COLUMNS FROM tenants LIKE 'ocr_processed_at'");
    if ($check_at->num_rows == 0) {
        $sql5 = "ALTER TABLE tenants ADD COLUMN ocr_processed_at DATETIME DEFAULT NULL AFTER ocr_raw_text";
        if ($conn->query($sql5)) {
            echo "[OK] Added ocr_processed_at column to tenants<br>";
        } else {
            throw new Exception("Error adding ocr_processed_at: " . $conn->error);
        }
    } else {
        echo "[SKIP] ocr_processed_at column already exists<br>";
    }

    // ========== CREATE DOCUMENT REVIEW QUEUE TABLE ==========
    
    $check_queue = $conn->query("SHOW TABLES LIKE 'document_review_queue'");
    if ($check_queue->num_rows == 0) {
        $sql6 = "CREATE TABLE IF NOT EXISTS document_review_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT DEFAULT NULL,
            clinic_name VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME DEFAULT NULL,
            status ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
            reason TEXT DEFAULT NULL,
            model_output JSON DEFAULT NULL,
            admin_notes TEXT DEFAULT NULL,
            reviewed_by_admin_id INT DEFAULT NULL,
            INDEX idx_tenant_id (tenant_id),
            INDEX idx_status (status),
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql6)) {
            echo "[OK] Created document_review_queue table<br>";
        } else {
            throw new Exception("Error creating document_review_queue table: " . $conn->error);
        }
    } else {
        echo "[SKIP] document_review_queue table already exists<br>";
    }

    // ========== CREATE INDEXES ==========
    
    $check_idx = $conn->query("SHOW INDEX FROM tenants WHERE Key_name='idx_registration_status'");
    if ($check_idx->num_rows == 0) {
        $sql7 = "CREATE INDEX idx_registration_status ON tenants(registration_status)";
        if ($conn->query($sql7)) {
            echo "[OK] Created idx_registration_status index<br>";
        } else {
            throw new Exception("Error creating index: " . $conn->error);
        }
    } else {
        echo "[SKIP] idx_registration_status index already exists<br>";
    }

    echo "<hr><strong style='color: green;'>✓ Migration completed successfully!</strong><br>";
    echo "The database is now ready for OCR verification.";

} catch (Exception $e) {
    echo "<strong style='color: red;'>✗ Migration failed: " . $e->getMessage() . "</strong><br>";
    exit(1);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
