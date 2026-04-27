<?php
/**
 * ============================================
 * STAFF PROFESSIONAL PROFILE VIEW - ADMIN
 * Fetches data from 'staff_details' table.
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

$staff_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$staff_id) {
    header("Location: staff.php?tenant=" . rawurlencode($tenantSlug));
    exit();
}

// Fetch staff from 'staff_details' table
$staff = null;
$stmt = mysqli_prepare($conn, "SELECT * FROM staff_details WHERE staff_id = ? AND tenant_id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $staff_id, $tenantId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        $staff = mysqli_fetch_assoc($result);
    }
    mysqli_stmt_close($stmt);
}

if (!$staff) {
    die("Staff member not found or access denied.");
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($staff['first_name'] . ' ' . $staff['last_name']); ?> | Staff Profile</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
      :root {
        --accent: #0d3b66;
        --border: #e2e8f0;
        --bg: #f8fafc;
        --text: #334155;
        --muted: #64748b;
        --success: #10b981;
      }

      .profile-container {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 24px;
        margin-top: 20px;
      }

      .card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
      }

      .profile-avatar-large {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: #f1f5f9;
        border: 2px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        font-weight: 800;
        color: var(--accent);
        margin: 0 auto 20px;
        overflow: hidden;
      }

      .profile-avatar-large img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      .profile-name-tag {
        text-align: center;
        margin-bottom: 24px;
      }

      .profile-name-tag h2 {
        margin: 0;
        font-size: 22px;
        color: var(--accent);
      }

      .profile-name-tag span {
        font-size: 12px;
        font-weight: 700;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 1px;
      }

      .info-group {
        margin-bottom: 20px;
      }

      .info-label {
        display: block;
        font-size: 11px;
        font-weight: 800;
        color: var(--muted);
        text-transform: uppercase;
        margin-bottom: 6px;
      }

      .info-value {
        font-size: 14px;
        color: var(--text);
        font-weight: 500;
      }

      .section-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--accent);
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 10px;
      }

      .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
      }

      .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
      }

      .status-active { background: #dcfce7; color: #166534; }
      .status-inactive { background: #f1f5f9; color: #64748b; }

      .btn-action {
        padding: 10px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
      }

      .btn-edit { background: var(--accent); color: white; }
      .btn-edit:hover { background: #002855; }
      
      .btn-back { color: var(--muted); }
      .btn-back:hover { color: var(--accent); }

      @media (max-width: 768px) {
        .profile-container {
          grid-template-columns: 1fr;
        }
      }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <main class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">Professional Profile</div>
        <?php renderDateClock(); ?>
      </div>

      <a href="staff.php<?php echo $baseTenantQuery; ?>" class="btn-action btn-back">← Return to Directory</a>

      <div class="profile-container">
        <!-- Sidebar Card -->
        <div class="card">
          <div class="profile-avatar-large">
            <?php if (!empty($staff['profile_image_path'])): ?>
                <img src="<?php echo h($staff['profile_image_path']); ?>" alt="Profile">
            <?php else: ?>
                <?php echo h(substr($staff['first_name'], 0, 1)); ?>
            <?php endif; ?>
          </div>
          <div class="profile-name-tag">
            <h2><?php echo ($staff['role'] === 'Dentist' ? 'Dr. ' : ''); ?><?php echo h($staff['first_name'] . ' ' . $staff['last_name']); ?></h2>
            <span><?php echo h($staff['role']); ?></span>
          </div>

          <div class="info-group">
            <span class="info-label">Email Address</span>
            <span class="info-value"><?php echo h($staff['email']); ?></span>
          </div>
          <div class="info-group">
            <span class="info-label">Contact Phone</span>
            <span class="info-value"><?php echo h($staff['phone'] ?? 'Not Provided'); ?></span>
          </div>
          <div class="info-group">
            <span class="info-label">Status</span>
            <span class="status-badge <?php echo (strtolower($staff['status']) === 'active') ? 'status-active' : 'status-inactive'; ?>">
              <?php echo h($staff['status']); ?>
            </span>
          </div>
        </div>

        <!-- Main Content Card -->
        <div class="card">
          <div class="section-title">
            <span>Professional Credentials</span>
            <a href="edit_staff_details.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&id=<?php echo $staff['staff_id']; ?>" class="btn-action btn-edit">Update Details</a>
          </div>

          <div class="grid-2">
            <div class="info-group">
              <span class="info-label">Primary Specialties</span>
              <span class="info-value"><?php echo h($staff['specialties'] ?? 'General Practitioner'); ?></span>
            </div>
            <div class="info-group">
              <span class="info-label">Hired Date</span>
              <span class="info-value"><?php echo !empty($staff['hired_date']) ? date('F d, Y', strtotime($staff['hired_date'])) : 'Not recorded'; ?></span>
            </div>
            <div class="info-group">
              <span class="info-label">Public Visibility</span>
              <span class="info-value"><?php echo $staff['is_public_visible'] ? 'Visible on Website' : 'Hidden from Public'; ?></span>
            </div>
          </div>

          <div class="info-group" style="margin-top: 20px;">
            <span class="info-label">Professional Biography</span>
            <div class="info-value" style="line-height: 1.6; color: var(--text); background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #edf2f7;">
              <?php echo nl2br(h($staff['public_bio'] ?? 'No biography provided yet.')); ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    <?php printDateClockScript(); ?>
  </script>
</body>
</html>
