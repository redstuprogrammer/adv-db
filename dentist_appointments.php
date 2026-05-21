<?php
/**
 * ============================================
 * DENTIST APPOINTMENTS - ENHANCED WITH FILTERING & SERVICE DETAILS
 * Last Updated: April 4, 2026
 * Features: Appointment Cards, Service Filtering, Time Display, Clinical Access
 * ✓ FLAG TEST: Dentist appointments successfully updated for Azure
 * ============================================
 */

require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function baseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('dentist');

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
$tenantData = $sessionManager->getTenantData();
$tenantName = $tenantData['tenant_name'] ?? '';
$tenantId = $sessionManager->getTenantId();
$dentistId = $sessionManager->getUserId();
$dentistName = $sessionManager->getUsername() ?? 'Dentist';

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$today = date('Y-m-d');

// Pagination Setup
$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$totalAppointments = 0;
$countQuery = "SELECT COUNT(*) AS total FROM appointment a WHERE a.tenant_id = ? AND a.dentist_id = ? AND a.status <> 'Disapproved'";
if ($filter == 'today') $countQuery .= " AND a.appointment_date = ?";
elseif ($filter == 'upcoming') $countQuery .= " AND a.appointment_date > ?";

$countStmt = $conn->prepare($countQuery);
if ($countStmt) {
    if ($filter == 'today' || $filter == 'upcoming') {
        $countStmt->bind_param("iis", $tenantId, $dentistId, $today);
    } else {
        $countStmt->bind_param("ii", $tenantId, $dentistId);
    }
    $countStmt->execute();
    $countRes = $countStmt->get_result();
    if ($countRow = $countRes->fetch_assoc()) {
        $totalAppointments = (int)$countRow['total'];
    }
    $countStmt->close();
}

// Query Logic
$query = "SELECT a.appointment_id, p.patient_id, p.first_name, p.last_name, 
                 COALESCE(s.service_name, 'No Service Specified') AS service_name, 
                 a.appointment_date, a.appointment_time, a.status 
          FROM appointment a
          LEFT JOIN patient p ON a.patient_id = p.patient_id AND p.tenant_id = a.tenant_id
          LEFT JOIN service s ON a.service_id = s.service_id AND s.tenant_id = a.tenant_id
          WHERE a.tenant_id = ? AND a.dentist_id = ? AND a.status <> 'Disapproved'";

if ($filter == 'today') $query .= " AND a.appointment_date = ?";
elseif ($filter == 'upcoming') $query .= " AND a.appointment_date > ?";

$query .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT ?, ?";

$result = null;
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    if ($filter == 'today' || $filter == 'upcoming') {
        mysqli_stmt_bind_param($stmt, "iisii", $tenantId, $dentistId, $today, $offset, $perPage);
    } else {
        mysqli_stmt_bind_param($stmt, "iiii", $tenantId, $dentistId, $offset, $perPage);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

$successMessage = '';
$errorMessage = '';
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Dentist Appointments</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
      :root {
        --dashboard-accent: #0d3b66;
        --dashboard-success: #10b981;
        --dashboard-warning: #f59e0b;
        --dashboard-danger: #ef4444;
        --dashboard-border: #e2e8f0;
        --dashboard-bg: #f8fafc;
      }

      .content-section {
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 32px;
      }

      .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
      }

      .content-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--dashboard-accent);
      }

      .action-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        gap: 20px;
      }

      .search-box {
        padding: 12px 20px;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        width: 100%;
        max-width: 400px;
        font-size: 14px;
      }

      .filter-tabs {
        display: flex;
        background: var(--dashboard-bg);
        padding: 5px;
        border-radius: 12px;
        gap: 5px;
      }

      .tab {
        padding: 8px 20px;
        border-radius: 8px;
        text-decoration: none;
        color: #64748b;
        font-weight: 600;
        font-size: 14px;
        transition: 0.3s;
      }

      .tab.active {
        background: var(--dashboard-accent);
        color: white;
      }

      .module-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
      }

      .module-table th {
        background: var(--dashboard-bg);
        border-bottom: 2px solid var(--dashboard-border);
        padding: 12px;
        text-align: left;
        font-weight: 700;
        color: var(--dashboard-accent);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .module-table td {
        padding: 12px;
        border-bottom: 1px solid var(--dashboard-border);
        font-size: 14px;
      }

      .module-table tbody tr:hover {
        background: var(--dashboard-bg);
      }

      .status-pill {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
        display: inline-block;
      }

      .status-pill.pending { background: #fffbeb; color: #92400e; }
      .status-pill.completed { background: #f0fdf4; color: #166534; }
      .status-pill.cancelled { background: #fef2f2; color: #991b1b; }
      .status-pill.in-progress { background: #e0f2fe; color: #0369a1; }
      .status-pill.approved { background: #dcfce7; color: #166534; }

      .btn-treatment {
        background: white;
        color: var(--dashboard-accent);
        border: 1px solid var(--dashboard-accent);
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 12px;
        cursor: pointer;
        transition: 0.2s;
        text-decoration: none;
        display: inline-block;
      }

      .btn-treatment:hover {
        background: var(--dashboard-accent);
        color: white;
      }

      .live-clock-badge {
        background: linear-gradient(135deg, rgba(13, 59, 102, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
        border: 2px solid var(--dashboard-accent);
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 16px;
        font-weight: 700;
        color: var(--dashboard-accent);
        font-family: 'Courier New', monospace;
        letter-spacing: 1px;
        white-space: nowrap;
      }

      .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.5);
        align-items: center;
        justify-content: center;
        padding: 20px;
        z-index: 1100;
      }

      .modal.active {
        display: flex;
      }

      .modal-content {
        background: white;
        border-radius: 14px;
        padding: 24px;
        width: 100%;
        max-width: 450px;
        box-shadow: 0 20px 25px rgba(15, 23, 42, 0.15);
      }

      .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
      }

      .modal-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--dashboard-accent);
      }

      .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #64748b;
      }

      .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 16px;
      }

      .form-group label {
        font-weight: 600;
        color: #334155;
        font-size: 13px;
      }

      .form-group input,
      .form-group select {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid var(--dashboard-border);
        border-radius: 10px;
        font-size: 14px;
      }

      .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 20px;
      }

      .modal-actions button {
        padding: 10px 16px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
      }

      .btn-secondary {
        background: #f1f5f9;
        color: #334155;
      }

      .btn-primary-modal {
        background: var(--dashboard-accent);
        color: white;
      }

      .btn-manage {
        background: white;
        color: var(--dashboard-accent);
        border: 1px solid var(--dashboard-accent);
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        transition: 0.2s;
      }

      .btn-manage:hover {
        background: var(--dashboard-accent);
        color: white;
      }

      .alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <!-- Header Bar -->
      <div class="tenant-header-bar">
        <div class="tenant-header-title">Appointments</div>
        <?php renderDateClock(); ?>
      </div>

      <!-- Content -->
      <div class="content-section">
        <div class="content-header">
          <div>
            <h2 class="content-title">My Appointments</h2>
            <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">
              <?php
                $count = $result ? $result->num_rows : 0;
                echo $count . ' appointment' . ($count !== 1 ? 's' : '') . ' found';
                if ($filter !== 'all') echo ' &mdash; filter: <strong>' . h($filter) . '</strong>';
              ?>
            </p>
          </div>
        </div>

        <?php if ($successMessage): ?>
          <div class="alert-box" style="background: #ecfdf5; color: #16573b; border: 1px solid #bbf7d0;"><?php echo h($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
          <div class="alert-box" style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;"><?php echo h($errorMessage); ?></div>
        <?php endif; ?>

        <div class="action-bar">
          <input type="text" id="apptSearch" class="search-box" placeholder="🔍 Search patient name...">
          
          <div class="filter-tabs">
            <a href="?tenant=<?php echo rawurlencode($tenantSlug); ?>&filter=all"      class="tab <?php echo $filter == 'all'      ? 'active' : ''; ?>">All</a>
            <a href="?tenant=<?php echo rawurlencode($tenantSlug); ?>&filter=today"    class="tab <?php echo $filter == 'today'    ? 'active' : ''; ?>">Today</a>
            <a href="?tenant=<?php echo rawurlencode($tenantSlug); ?>&filter=upcoming" class="tab <?php echo $filter == 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
          </div>
        </div>

        <div class="appt-list-container" id="apptList">
          <table class="module-table" id="appointmentTable">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Patient</th>
                <th>Service</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()):
                  $statusClass = strtolower(str_replace(' ', '-', $row['status'] ?? 'pending'));
                  $dateFormatted = date('M d, Y', strtotime($row['appointment_date']));
                  $timeFormatted = !empty($row['appointment_time']) ? date('g:i A', strtotime($row['appointment_time'])) : '-';
                  $patientName = h(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                  $serviceName = h($row['service_name'] ?? 'No Service Specified');
                ?>
                  <tr class="appt-row" data-name="<?php echo strtolower($row['first_name'] . ' ' . $row['last_name']); ?>">
                    <td><strong><?php echo $dateFormatted; ?></strong></td>
                    <td><?php echo $timeFormatted; ?></td>
                    <td><?php echo $patientName; ?></td>
                    <td><?php echo $serviceName !== 'No Service Specified' ? '<span class="service-tag">'.$serviceName.'</span>' : $serviceName; ?></td>
                    <td><span class="status-pill <?php echo $statusClass; ?>"><?php echo h(ucwords(strtolower($row['status'] ?? 'pending'))); ?></span></td>
                    <td>
                      <a href="clinical_record.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&id=<?php echo $row['patient_id']; ?>&appt=<?php echo $row['appointment_id']; ?>" class="btn-treatment">Open Clinical Log</a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align: center; color: #64748b; padding: 40px;">No appointments found. Try switching the filter above or check back later.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
          
          <?php if ($totalAppointments > $perPage):
              $lastPage = (int)ceil($totalAppointments / $perPage);
          ?>
            <div style="display:flex; gap:10px; justify-content:center; align-items:center; margin-top:18px;">
              <?php if ($page > 1): ?>
                <a href="?tenant=<?php echo urlencode($tenantSlug); ?>&filter=<?php echo urlencode($filter); ?>&page=<?php echo $page - 1; ?>" class="btn-treatment">Previous</a>
              <?php endif; ?>
              <span style="font-size: 13px; color: #475569;">Page <?php echo $page; ?> of <?php echo $lastPage; ?></span>
              <?php if ($page < $lastPage): ?>
                <a href="?tenant=<?php echo urlencode($tenantSlug); ?>&filter=<?php echo urlencode($filter); ?>&page=<?php echo $page + 1; ?>" class="btn-treatment">Next</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    <?php printDateClockScript(); ?>

    // Verification logs
    console.log('UI Parity Active - Version 2.0');
    console.log('Dentist Appointments Module Active');
    
    document.getElementById('apptSearch').addEventListener('input', function() {
      const q = this.value.toLowerCase();
      document.querySelectorAll('.appt-row').forEach(row => {
        const match = (row.dataset.name || '').includes(q);
        row.style.display = match ? '' : 'none';
      });
    });
  </script>
</body>
</html>

