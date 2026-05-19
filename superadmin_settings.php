<?php
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/session_utils.php';

$sessionManager = SessionManager::getInstance();
$sessionManager->requireSuperAdmin();

// Self-healing database check: ensure announcements table exists and has updated enum categories
$conn->query("CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` varchar(255) DEFAULT 'General',
  `image_path` varchar(511) DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active',
  `publish_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_tenant_announcement` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("ALTER TABLE announcements MODIFY COLUMN tenant_id int NULL");
$conn->query("ALTER TABLE announcements MODIFY COLUMN category varchar(255) DEFAULT 'General'");

// Handle Super Admin Announcements CRUD
$annMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement_action'])) {
    $action = trim($_POST['announcement_action']);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category = trim($_POST['category'] ?? 'System Announcement');
    $publishDate = trim($_POST['publish_date'] ?? date('Y-m-d'));
    $status = trim($_POST['status'] ?? 'active');
    $annId = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;

    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO announcements (tenant_id, title, content, category, publish_date, status) VALUES (NULL, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('sssss', $title, $content, $category, $publishDate, $status);
            if ($stmt->execute()) {
                $annMessage = 'System Announcement published successfully!';
            } else {
                $annMessage = 'Error publishing system announcement.';
            }
            $stmt->close();
        }
    } elseif ($action === 'edit' && $annId > 0) {
        $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, category = ?, publish_date = ?, status = ? WHERE id = ? AND tenant_id IS NULL");
        if ($stmt) {
            $stmt->bind_param('sssssi', $title, $content, $category, $publishDate, $status, $annId);
            if ($stmt->execute()) {
                $annMessage = 'System Announcement updated successfully!';
            } else {
                $annMessage = 'Error updating system announcement.';
            }
            $stmt->close();
        }
    } elseif ($action === 'delete' && $annId > 0) {
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND tenant_id IS NULL");
        if ($stmt) {
            $stmt->bind_param('i', $annId);
            if ($stmt->execute()) {
                $annMessage = 'System Announcement deleted successfully!';
            } else {
                $annMessage = 'Error deleting system announcement.';
            }
            $stmt->close();
        }
    }
}

// Load current settings
require_once __DIR__ . '/settings.php';
try {
    $currentSettings = getAllSettings();
} catch (Exception $e) {
    $currentSettings = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['announcement_action'])) {
    require_once __DIR__ . '/settings.php';

    try {
        if (isset($_POST['reset'])) {
            // Reset to defaults
            setSetting('system_name', 'OralSync');
            
            // Delete existing logo file if exists
            $currentLogo = $currentSettings['logo_path'] ?? '';
            if ($currentLogo && file_exists(__DIR__ . '/' . ltrim($currentLogo, '/'))) {
                @unlink(__DIR__ . '/' . ltrim($currentLogo, '/'));
            }
            setSetting('logo_path', '');

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' || isset($_POST['reset'])) {
                // If it's the fetch request from JS
                if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                     echo json_encode(['success' => true]);
                     exit;
                }
            }

            $message = "Settings reset to defaults!";
        }
 else {
            // Save settings
            setSetting('system_name', $_POST['system_name'] ?? 'OralSync');



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
        }
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

        /* System Announcements premium CSS overrides */
        .sa-ann-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            background: #ffe4e6;
            color: #be123c;
        }
        .sa-ann-badge.system-announcement { background: #ffe4e6; color: #be123c; }
        .sa-ann-badge.clinical-update { background: #e0f2fe; color: #0369a1; }
        .sa-ann-badge.patient-care { background: #dcfce7; color: #166534; }
        .sa-ann-badge.facility-news { background: #fef3c7; color: #d97706; }
        .sa-ann-badge.staff-training { background: #f3e8ff; color: #7e22ce; }

        .sa-status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .sa-status-badge.active { background: #dcfce7; color: #166534; }
        .sa-status-badge.archived { background: #f1f5f9; color: #64748b; }

        .sa-ann-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .sa-ann-table th {
            text-align: left;
            padding: 12px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 12px;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .sa-ann-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            vertical-align: middle;
        }
        .sa-ann-btn {
            background: var(--sa-primary);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .sa-ann-btn:hover {
            background: #0a2f52;
        }
        .sa-ann-btn-danger {
            background: #ef4444;
            color: white;
        }
        .sa-ann-btn-danger:hover {
            background: #b91c1c;
        }
        .sa-ann-btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        .sa-ann-btn-secondary:hover {
            background: #d1d5db;
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
                            <?php if (!empty($currentSettings['logo_path']) && file_exists(__DIR__ . $currentSettings['logo_path'])): ?>
                                <img src="<?php echo htmlspecialchars($currentSettings['logo_path']); ?>?t=<?php echo time(); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;" id="logo-img">
                            <?php else: ?>
                                <span id="logo-placeholder" style="font-size: 48px;">🏥</span>
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
                                <th>Payment Tracking</th>
                                <th>Basic Reporting</th>
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
                                    <td>" . (($tier['features']['payment_tracking'] ?? false) ? '✓' : '✗') . "</td>
                                    <td>" . (($tier['features']['basic_reporting'] ?? false) ? '✓' : '✗') . "</td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
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
                <button type="button" class="sa-btn sa-btn-outline" onclick="resetSettings()">Reset to Default</button>
            </div>
        </form>

        <!-- System Announcements Section -->
        <div class="sa-card settings-section" style="margin-top: 30px;">
            <div class="sa-card-header" style="border-bottom: 1.5px solid var(--sa-border); padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap;">
                <div>
                    <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--sa-primary); display: flex; align-items: center; gap: 8px;">
                        <span>📢</span> System Announcements
                    </h3>
                    <div class="sa-card-subtitle" style="margin-top: 4px;">Publish system-wide notifications (e.g. maintenance, platform-wide alerts) shown to all clinics and their staff on their dashboards</div>
                </div>
                <button type="button" class="sa-btn" onclick="showAddAnnouncementForm()">+ Publish Announcement</button>
            </div>

            <?php if (!empty($annMessage)): ?>
                <div style="background: #e0f2fe; color: #0369a1; padding: 12px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; margin-bottom: 20px; border-left: 4px solid #0284c7;">
                    <?php echo htmlspecialchars($annMessage); ?>
                </div>
            <?php endif; ?>

            <!-- Announcement Form Section (hidden by default) -->
            <div id="announcementFormSection" style="display:none; border: 1.5px solid var(--sa-border); border-radius: 8px; padding: 20px; margin-bottom: 20px; background: #f8fafc;">
                <h3 id="formTitle" style="margin-top:0; color: var(--sa-primary); font-size: 1rem; font-weight: 600;">Publish System Announcement</h3>
                <form method="POST" id="annForm">
                    <input type="hidden" name="announcement_action" id="announcementAction" value="add">
                    <input type="hidden" name="announcement_id" id="announcementId" value="">
                    
                    <div class="sa-form-group" style="margin-bottom: 15px;">
                        <label for="ann_title" style="font-weight:600; margin-bottom:6px;">Title</label>
                        <input type="text" id="ann_title" name="title" required style="width:100%; box-sizing:border-box;">
                    </div>
                    
                    <div class="sa-form-group" style="margin-bottom: 15px;">
                        <label for="ann_category" style="font-weight:600; margin-bottom:6px;">Category / Tag</label>
                        <input type="text" id="ann_category" name="category" required placeholder="e.g., System Announcement, Maintenance, Platform Alert" style="width:100%; box-sizing:border-box;">
                    </div>
                    
                    <div class="sa-form-group" style="margin-bottom: 15px;">
                        <label for="ann_content" style="font-weight:600; margin-bottom:6px;">Content / Details</label>
                        <textarea id="ann_content" name="content" required style="width:100%; height:120px; padding:10px; border: 1px solid var(--sa-border); border-radius: 6px; font-family:inherit; font-size:14px; box-sizing:border-box; background:white; resize:vertical;"></textarea>
                    </div>
                    
                    <div class="sa-form-group" style="margin-bottom: 15px;">
                        <label for="ann_publish_date" style="font-weight:600; margin-bottom:6px;">Publish Date</label>
                        <input type="date" id="ann_publish_date" name="publish_date" required value="<?php echo date('Y-m-d'); ?>" style="width:100%; box-sizing:border-box;">
                    </div>
                    
                    <div class="sa-form-group" style="margin-bottom: 15px;">
                        <label for="ann_status" style="font-weight:600; margin-bottom:6px;">Status</label>
                        <select id="ann_status" name="status" style="width:100%; box-sizing:border-box; height:38px;">
                            <option value="active">Active</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    
                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" class="sa-ann-btn">Save Announcement</button>
                        <button type="button" class="sa-ann-btn sa-ann-btn-secondary" onclick="hideAnnouncementForm()">Cancel</button>
                    </div>
                </form>
            </div>
            
            <!-- Announcements Table/List -->
            <?php
            $systemAnnouncements = [];
            $stmt = $conn->prepare("SELECT * FROM announcements WHERE tenant_id IS NULL ORDER BY publish_date DESC, id DESC");
            if ($stmt) {
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $systemAnnouncements[] = $row;
                    }
                }
                $stmt->close();
            }
            ?>

            <?php if (empty($systemAnnouncements)): ?>
                <div style="text-align:center; padding: 40px 20px; border: 1px dashed var(--sa-border); border-radius: 8px; background: #fafbfc;">
                    <p style="color:var(--sa-muted); margin:0;">No system announcements published yet. Click "Publish Announcement" to create one.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="sa-ann-table">
                        <thead>
                            <tr>
                                <th style="width: 180px;">Category</th>
                                <th>Title & Details</th>
                                <th style="width: 130px;">Published</th>
                                <th style="width: 100px;">Status</th>
                                <th style="text-align:right; width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($systemAnnouncements as $ann): ?>
                                <tr>
                                    <td>
                                        <span class="sa-ann-badge <?php echo str_replace(' ', '-', strtolower($ann['category'])); ?>">
                                            <?php echo htmlspecialchars($ann['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color:var(--sa-primary); font-size:15px;"><?php echo htmlspecialchars($ann['title']); ?></strong>
                                        <p style="margin: 6px 0 0 0; color:#475569; font-size:13px; line-height:1.4; white-space:pre-line;"><?php echo htmlspecialchars($ann['content']); ?></p>
                                    </td>
                                    <td>
                                        <span style="color:#64748b; font-size:13px; font-weight:500;"><?php echo date('M d, Y', strtotime($ann['publish_date'])); ?></span>
                                    </td>
                                    <td>
                                        <span class="sa-status-badge <?php echo htmlspecialchars($ann['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($ann['status'])); ?>
                                        </span>
                                    </td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <button type="button" class="sa-ann-btn" onclick='showEditAnnouncementForm(<?php echo json_encode($ann, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Edit</button>
                                        <button type="button" class="sa-ann-btn sa-ann-btn-danger" onclick="confirmDeleteAnnouncement(<?php echo (int)$ann['id']; ?>)">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
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
    </script>

    <!-- Reset Confirmation Modal -->
    <style>
        .reset-modal {
            display: none;
            position: fixed;
            z-index: 1100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            animation: saFadeIn 0.3s ease;
        }

        .reset-modal-content {
            background: white;
            margin: 0;
            padding: 0;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 450px;
            animation: saModalSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
        }

        @keyframes saFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes saModalSlideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .reset-modal-header {
            background: linear-gradient(135deg, #0d3b66, #0a2f52);
            color: white;
            padding: 20px;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .reset-modal-body {
            padding: 24px;
            color: #374151;
            font-size: 14px;
            line-height: 1.6;
        }

        .reset-modal-footer {
            padding: 20px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #f9fafb;
        }

        .reset-modal-footer button {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .btn-confirm-sa {
            background: #0d3b66;
            color: white;
        }

        .btn-confirm-sa:hover {
            background: #0a2f52;
        }

        .btn-cancel-sa {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-cancel-sa:hover {
            background: #d1d5db;
        }

        .reset-modal-close {
            color: white;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
        }
    </style>

    <div id="resetConfirmModal" class="reset-modal">
        <div class="reset-modal-content">
            <div class="reset-modal-header">
                <span>Reset to Default Settings</span>
                <button class="reset-modal-close" onclick="closeResetModal()">&times;</button>
            </div>
            <div class="reset-modal-body">
                <p>Are you sure you want to reset all system settings to their default values?</p>
                <p>This will restore the system name and remove the current logo.</p>
            </div>
            <div class="reset-modal-footer">
                <button class="btn-cancel-sa" onclick="closeResetModal()">Cancel</button>
                <button class="btn-confirm-sa" onclick="confirmReset()">Reset Settings</button>
            </div>
        </div>
    </div>

    <script>
        function openResetModal() {
            document.getElementById('resetConfirmModal').style.display = 'flex';
        }

        function closeResetModal() {
            document.getElementById('resetConfirmModal').style.display = 'none';
        }

        function resetSettings() {
            openResetModal();
        }

        function confirmReset() {
            closeResetModal();
            // Send reset request
            const formData = new FormData();
            formData.append('reset', 'true');
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Update UI to show defaults
                document.getElementById('system_name').value = 'OralSync';
                document.getElementById('logo-preview').innerHTML = '<span id="logo-placeholder" style="font-size: 48px;">🏥</span>';

                // Clear file input
                document.getElementById('logo').value = '';
                // Reload to reflect changes
                setTimeout(() => {
                    location.reload();
                }, 500);
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('resetConfirmModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Announcement Management Functions
        function showAddAnnouncementForm() {
            document.getElementById('announcementFormSection').style.display = 'block';
            document.getElementById('formTitle').innerText = 'Publish System Announcement';
            document.getElementById('announcementAction').value = 'add';
            document.getElementById('announcementId').value = '';
            document.getElementById('ann_title').value = '';
            document.getElementById('ann_category').value = 'System Announcement';
            document.getElementById('ann_content').value = '';
            document.getElementById('ann_publish_date').value = new Date().toISOString().split('T')[0];
            document.getElementById('ann_status').value = 'active';
            document.getElementById('announcementFormSection').scrollIntoView({ behavior: 'smooth' });
        }

        function showEditAnnouncementForm(ann) {
            document.getElementById('announcementFormSection').style.display = 'block';
            document.getElementById('formTitle').innerText = 'Edit System Announcement';
            document.getElementById('announcementAction').value = 'edit';
            document.getElementById('announcementId').value = ann.id;
            document.getElementById('ann_title').value = ann.title;
            document.getElementById('ann_category').value = ann.category;
            document.getElementById('ann_content').value = ann.content;
            document.getElementById('ann_publish_date').value = ann.publish_date;
            document.getElementById('ann_status').value = ann.status;
            document.getElementById('announcementFormSection').scrollIntoView({ behavior: 'smooth' });
        }

        function hideAnnouncementForm() {
            document.getElementById('announcementFormSection').style.display = 'none';
        }

        function confirmDeleteAnnouncement(id) {
            if (confirm('Are you sure you want to delete this system announcement? This will remove it from all clinic dashboards immediately.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="announcement_action" value="delete">
                    <input type="hidden" name="announcement_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    </main>
</body>
</html>
