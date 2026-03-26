-- Generate sample tenant subscription revenue data for testing/demo purposes
-- This script creates monthly invoices for each tenant from their registration date until now

-- IMPORTANT: Run this AFTER updating tenants with subscription tiers (migration_add_subscription_tiers.sql)
-- and adding the payment tracking tables (migration_add_payment_tracking.sql)

-- Sample revenue amounts based on tier (using midpoint of pricing ranges)
-- Trial: ₱0/month (free tier)
-- Startup: ₱6,200/month (average of ₱4,950-₱7,450)
-- Professional: ₱17,450/month (average of ₱14,950-₱19,950)

-- Generate monthly subscription records for each tenant
INSERT INTO `tenant_subscription_revenue` (tenant_id, subscription_tier, amount, billing_period_start, billing_period_end, status, payment_date, created_at)
SELECT 
    t.tenant_id,
    t.subscription_tier,
    CASE 
        WHEN t.subscription_tier = 'trial' THEN 0.00
        WHEN t.subscription_tier = 'startup' THEN 6200.00
        WHEN t.subscription_tier = 'professional' THEN 17450.00
        ELSE 6200.00
    END AS amount,
    DATE_FORMAT(billing_date, '%Y-%m-01') as billing_period_start,
    LAST_DAY(billing_date) as billing_period_end,
    'paid',
    billing_date,
    billing_date
FROM (
    -- Generate monthly dates from tenant creation date to current date
    SELECT 
        t.tenant_id,
        t.subscription_tier,
        DATE_ADD(DATE_FORMAT(t.created_at, '%Y-%m-01'), INTERVAL n.n MONTH) as billing_date
    FROM tenants t
    CROSS JOIN (
        SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL 
        SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL
        SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
    ) n
    WHERE DATE_ADD(DATE_FORMAT(t.created_at, '%Y-%m-01'), INTERVAL n.n MONTH) <= LAST_DAY(CURDATE())
) revenue_data;

-- Alternative: If you want to add revenue data one month at a time (more control):
-- This can be run monthly to add the current month's revenue

-- Sample query to verify the revenue data:
-- SELECT 
--     t.company_name,
--     COUNT(*) as months_billed,
--     SUM(tsr.amount) as total_revenue,
--     AVG(tsr.amount) as avg_monthly_amount,
--     MIN(tsr.payment_date) as first_billing,
--     MAX(tsr.payment_date) as last_billing
-- FROM tenant_subscription_revenue tsr
-- JOIN tenants t ON tsr.tenant_id = t.tenant_id
-- GROUP BY t.tenant_id, t.company_name
-- ORDER BY total_revenue DESC;
