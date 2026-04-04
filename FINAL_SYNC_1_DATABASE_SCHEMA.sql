-- ============================================================================
-- 📋 FINAL SYNC: Database Schema - tenant_configs Table
-- ============================================================================
-- For: Groupmate's Azure MySQL Database
-- Run this script on your Azure MySQL instance before implementing PHP changes
-- ============================================================================

-- Step 1: Create the tenant_configs table
CREATE TABLE IF NOT EXISTS `tenant_configs` (
  `config_id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `brand_bg_color` VARCHAR(7) DEFAULT '#001f3f' COMMENT 'Brand card background - Default Navy Blue',
  `brand_subtitle` VARCHAR(255) DEFAULT 'Powered by OralSync',
  `login_title` VARCHAR(255) DEFAULT 'Clinic Login',
  `primary_btn_color` VARCHAR(7) DEFAULT '#22c55e' COMMENT 'Sign In button - Default Green',
  `link_color` VARCHAR(7) DEFAULT '#2563eb',
  `custom_bg_image_url` TEXT COMMENT 'Background image URL for brand card (optional)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`config_id`),
  UNIQUE KEY `unique_tenant_config` (`tenant_id`),
  CONSTRAINT `fk_config_tenant` FOREIGN KEY (`tenant_id`) 
    REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Step 2: Insert default configurations for all existing tenants
INSERT INTO `tenant_configs` (tenant_id, brand_bg_color, primary_btn_color)
SELECT tenant_id, '#001f3f', '#22c55e' FROM `tenants`
ON DUPLICATE KEY UPDATE brand_bg_color = '#001f3f', primary_btn_color = '#22c55e';

-- Step 3: Verify the table was created successfully
SELECT 'Table created successfully. Record count:' as status, COUNT(*) as config_count FROM tenant_configs;

-- Step 4: Show table structure
DESCRIBE tenant_configs;

-- Step 5: Show all current configurations
SELECT 
  tc.config_id,
  t.tenant_id,
  t.company_name,
  tc.brand_bg_color,
  tc.primary_btn_color,
  tc.brand_subtitle,
  tc.login_title
FROM tenant_configs tc
JOIN tenants t ON tc.tenant_id = t.tenant_id
ORDER BY t.company_name;

-- ============================================================================
-- KEYS POINTS FOR TEAM:
-- ✅ tenant_id is a FOREIGN KEY with ON DELETE CASCADE
--    → If a clinic is deleted, its settings are automatically removed
-- ✅ Default Navy Blue: #001f3f
-- ✅ Default Green (Sign In): #22c55e
-- ✅ Includes custom_bg_image_url for background image support
-- ✅ Timestamps for audit trail
-- ============================================================================
