-- Migration: Add first_name, last_name, and created_at to users table
-- This allows storing user details and tracking when users were created

-- Add columns if they don't already exist
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `first_name` VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `last_name` VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Ensure created_at has the correct default for existing records
UPDATE `users` SET `created_at` = NOW() WHERE `created_at` IS NULL;
