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

$query .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$result = null;
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    if ($filter == 'today' || $filter == 'upcoming') {
        mysqli_stmt_bind_param($stmt, "iis", $tenantId, $dentistId, $today);
    } else {
        mysqli_stmt_bind_param($stmt, "ii", $tenantId, $dentistId);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment'])) {
    $appointmentId = isset($_POST['update_id']) ? (int)$_POST['update_id'] : 0;
    $newStatus = trim($_POST['new_status'] ?? '');

    if ($appointmentId > 0 && $newStatus !== '') {
        // Extra safety check: verify current status is not a final state
        $checkStmt = mysqli_prepare($conn, 'SELECT status FROM appointment WHERE appointment_id = ? AND tenant_id = ?');
        mysqli_stmt_bind_param($checkStmt, 'ii', $appointmentId, $tenantId);
        mysqli_stmt_execute($checkStmt);
        $checkRes = mysqli_stmt_get_result($checkStmt);
        $current = mysqli_fetch_assoc($checkRes);
        mysqli_stmt_close($checkStmt);

        if ($current && in_array(strtolower($current['status']), ['completed', 'cancelled'])) {
            $errorMessage = 'This appointment is already ' . strtolower($current['status']) . ' and cannot be modified.';
        } else {
            $stmtUpdate = mysqli_prepare($conn, 'UPDATE appointment SET status = ? WHERE appointment_id = ? AND tenant_id = ?');
            if ($stmtUpdate) {
                mysqli_stmt_bind_param($stmtUpdate, 'sii', $newStatus, $appointmentId, $tenantId);
                if (mysqli_stmt_execute($stmtUpdate)) {
                    $successMessage = 'Appointment status updated successfully.';
                    // Refresh current result to show new status
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                } else {
                    $errorMessage = 'Unable to update appointment status.';
                }
                mysqli_stmt_close($stmtUpdate);
            }
        }
    }
}
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

      .appt-list-container {
        display: flex;
        flex-direction: column;
        gap: 15px;
      }

      .appt-card {
        display: grid;
        grid-template-columns: 100px 1fr auto;
        align-items: center;
        background: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        border-left: 6px solid var(--dashboard-accent);
        transition: transform 0.2s;
      }

      .appt-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.08); }

      .time-badge {
        text-align: center;
        border-right: 1px solid var(--dashboard-border);
        padding-right: 15px;
      }

      .time-badge h4 { margin: 0; color: var(--dashboard-accent); font-size: 16px; }
      .time-badge p { margin: 0; font-size: 12px; color: #64748b; }

      .patient-details { padding-left: 20px; }
      .patient-details h3 { margin: 0 0 5px 0; color: #1e293b; font-size: 18px; }
      .service-tag { 
        background: var(--dashboard-bg); 
        color: #64748b; 
        padding: 3px 10px; 
        border-radius: 5px; 
        font-size: 12px; 
        font-weight: 600; 
      }

      .status-indicator {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        margin-top: 8px;
        display: block;
      }

      .btn-treatment {
        background: var(--dashboard-accent);
        color: white !important;
        text-decoration: none;
        padding: 12px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 13px;
        display: inline-block;
      }

      .hidden-card { display: none !important; }

      /* Status Colors */
      .status-pending { color: #f59e0b; }
      .status-completed { color: #10b981; }
      .status-in-progress { color: #0ea5e9; }

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
        <div class="tenant-header-title"><?php echo h($tenantName); ?> Dentist Portal</div>
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
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()):
              $statusClass = strtolower($row['status'] ?? 'pending');
              $dateFormatted = date('M d', strtotime($row['appointment_date']));
              $yearFormatted = date('Y', strtotime($row['appointment_date']));
              $dayFormatted  = date('D', strtotime($row['appointment_date']));
            ?>
              <div class="appt-card" data-name="<?php echo strtolower($row['first_name'] . ' ' . $row['last_name']); ?>">

                <div class="time-badge">
                  <h4 style="font-size: 14px;"><?php echo $dateFormatted; ?></h4>
                  <p style="font-size: 11px;"><?php echo $dayFormatted; ?></p>
                  <?php if (!empty($row['appointment_time'])): ?>
                    <div style="margin-top: 5px; font-weight: bold; color: var(--dashboard-accent); font-size: 13px;">
                        <?php echo date('g:i A', strtotime($row['appointment_time'])); ?>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="patient-details">
                  <h3><?php echo h(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); ?></h3>
                  <?php if (!empty($row['service_name']) && $row['service_name'] !== 'No Service Specified'): ?>
                    <span class="service-tag"><?php echo h($row['service_name']); ?></span>
                  <?php endif; ?>
                  <span class="status-indicator status-<?php echo str_replace(' ', '-', strtolower($row['status'] ?? 'pending')); ?>">
                    ● <?php echo h(ucwords(strtolower($row['status'] ?? 'pending'))); ?>
                  </span>
                </div>

                <div class="appt-actions" style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
                  <?php 
                    $isFinalStatus = in_array(strtolower($row['status'] ?? ''), ['completed', 'cancelled']);
                    if (!$isFinalStatus): 
                  ?>
                    <button type="button" class="btn-manage" onclick="openManageModal(<?php echo (int)$row['appointment_id']; ?>, <?php echo htmlspecialchars(json_encode(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode('Dr. ' . $dentistName), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)">
                      Manage Status
                    </button>
                  <?php else: ?>
                    <button type="button" class="btn-manage" style="opacity: 0.5; cursor: not-allowed; border-color: #ccc; color: #999;" disabled>
                      Status Locked
                    </button>
                  <?php endif; ?>
                  <a href="clinical_record.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&id=<?php echo $row['patient_id']; ?>&appt=<?php echo $row['appointment_id']; ?>" class="btn-treatment">
                    Open Clinical Log
                  </a>
                </div>

              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div style="text-align:center; padding: 60px; background:white; border-radius:15px; color:#94a3b8;">
              <div style="font-size:48px; margin-bottom:16px;">📅</div>
              <p style="font-size:15px; font-weight:600;">No appointments found</p>
              <p style="font-size:13px;">Try switching the filter above or check back later.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Manage Appointment Modal -->
  <div id="manageModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Manage Appointment</h3>
        <button class="modal-close" type="button" onclick="closeManageModal()">&times;</button>
      </div>
      <form method="POST" action="dentist_appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?><?php echo $filter !== 'all' ? '&filter=' . $filter : ''; ?>">
        <input type="hidden" id="update_id" name="update_id" value="">
        <div class="form-group">
          <label>Appointment</label>
          <input type="text" id="manageAppointmentInfo" readonly style="background: #f8fafc;">
        </div>
        <div class="form-group">
          <label for="new_status">Status</label>
          <select id="new_status" name="new_status" required>
            <option value="">Select status</option>
            <option value="In Progress">In Progress</option>
            <option value="Completed">Completed</option>
            <option value="Cancelled">Cancelled</option>
          </select>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="closeManageModal()">Cancel</button>
          <button type="submit" id="updateStatusBtn" class="btn-primary-modal" name="update_appointment">Update Status</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    <?php printDateClockScript(); ?>

    function openManageModal(id, patientName, dentistName, status) {
      document.getElementById('update_id').value = id;
      document.getElementById('manageAppointmentInfo').value = patientName + ' (' + status + ')';
      
      const newStatusSelect = document.getElementById('new_status');
      const updateBtn = document.getElementById('updateStatusBtn');
      newStatusSelect.value = status;
      
      const lowerStatus = status ? status.toLowerCase() : '';
      if (lowerStatus === 'completed' || lowerStatus === 'cancelled') {
        newStatusSelect.disabled = true;
        if (updateBtn) updateBtn.disabled = true;
      } else {
        newStatusSelect.disabled = false;
        if (updateBtn) updateBtn.disabled = false;
      }
      
      document.getElementById('manageModal').classList.add('active');
    }

    function closeManageModal() {
      document.getElementById('manageModal').classList.remove('active');
    }

    window.onclick = function(event) {
      const manageModal = document.getElementById('manageModal');
      if (event.target === manageModal) closeManageModal();
    };

    // Verification logs
    console.log('UI Parity Active - Version 2.0');
    console.log('Dentist Appointments Module Active');
    
    document.getElementById('apptSearch').addEventListener('input', function() {
      const q = this.value.toLowerCase();
      document.querySelectorAll('.appt-card').forEach(card => {
        const match = (card.dataset.name || '').includes(q);
        card.classList.toggle('hidden-card', !match);
      });
    });
  </script>
</body>
</html>

