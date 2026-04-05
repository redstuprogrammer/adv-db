<?php
// Force redeployment - version 1.1
session_start();
require_once __DIR__ . '/includes/security_headers.php';
if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadmin_login.php');
    exit;
}
require_once __DIR__ . '/includes/connect.php';

// Load current settings
require_once __DIR__ . '/settings.php';
try {
    $currentSettings = getAllSettings();
} catch (Exception $e) {
    $currentSettings = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/settings.php';

    try {
        // Save settings
        setSetting('system_name', $_POST['system_name'] ?? 'OralSync');
        setSetting('max_tenants', $_POST['max_tenants'] ?? '');
        setSetting('max_users_per_tenant', $_POST['max_users_per_tenant'] ?? '');
        setSetting('storage_limit', $_POST['storage_limit'] ?? '');

        // Handle logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = 'logo_' . time() . '.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename);
            setSetting('logo_path', '/uploads/' . $filename);
        }

        $message = "Settings saved successfully!";
    } catch (Exception $e) {
        $message = "Error saving settings: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Super Admin Settings</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        :root {
            --sa-primary: #0d3b66;
            --sa-muted: #64748b;
            --sa-border: #e2e8f0;
            --sa-bg: #f8fafc;
        }

        body {
            background-color: var(--sa-bg);
            color: #0f172a;
        }

        .sa-main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            margin-bottom: 10px;
        }

        .sa-main-header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--sa-primary);
            margin: 0;
        }

        .sa-main-header span {
            font-size: 0.85rem;
            color: var(--sa-muted);
        }

        .sa-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sa-profile span {
            font-weight: 600;
        }

        .sa-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .sa-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .sa-card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .sa-card-subtitle {
            font-size: 0.875rem;
            color: var(--sa-muted);
            margin: 0;
        }

        .sa-btn {
            background: var(--sa-primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
        }

        .sa-btn:hover {
            background: #0a2f52;
        }

        .sa-btn-outline {
            background: white;
            color: var(--sa-primary);
            border: 1px solid var(--sa-border);
        }

        .sa-btn-outline:hover {
            background: var(--sa-bg);
        }

        .sa-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }

        .sa-form-group {
            display: flex;
            flex-direction: column;
        }

        .sa-form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 5px;
        }

        .sa-form-group input,
        .sa-form-group select {
            padding: 8px 12px;
            border: 1px solid var(--sa-border);
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .sa-form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .settings-section {
            margin-bottom: 30px;
        }

        .settings-section h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 15px;
        }

        .roles-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .roles-table th,
        .roles-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid var(--sa-border);
        }

        .roles-table th {
            background: var(--sa-bg);
            font-weight: 600;
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 10px;
        }

        .permission-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-radius: 4px;
            background: #fafbfc;
        }

        .permission-item input[type="checkbox"] {
            margin: 0;
            cursor: pointer;
            flex-shrink: 0;
            width: 18px;
            height: 18px;
        }

        .permission-item label {
            margin: 0;
            cursor: pointer;
            font-size: 0.875rem;
            color: #374151;
        }

        .logo-preview {
            width: 100px;
            height: 100px;
            border: 1px solid var(--sa-border);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--sa-muted);
            font-size: 0.875rem;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <?php include __DIR__ . '/includes/sidebar_superadmin.php'; ?>

    <main class="main-content">
        <header class="sa-main-header">
            <div>
                <h1>Settings</h1>
                <span>Configure system parameters</span>
            </div>
            <div class="sa-profile">
                <span>Welcome, <strong>Super Admin</strong></span>
                <div class="sa-profile-avatar">🛡️</div>
            </div>
        </header>

        <?php if (isset($message)): ?>
        <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" id="settingsForm" enctype="multipart/form-data">
            <!-- System Name and Branding -->
            <div class="sa-card settings-section">
                <div class="sa-card-header">
                    <div>
                        <h3>System Name and Branding</h3>
                        <div class="sa-card-subtitle">Configure the system identity</div>
                    </div>
                </div>
                <div class="sa-form-grid">
                    <div class="sa-form-group">
                        <label for="system_name">System Name</label>
                        <input type="text" id="system_name" name="system_name" value="<?php echo htmlspecialchars($currentSettings['system_name'] ?? 'OralSync'); ?>">
                    </div>
                    <div class="sa-form-group">
                        <label for="logo">Logo / Icon Upload</label>
                        <input type="file" id="logo" name="logo" accept="image/png, image/jpeg, image/jpg">
                        <div class="logo-preview" id="logo-preview">
                            <?php if (!empty($currentSettings['logo_path']) && file_exists(__DIR__ . '/uploads/' . $currentSettings['logo_path'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($currentSettings['logo_path']); ?>?t=<?php echo time(); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;" id="logo-img">
                            <?php else: ?>
                                <span id="logo-placeholder">No logo uploaded</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tenant Limits and Rules -->
            <div class="sa-card settings-section">
                <div class="sa-card-header">
                    <div>
                        <h3>Tenant Limits and Rules</h3>
                        <div class="sa-card-subtitle">Subscription tier-based limits and platform-wide constraints</div>
                    </div>
                </div>
                
                <!-- Tier-based Limits Information -->
                <div style="margin-bottom: 25px;">
                    <h4 style="color: #0d3b66; margin-bottom: 15px;">Subscription Tier Limits</h4>
                    <table class="roles-table">
                        <thead>
                            <tr>
                                <th>Tier</th>
                                <th>Max Dentists</th>
                                <th>Max Receptionists</th>
                                <th>Max Patients</th>
                                <th>Storage (GB)</th>
                                <th>Dental Chart</th>
                                <th>SMS Reminders</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            require_once __DIR__ . '/includes/subscription_tiers.php';
                            foreach (getAllTiers() as $tierKey => $tier) {
                                $tierPrice = $tier['price_min'] === 0 && $tier['price_max'] === 0 
                                    ? 'Free' 
                                    : '$' . $tier['price_min'] . '-' . $tier['price_max'] . '/mo';
                                
                                echo "<tr>
                                    <td><strong>" . htmlspecialchars($tier['name']) . "</strong><br><small style='color: var(--sa-muted);'>" . htmlspecialchars($tierPrice) . "</small></td>
                                    <td>" . ($tier['features']['max_dentists'] ?? 0) . "</td>
                                    <td>" . ($tier['features']['max_receptionists'] ?? 0) . "</td>
                                    <td>" . ($tier['features']['max_patients'] ?? 0) . "</td>
                                    <td>" . ($tier['features']['max_storage_gb'] ?? 0) . "</td>
                                    <td>" . (($tier['features']['dental_chart_tracking'] ?? false) ? '✓' : '✗') . "</td>
                                    <td>" . (($tier['features']['sms_reminders'] ?? false) ? '✓' : '✗') . "</td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Platform-wide Settings -->
                <div style="border-top: 1px solid var(--sa-border); padding-top: 20px;">
                    <h4 style="color: #0d3b66; margin-bottom: 15px;">Platform-wide Constraints</h4>
                    <div class="sa-form-grid">
                        <div class="sa-form-group">
                            <label for="max_tenants">Maximum Active Tenants</label>
                            <input type="number" id="max_tenants" name="max_tenants" value="<?php echo htmlspecialchars($currentSettings['max_tenants'] ?? ''); ?>" placeholder="Leave empty for unlimited">
                            <div class="sa-note">Limits the total number of active tenant subscriptions on the platform</div>
                        </div>
                        <div class="sa-form-group">
                            <label for="max_users_per_tenant">Maximum Users per Tenant (Override)</label>
                            <input type="number" id="max_users_per_tenant" name="max_users_per_tenant" value="<?php echo htmlspecialchars($currentSettings['max_users_per_tenant'] ?? ''); ?>" placeholder="Leave empty for tier-based limits">
                            <div class="sa-note">If set, overrides tier limits for all tenants</div>
                        </div>
                        <div class="sa-form-group">
                            <label for="storage_limit">Storage Limit Override (GB)</label>
                            <input type="number" id="storage_limit" name="storage_limit" value="<?php echo htmlspecialchars($currentSettings['storage_limit'] ?? ''); ?>" placeholder="Leave empty for tier-based limits">
                            <div class="sa-note">If set, overrides tier-based storage limits</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Roles and Permissions -->
            <div class="sa-card settings-section">
                <div class="sa-card-header">
                    <div>
                        <h3>User Roles and Permissions</h3>
                        <div class="sa-card-subtitle">Define roles and their access levels</div>
                    </div>
                </div>
                <table class="roles-table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Description</th>
                            <th>Permissions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Admin</td>
                            <td>Full clinic management and administration</td>
                            <td>
                                <div class="permissions-grid">
                                    <label class="permission-item">
                                        <input type="checkbox" checked disabled> <span>Appointments</span>
                                    </label>
                                    <label class="permission-item">
                                        <input type="checkbox" checked disabled> <span>Patients</span>
                                    </label>
                                    <label class="permission-item">
                                        <input type="checkbox" disabled> <span>Billing</span>
                                    </label>
                                    <label class="permission-item">
                                        <input type="checkbox" checked disabled> <span>Staff Management</span>
                                    </label>
                                    <label class="permission-item">
                                        <input type="checkbox" checked disabled> <span>Reports</span>
                                    </label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Front Desk / Receptionist</td>
                            <td>Appointment scheduling and patient coordination</td>
                            <td>
                                <div class="permissions-grid">
                                    <label class="permission-item">
                                        <input type="checkbox" checked disabled> <span>Appointments</span>
                                    </label>
                                    <label class="permission-item">
                                        <input type="checkbox" checked disabled> <span>Patients</span>
                                    </label>
                                    <label class="permission-item">
                                        <input type="checkbox" checked disabled> <span>Billing</span>
                                    </label>
                                    <label class="permission-item">
                                        <input type="checkbox" disabled> <span>Staff Management</span>
                                    </label>
                                    <label class="permission-item">
                                        <input type="checkbox" disabled> <span>Reports</span>
                                    </label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Dentist</td>
                            <td>Clinical records and patient treatment</td>
                            <td>
                                <div class="permissions-grid">
                                    <label class="permission-item">
                                        <input type="checkbox" checked disabled> <span>Appointments</span>
                                    </label>
                                    <label class="permission-item">
                                        <input type="checkbox" checked disabled> <span>Patients</span>
                                    </label>
                                    <label class="permission-item">
                                        <input type="checkbox" checked disabled> <span>Clinical Notes</span>
                                    </label>
                                    <label class="permission-item">
                                        <input type="checkbox" disabled> <span>Billing</span>
                                    </label>
                                    <label class="permission-item">
                                        <input type="checkbox" disabled> <span>Staff Management</span>
                                    </label>
                                    <label class="permission-item">
                                        <input type="checkbox" disabled> <span>Reports</span>
                                    </label>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="sa-form-actions">
                <button type="submit" class="sa-btn">Save Settings</button>
                <button type="button" class="sa-btn sa-btn-outline" onclick="resetSettings()">Reset to Defaults</button>
            </div>
        </form>
    </div>

    <script>
        // Logo preview during upload
        document.getElementById('logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('logo-preview');
                    preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 100%; max-height: 100%; object-fit: contain;" id="logo-img">';
                };
                reader.readAsDataURL(file);
            }
        });

        // Handle form submission with auto-refresh
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Auto-refresh after 500ms to show updated logo and system name
                setTimeout(() => {
                    location.reload();
                }, 500);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving settings');
            });
        });

        // Dropdown toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.querySelector('.menu-dropdown-toggle');
            const dropdownItems = document.querySelector('.menu-dropdown-items');
            const dropdown = document.querySelector('.menu-dropdown');

            if (dropdownToggle) {
                dropdownToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (dropdownItems.style.display === 'none' || dropdownItems.style.display === '') {
                        dropdownItems.style.display = 'flex';
                        dropdownToggle.classList.add('active');
                    } else {
                        dropdownItems.style.display = 'none';
                        dropdownToggle.classList.remove('active');
                    }
                });
            }

            // Prevent dropdown from closing when clicking dropdown items
            if (dropdownItems) {
                dropdownItems.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (dropdown && !dropdown.contains(e.target)) {
                    if (dropdownItems) dropdownItems.style.display = 'none';
                    if (dropdownToggle) dropdownToggle.classList.remove('active');
                }
            });
            
            // Expand dropdown if on a reports page
            const currentPage = window.location.pathname;
            if ((currentPage.includes('superadmin_reports') || currentPage.includes('superadmin_sales_report')) && dropdownToggle && dropdownItems) {
                dropdownItems.style.display = 'flex';
                dropdownToggle.classList.add('active');
            }
        });

        function validateForm() {
            return true;
        }

        function resetSettings() {
            if (confirm('Are you sure you want to reset all settings to defaults?')) {
                // Reset form
                document.querySelector('form').reset();
                document.getElementById('logo-preview').innerHTML = '<span id="logo-placeholder">No logo uploaded</span>';
            }
        }
    </script>
    </main>
</body>
</html>
