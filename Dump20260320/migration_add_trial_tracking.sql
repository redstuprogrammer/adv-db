-- Add trial tracking columns to tenants table
-- This allows better tracking of trial expirations without relying on subscription_start_date

ALTER TABLE `tenants` 
ADD COLUMN `trial_start_date` TIMESTAMP NULL AFTER `subscription_start_date`,
ADD COLUMN `trial_end_date` TIMESTAMP NULL AFTER `trial_start_date`,
ADD INDEX `idx_trial_end_date` (`trial_end_date`);

-- Populate trial dates for existing trial tenants (14 days from creation)
UPDATE `tenants`
SET trial_start_date = created_at,
    trial_end_date = DATE_ADD(created_at, INTERVAL 14 DAY)
WHERE subscription_tier = 'trial' 
  AND trial_start_date IS NULL;

-- Add check constraint to ensure trial_end_date is after trial_start_date
-- MySQL doesn't support CHECK constraints directly, but we can enforce at application level
-- or use a trigger if needed

-- Verify the changes
-- SELECT tenant_id, company_name, subscription_tier, subscription_start_date, trial_start_date, trial_end_date 
-- FROM tenants 
-- WHERE subscription_tier = 'trial' 
-- LIMIT 10;
