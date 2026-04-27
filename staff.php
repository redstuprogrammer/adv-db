<?php
/**
 * ============================================
 * STAFF DIRECTORY - ADMIN
 * Fetches data from 'staff_details' table as requested.
 * UI consistent with the project's dashboard.
 * ============================================
 */

require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

// Role Check - Ensure user is admin
$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('admin');

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
$tenantName = $sessionManager->getTenantData()['tenant_name'] ?? '';
$tenantId = $sessionManager->getTenantId();

// Fetch staff from 'users' table joined with 'staff_details'
$staffMembers = [];
// We pull from users as the source of truth for clinic members, 
// and left join staff_details for professional info (bio, phone, specialties, image).
$sql = "SELECT 
            u.user_id,
            u.first_name as user_fname,
            u.last_name as user_lname,
            u.email as user_email,
            u.role as user_role,
            sd.staff_id,
            sd.phone,
            sd.specialties,
            sd.profile_image_path,
            sd.status as staff_status,
            sd.is_public_visible,
            COALESCE(sd.first_name, u.first_name) as first_name,
            COALESCE(sd.last_name, u.last_name) as last_name,
            COALESCE(sd.email, u.email) as email,
            COALESCE(sd.role, u.role) as role,
            COALESCE(sd.status, 'Active') as status
        FROM users u
        LEFT JOIN staff_details sd ON u.email = sd.email AND u.tenant_id = sd.tenant_id
        WHERE u.tenant_id = ? 
        AND u.role IN ('Admin', 'Dentist', 'Receptionist', 'Assistant')
        ORDER BY u.last_name ASC, u.first_name ASC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $tenantId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $staffMembers[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Staff Directory</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
      :root {
        --accent: #0d3b66;
        --border: #e2e8f0;
        --bg: #f8fafc;
        --text: #334155;
        --muted: #64748b;
      }

      .staff-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 20px;
      }

      .staff-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 22px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }

      .staff-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
      }

      .staff-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 18px;
      }

      .staff-avatar {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #f1f5f9;
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: 800;
        color: var(--accent);
        overflow: hidden;
      }

      .staff-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      .staff-info h3 {
        margin: 0;
        font-size: 18px;
        color: var(--accent);
      }

      .staff-info p {
        margin: 4px 0 0;
        font-size: 13px;
        color: var(--muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
      }

      .staff-details {
        border-top: 1px solid #f1f5f9;
        padding-top: 14px;
        display: grid;
        gap: 10px;
      }

      .detail-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: var(--text);
      }

      .detail-item span {
        color: var(--muted);
        font-size: 16px;
      }

      .btn-view {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        margin-top: 14px;
        padding: 10px 12px;
        background: #eef2ff;
        color: var(--accent);
        text-decoration: none;
        border-radius: 10px;
        font-weight: 700;
        border: 1px solid #c7d2fe;
        transition: background 0.2s ease, transform 0.2s ease;
      }

      .btn-view:hover {
        background: #e0e7ff;
        transform: translateY(-1px);
      }

      .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: #94a3b8;
        grid-column: 1 / -1;
      }
      
      .status-pill {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
      }
      
      .status-active { background: #dcfce7; color: #166534; }
      .status-inactive { background: #f1f5f9; color: #64748b; }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <main class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">Staff Directory</div>
        <?php renderDateClock(); ?>
      </div>

      <div class="dashboard-card" style="padding: 24px; background: white; border: 1px solid var(--border); border-radius: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 18px; color: var(--accent);">Professional Staff List</h2>
            <a href="users.php<?php echo $baseTenantQuery; ?>" class="btn-view" style="width: auto; margin-top: 0; padding: 8px 16px;">Manage System Users</a>
        </div>

        <div class="staff-grid">
          <?php if (!empty($staffMembers)): ?>
            <?php foreach ($staffMembers as $staff): ?>
              <div class="staff-card">
                <div class="staff-header">
                  <div class="staff-avatar">
                    <?php if (!empty($staff['profile_image_path'])): ?>
                        <img src="<?php echo h($staff['profile_image_path']); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo h(substr($staff['first_name'], 0, 1)); ?>
                    <?php endif; ?>
                  </div>
                  <div class="staff-info">
                    <h3><?php echo ($staff['role'] === 'Dentist' ? 'Dr. ' : ''); ?><?php echo h(trim($staff['first_name'] . ' ' . $staff['last_name'])); ?></h3>
                    <p><?php echo h($staff['role']); ?></p>
                  </div>
                </div>

                <div class="staff-details">
                  <div class="detail-item"><span>📧</span> <?php echo h($staff['email']); ?></div>
                  <div class="detail-item"><span>📞</span> <?php echo h($staff['phone'] ?? 'N/A'); ?></div>
                  <div class="detail-item">
                    <span>🛡️</span> 
                    <span class="status-pill <?php echo (strtolower($staff['status']) === 'active') ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo h($staff['status']); ?>
                    </span>
                  </div>
                  <a href="view_staff_profile.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&id=<?php echo $staff['staff_id'] ?? 0; ?>&uid=<?php echo $staff['user_id']; ?>" class="btn-view">View Professional Profile</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 16px;">👨‍⚕️</div>
                <h3>No Staff Records Found</h3>
                <p>Staff members listed here come from the professional details table. You can add them via the database or a registration module.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <script>
    <?php printDateClockScript(); ?>
  </script>
</body>
</html>
