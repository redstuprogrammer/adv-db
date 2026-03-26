-- Add subscription_tier column to tenants table
-- This migration adds support for subscription tier management

ALTER TABLE `tenants` ADD COLUMN `subscription_tier` VARCHAR(50) DEFAULT 'startup' AFTER `status`;
ALTER TABLE `tenants` ADD COLUMN `subscription_start_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `subscription_tier`;

-- Verify the changes
-- SELECT tenant_id, company_name, subscription_tier, subscription_start_date FROM tenants LIMIT 5;
