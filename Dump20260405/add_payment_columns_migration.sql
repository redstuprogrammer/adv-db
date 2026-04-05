-- Add new columns to payment table for multi-procedure cart system
-- This migration adds support for storing procedures as JSON, tracking payment source, and reference numbers

ALTER TABLE payment
ADD COLUMN procedures_json TEXT AFTER status,
ADD COLUMN source ENUM('web', 'mobile') DEFAULT 'web' AFTER procedures_json,
ADD COLUMN reference_number VARCHAR(255) AFTER source;

-- Add index for better query performance on source
ALTER TABLE payment ADD INDEX idx_payment_source (source);

-- Update existing records to have default values
UPDATE payment SET procedures_json = NULL, source = 'web', reference_number = NULL WHERE procedures_json IS NULL;