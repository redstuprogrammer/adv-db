-- Add password reset functionality columns for tenants
ALTER TABLE `tenants` 
ADD COLUMN `username` VARCHAR(50) UNIQUE DEFAULT NULL AFTER `subdomain_slug`,
ADD COLUMN `password_reset_token` VARCHAR(255) DEFAULT NULL AFTER `password`,
ADD COLUMN `password_reset_expires` DATETIME DEFAULT NULL AFTER `password_reset_token`;

-- Add password reset functionality columns for super_admins
ALTER TABLE `super_admins`
ADD COLUMN `password_reset_token` VARCHAR(255) DEFAULT NULL AFTER `password_hash`,
ADD COLUMN `password_reset_expires` DATETIME DEFAULT NULL AFTER `password_reset_token`;

-- Create index for faster lookups on reset tokens
CREATE INDEX idx_tenant_reset_token ON `tenants` (`password_reset_token`);
CREATE INDEX idx_superadmin_reset_token ON `super_admins` (`password_reset_token`);
