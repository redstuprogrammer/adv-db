-- Migration: Add login customization columns to tenant_configs table
-- Date: 2026-04-05
-- Description: Adds columns for storing login customization settings, logo paths, and background image paths

-- Check if table exists, if not create it
CREATE TABLE IF NOT EXISTS `tenant_configs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT NOT NULL UNIQUE,
  `brand_bg_color` VARCHAR(7) DEFAULT '#001f3f',
  `brand_text_color` VARCHAR(7) DEFAULT '#ffffff',
  `primary_btn_color` VARCHAR(7) DEFAULT '#22c55e',
  `link_color` VARCHAR(7) DEFAULT '#2563eb',
  `login_title` VARCHAR(255) DEFAULT 'Clinic Login',
  `login_description` TEXT,
  `brand_subtitle` VARCHAR(255) DEFAULT 'Powered by OralSync',
  `brand_logo_path` VARCHAR(500),
  `brand_bg_image_path` VARCHAR(500),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing columns if they don't exist (for existing tables)
ALTER TABLE `tenant_configs` ADD COLUMN IF NOT EXISTS `brand_text_color` VARCHAR(7) DEFAULT '#ffffff';
ALTER TABLE `tenant_configs` ADD COLUMN IF NOT EXISTS `login_description` TEXT;
ALTER TABLE `tenant_configs` ADD COLUMN IF NOT EXISTS `brand_logo_path` VARCHAR(500);
ALTER TABLE `tenant_configs` ADD COLUMN IF NOT EXISTS `brand_bg_image_path` VARCHAR(500);

-- Create upload directory structure hint (execute PHP migrations for actual directory creation)
-- Uses: /assets/uploads/tenants/{tenant_id}/
