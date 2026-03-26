-- Add payment_date and other tracking columns to payment table
ALTER TABLE `payment` ADD COLUMN `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `status`;

-- Create table for tracking tenant subscription revenue (for SaaS billing)
CREATE TABLE IF NOT EXISTS `tenant_subscription_revenue` (
    `revenue_id` INT NOT NULL AUTO_INCREMENT,
    `tenant_id` INT NOT NULL,
    `subscription_tier` VARCHAR(50) DEFAULT 'startup',
    `amount` DECIMAL(10, 2) NOT NULL,
    `billing_period_start` TIMESTAMP,
    `billing_period_end` TIMESTAMP,
    `status` VARCHAR(50) DEFAULT 'paid',
    `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`revenue_id`),
    KEY `fk_revenue_tenant` (`tenant_id`),
    KEY `idx_revenue_date` (`payment_date`),
    CONSTRAINT `fk_revenue_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create indexes for better query performance
CREATE INDEX idx_payment_date ON `payment` (`payment_date`);
CREATE INDEX idx_payment_tenant_date ON `payment` (`tenant_id`, `payment_date`);
