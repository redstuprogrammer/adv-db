-- Migration: Add missing columns to appointment table for appointment scheduling
-- This migration adds the necessary columns that are referenced in the PHP code but missing from the schema

-- Add missing columns to appointment table
ALTER TABLE `appointment` ADD COLUMN IF NOT EXISTS `appointment_time` TIME DEFAULT NULL AFTER `appointment_date`;
ALTER TABLE `appointment` ADD COLUMN IF NOT EXISTS `service_id` INT DEFAULT NULL AFTER `appointment_time`;
ALTER TABLE `appointment` ADD COLUMN IF NOT EXISTS `notes` TEXT DEFAULT NULL AFTER `service_id`;

-- Add foreign key constraint for service_id if not exists (only if column was just created)
-- Note: This may fail if the constraint already exists, which is OK
ALTER TABLE `appointment` ADD CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON DELETE SET NULL;

