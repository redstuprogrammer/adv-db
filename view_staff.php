<?php
/**
 * ============================================
 * STAFF PROFILE VIEW - ADMIN
 * Last Updated: April 6, 2026
 * ============================================
 */

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin' || $_SESSION['tenant_slug'] !== $tenantSlug) {
    header("Location: tenant_login.php?tenant=" . rawurlencode($tenantSlug));
    exit();
}

requireTenantLogin($tenantSlug);
$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$user_id) {
    die("Invalid Staff ID.");
}

// Fetch staff details
$stmt = $conn->prepare("SELECT u.user_id, u.username, u.email, u.role, u.first_name, u.last_name
                       FROM users u 
                       WHERE u.user_id = ? AND u.tenant_id = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("Database error: Unable to fetch staff details.");
}
$stmt->bind_param('ii', $user_id, $tenantId);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("Database error: " . $stmt->error);
}
$result = $stmt->get_result();
$staff = $result->fetch_assoc();
$stmt->close();

// If dentist record doesn't exist, provide defaults
if ($staff && !isset($staff['primary_specialization'])) {
    $staff['primary_specialization'] = 'General Practitioner';
}

if (!$staff) {
    die("Staff member not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($staff['username']); ?> - Staff Profile</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
        :root {
            --primary: #0d3b66;
            --bg-light: #f8fafc;
            --border: #e2e8f0;
            --text-main: #334155;
            --text-muted: #64748b;
        }

        body { background-color: var(--bg-light); }

        .profile-container { max-width: 600px; margin: 40px auto; padding: 0 20px; }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .profile-header h1 { margin: 0; color: var(--primary); font-size: 28px; }

        .back-link {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
        }

        .back-link:hover { color: var(--primary); }

        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .profile-card-header {
            background: linear-gradient(135deg, var(--primary) 0%, #002855 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .avatar {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 800;
            margin: 0 auto 15px;
            border: 3px solid rgba(255,255,255,0.3);
        }

        .staff-name {
            font-size: 24px;
            font-weight: 800;
            margin: 10px 0 5px;
        }

        .staff-role {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }

        .profile-body { padding: 30px; }

        .info-section {
            margin-bottom: 25px;
        }

        .info-section label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-section p {
            margin: 0;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover { background: #002855; }

        .btn-secondary {
            background: #f1f5f9;
            color: var(--text-muted);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover { background: #e2e8f0; }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            background: #dcfce7;
            color: #166534;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
        }    </style>
</head>
<body>

<div class="profile-container">
    <div class="profile-header">
        <h1>Staff Profile</h1>
        <a href="staff.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="back-link">← Back to Staff</a>
    </div>

    <div class="profile-card">
        <div class="profile-card-header">
            <div class="avatar" style="background: rgba(255,255,255,0.2);">
                <?php echo strtoupper(substr($staff['first_name'] ?? 'S', 0, 1) . substr($staff['last_name'] ?? 'M', 0, 1)); ?>
            </div>
            <div class="staff-name">
                <?php echo h(($staff['first_name'] ?? 'Staff') . ' ' . ($staff['last_name'] ?? 'Member')); ?>
            </div>
            <div class="staff-role"><?php echo h($staff['role']); ?></div>
            <div style="margin-top: 12px;" class="status-badge">Active</div>
        </div>

        <div class="profile-body">
            <div class="info-grid">
                <div class="info-section">
                    <label>Username</label>
                    <p><?php echo h($staff['username']); ?></p>
                </div>
                <div class="info-section">
                    <label>Role</label>
                    <p><?php echo h($staff['role']); ?></p>
                </div>
                <div class="info-section">
                    <label>Email</label>
                    <p><?php echo h($staff['email']); ?></p>
                </div>
                <div class="info-section">
                    <label>User ID</label>
                    <p>#<?php echo $user_id; ?></p>
                </div>
            </div>

            <div class="action-buttons">
                <!-- Back button removed as requested -->
            </div>
        </div>
    </div>
</div>

</body>
</html>
