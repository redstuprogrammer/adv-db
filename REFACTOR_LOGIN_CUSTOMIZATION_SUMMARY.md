# Login Customization Refactor - Implementation Summary

## Changes Made

### 1. Database Layer (includes/tenant_settings_functions.php)
- **Added `getTenantConfig(int $tenantId): array`** - Retrieves full tenant config from `tenant_configs` table
  - Fields: brand_bg_color, brand_text_color, primary_btn_color, link_color, login_title, login_description, brand_subtitle, brand_logo_path, brand_bg_image_path
  
- **Added `saveTenantConfig(int $tenantId, array $values): bool`** - Saves or updates tenant config
  - Uses INSERT ... ON DUPLICATE KEY UPDATE for upsert behavior
  - Validates allowed keys to prevent injection

### 2. Settings Page (settings.php)
- **Enhanced UI Components:**
  - Added color swatch buttons showing hex code + preview box
  - Replaced textarea URL input with file upload fields for:
    - Background image upload (JPG, PNG)
    - Clinic logo upload (JPG, PNG)
  - Live preview updates for colors, text, images, and logos
  
- **File Upload Handler:**
  - New function `saveTenantUploadImage()` handles:
    - File validation (type, size)
    - Directory creation: `/assets/uploads/tenants/{tenant_id}/`
    - Returns relative path for storage in database
  
- **Save Logic:**
  - All form data now saved to `tenant_configs` table via `saveTenantConfig()`
  - Images processed and stored with tenant isolation
  - Error handling with user-friendly messages
  
- **Live Preview:**
  - Dual-pane layout (left brand panel, right login card)
  - Real-time color updates via color swatches
  - Real-time image previews from file uploads
  - Logo preview with image element or emoji fallback

### 3. Tenant Login Page (tenant_login.php)
- **Config Loading:**
  - Fetches tenant_id from subdomain_slug lookup
  - Loads full config from `tenant_configs` table
  - Merges with defaults for all missing fields
  
- **Dynamic Styling:**
  - Inline CSS generated from stored config:
    - Background color and image with overlay
    - Text color for all brand elements
    - Button color for sign-in
    - Link color for forgot password
  
- **Logo Rendering:**
  - Displays uploaded logo image if available
  - Falls back to "OS" text placeholder

### 4. Database Migration
- **Created `run_tenant_config_migration.php`:**
  - Creates `tenant_configs` table with all columns
  - Adds missing columns to existing tables
  - Creates `/assets/uploads/tenants/{tenant_id}/` directories for all active tenants
  
- **Also provided:** SQL migration file in `migrations/migration_tenant_configs_login_customization.sql`

## Field Mapping

| Old Field | New Field | Type | Purpose |
|-----------|-----------|------|---------|
| login_brand_bg | brand_bg_color | VARCHAR(7) | Left panel background |
| login_button_color | primary_btn_color | VARCHAR(7) | Sign-in button |
| login_text_link_color | link_color | VARCHAR(7) | Forgot password link |
| login_title | login_title | VARCHAR(255) | Login page heading |
| login_branding_subtitle | brand_subtitle | VARCHAR(255) | Brand panel subtitle |
| *(new)* | brand_text_color | VARCHAR(7) | Brand panel text color |
| *(new)* | login_description | TEXT | Login form description |
| custom_bg_image_url | brand_bg_image_path | VARCHAR(500) | Path to uploaded background |
| *(new)* | brand_logo_path | VARCHAR(500) | Path to uploaded logo |

## Database Table Structure

```sql
CREATE TABLE `tenant_configs` (
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
)
```

## File Upload Structure
```
/assets/uploads/tenants/
├── {tenant_id}/
│   ├── brand_logo.jpg          (Clinic logo)
│   └── brand_bg_image.png      (Background image)
```

## Testing Checklist
- [ ] Run migration: `php run_tenant_config_migration.php`
- [ ] Navigate to Settings → Login Customization
- [ ] Test color pickers (should show hex and update preview instantly)
- [ ] Upload logo and background images
- [ ] Save settings
- [ ] Verify images saved to `/assets/uploads/tenants/{tenant_id}/`
- [ ] Open tenant login page
- [ ] Confirm all customizations apply (colors, images, text)
- [ ] Test with multiple tenants for isolation

## Next Steps (Optional Enhancements)
- Add image size validation and optimization
- Add drag-and-drop file upload UI
- Add preset color themes
- Add preview email notification feature
