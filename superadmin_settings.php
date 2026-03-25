<?php
session_start();
require_once __DIR__ . '/security_headers.php';
if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadmin_login.php');
    exit;
}
require_once __DIR__ . '/connect.php';

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
            setSetting('logo_path', $filename);
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
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }

        .permission-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .permission-item input[type="checkbox"] {
            margin: 0;
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
        <header class="sa-main-header">
            <div>
                <h1>Settings</h1>
                <span>Configure system parameters</span>
            </div>
            <div class="sa-profile">
                <span>Super Admin</span>
                <a href="superadmin_logout.php" class="sa-btn sa-btn-outline">Logout</a>
            </div>
        </header>

        <?php if (isset($message)): ?>
        <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST">
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
                        <input type="text" id="system_name" name="system_name" value="<?php echo htmlspecialchars($currentSettings['system_name'] ?? 'OralSync'); ?>" readonly>
                    </div>
                    <div class="sa-form-group">
                        <label for="logo">Logo Upload</label>
                        <input type="file" id="logo" name="logo" accept="image/*">
                        <div class="logo-preview" id="logo-preview">
                            <?php if (!empty($currentSettings['logo_path']) && file_exists(__DIR__ . '/uploads/' . $currentSettings['logo_path'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($currentSettings['logo_path']); ?>" style="max-width: 100%; max-height: 100%;">
                            <?php else: ?>
                                No logo uploaded
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
                        <div class="sa-card-subtitle">Set platform-wide tenant constraints</div>
                    </div>
                </div>
                <div class="sa-form-grid">
                    <div class="sa-form-group">
                        <label for="max_tenants">Maximum Active Tenants</label>
                        <input type="number" id="max_tenants" name="max_tenants" value="<?php echo htmlspecialchars($currentSettings['max_tenants'] ?? ''); ?>" placeholder="Leave empty for unlimited">
                    </div>
                    <div class="sa-form-group">
                        <label for="max_users_per_tenant">Maximum Users per Tenant</label>
                        <input type="number" id="max_users_per_tenant" name="max_users_per_tenant" value="<?php echo htmlspecialchars($currentSettings['max_users_per_tenant'] ?? ''); ?>" placeholder="Leave empty for unlimited">
                    </div>
                    <div class="sa-form-group">
                        <label for="storage_limit">Storage Limit per Tenant (GB)</label>
                        <input type="number" id="storage_limit" name="storage_limit" value="<?php echo htmlspecialchars($currentSettings['storage_limit'] ?? ''); ?>" placeholder="Leave empty for unlimited">
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
                            <td>Owner (Admin)</td>
                            <td>Full clinic management and administration</td>
                            <td>
                                <div class="permissions-grid">
                                    <div class="permission-item">
                                        <input type="checkbox" checked disabled> Appointments
                                    </div>
                                    <div class="permission-item">
                                        <input type="checkbox" checked disabled> Patients
                                    </div>
                                    <div class="permission-item">
                                        <input type="checkbox" checked disabled> Billing
                                    </div>
                                    <div class="permission-item">
                                        <input type="checkbox" checked disabled> Staff Management
                                    </div>
                                    <div class="permission-item">
                                        <input type="checkbox" checked disabled> Reports
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Front Desk (Receptionist)</td>
                            <td>Appointment scheduling and patient coordination</td>
                            <td>
                                <div class="permissions-grid">
                                    <div class="permission-item">
                                        <input type="checkbox" checked disabled> Appointments
                                    </div>
                                    <div class="permission-item">
                                        <input type="checkbox" checked disabled> Patients
                                    </div>
                                    <div class="permission-item">
                                        <input type="checkbox" disabled> Billing
                                    </div>
                                    <div class="permission-item">
                                        <input type="checkbox" disabled> Staff Management
                                    </div>
                                    <div class="permission-item">
                                        <input type="checkbox" disabled> Reports
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Dentist</td>
                            <td>Clinical records and patient treatment</td>
                            <td>
                                <div class="permissions-grid">
                                    <div class="permission-item">
                                        <input type="checkbox" checked disabled> Appointments
                                    </div>
                                    <div class="permission-item">
                                        <input type="checkbox" checked disabled> Patients
                                    </div>
                                    <div class="permission-item">
                                        <input type="checkbox" checked disabled> Clinical Notes
                                    </div>
                                    <div class="permission-item">
                                        <input type="checkbox" disabled> Billing
                                    </div>
                                    <div class="permission-item">
                                        <input type="checkbox" disabled> Staff Management
                                    </div>
                                    <div class="permission-item">
                                        <input type="checkbox" disabled> Reports
                                    </div>
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
        // Logo preview
        document.getElementById('logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('logo-preview').innerHTML = '<img src="' + e.target.result + '" style="max-width: 100%; max-height: 100%;">';
                };
                reader.readAsDataURL(file);
            }
        });

        function resetSettings() {
            if (confirm('Are you sure you want to reset all settings to defaults?')) {
                // Reset form
                document.querySelector('form').reset();
                document.getElementById('logo-preview').innerHTML = 'No logo uploaded';
            }
        }
    </script>
</body>
</html>