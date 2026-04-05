<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Location: tenant_login.php');
    exit();
}

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();

$staffMembers = [];
$stmt = mysqli_prepare($conn, "SELECT user_id, username, email, role, date_created FROM users WHERE tenant_id = ? AND role IN ('Dentist', 'Receptionist') ORDER BY username ASC");
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
      }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <main class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">Staff Directory</div>
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
      </div>

      <div class="module-card">
        <div class="staff-grid">
          <?php if (!empty($staffMembers)): ?>
            <?php foreach ($staffMembers as $staff): ?>
              <div class="staff-card">
                <div class="staff-header">
                  <div class="staff-avatar"><?php echo h(substr($staff['username'], 0, 1)); ?></div>
                  <div class="staff-info">
                    <h3><?php echo ($staff['role'] === 'Dentist' ? 'Dr. ' : ''); ?><?php echo h($staff['username']); ?></h3>
                    <p><?php echo h($staff['role']); ?></p>
                  </div>
                </div>

                <div class="staff-details">
                  <div class="detail-item"><span>📧</span> <?php echo h($staff['email']); ?></div>
                  <div class="detail-item"><span>📅</span> Active since <?php echo h(date('M Y', strtotime($staff['date_created'] ?? 'now'))); ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="empty-state">No staff members found.</p>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
