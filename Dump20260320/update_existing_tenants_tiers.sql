-- Update existing tenants with random subscription tiers
-- This assigns random tiers to tenants that were registered before the tier system was added

-- Option 1: Update all tenants to random tiers (startup or professional)
UPDATE tenants 
SET subscription_tier = IF(RAND() < 0.6, 'startup', 'professional')
WHERE subscription_tier IS NULL OR subscription_tier = '';

-- Option 2: If you prefer a more balanced distribution, use this instead:
-- UPDATE tenants 
-- SET subscription_tier = CASE 
--     WHEN tenant_id % 2 = 0 THEN 'professional'
--     ELSE 'startup'
-- END
-- WHERE subscription_tier IS NULL OR subscription_tier = '';

-- Verify the distribution
-- SELECT subscription_tier, COUNT(*) as count FROM tenants GROUP BY subscription_tier;

-- Example query to see which tenants got which tier
-- SELECT tenant_id, company_name, ownership_name, subscription_tier, subscription_start_date FROM tenants ORDER BY tenant_id;
