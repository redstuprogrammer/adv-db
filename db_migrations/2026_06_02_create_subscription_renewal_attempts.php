<?php
// Run: php 2026_06_02_create_subscription_renewal_attempts.php
require_once __DIR__ . '/../includes/connect.php';

if (!isset($conn) || $conn->connect_error) {
    echo "DB connect failed\n"; exit(1);
}

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS subscription_renewal_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subscription_id INT NOT NULL,
  tenant_id INT NOT NULL,
  attempt_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  attempt_count INT NOT NULL DEFAULT 1,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  response TEXT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  next_retry_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_subscription (subscription_id),
  INDEX idx_tenant (tenant_id),
  INDEX idx_next_retry (next_retry_at)
);
SQL;

if ($conn->query($sql) === TRUE) {
    echo "Created subscription_renewal_attempts table (or already exists).\n"; exit(0);
} else {
    echo "Failed: " . $conn->error . "\n"; exit(1);
}
