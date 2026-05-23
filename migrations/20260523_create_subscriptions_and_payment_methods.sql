-- Migration: Create normalized subscription schema for tenants
-- Date: 2026-05-23

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `plan_id` INT NOT NULL,
  `status` ENUM('trialing','active','past_due','canceled','inactive') NOT NULL DEFAULT 'trialing',
  `trial_starts_at` DATETIME DEFAULT NULL,
  `trial_ends_at` DATETIME DEFAULT NULL,
  `current_period_start` DATETIME DEFAULT NULL,
  `current_period_end` DATETIME DEFAULT NULL,
  `auto_renew` TINYINT(1) NOT NULL DEFAULT 1,
  `payment_method_id` BIGINT UNSIGNED DEFAULT NULL,
  `price` DECIMAL(10,2) DEFAULT NULL,
  `currency` VARCHAR(8) DEFAULT 'PHP',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_subscriptions_tenant_id` (`tenant_id`),
  INDEX `idx_subscriptions_plan_id` (`plan_id`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `provider` VARCHAR(50) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `brand` VARCHAR(50) DEFAULT NULL,
  `last4` VARCHAR(4) DEFAULT NULL,
  `exp_month` TINYINT DEFAULT NULL,
  `exp_year` SMALLINT DEFAULT NULL,
  `billing_contact` JSON DEFAULT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_payment_methods_tenant_id` (`tenant_id`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS=1;

-- Optional initial data migration for existing tenant subscription state.
-- This can be enabled after verifying the plan matching logic.
--
-- INSERT INTO `subscriptions` (`tenant_id`,`plan_id`,`status`,`trial_starts_at`,`trial_ends_at`,`current_period_start`,`current_period_end`,`auto_renew`,`price`,`currency`)
-- SELECT
--   t.tenant_id,
--   sp.plan_id,
--   'active',
--   t.subscription_start_date,
--   DATE_ADD(t.subscription_start_date, INTERVAL t.subscription_duration MONTH),
--   t.subscription_start_date,
--   DATE_ADD(t.subscription_start_date, INTERVAL t.subscription_duration MONTH),
--   1,
--   sp.price,
--   'PHP'
-- FROM `tenants` t
-- JOIN `subscription_plans` sp ON LOWER(sp.plan_name) LIKE CONCAT('%', LOWER(t.subscription_tier), '%');

-- Rollback / cleanup statements
-- DROP TABLE IF EXISTS `payment_methods`;
-- DROP TABLE IF EXISTS `subscriptions`;
