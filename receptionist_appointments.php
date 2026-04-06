<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
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

function formatTenantPatientId($tenant_patient_id) {
    return '#' . str_pad($tenant_patient_id, 4, '0', STR_PAD_LEFT);
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Receptionist' || $_SESSION['tenant_slug'] !== $tenantSlug) {
    header("Location: /tenant_login.php?tenant=" . rawurlencode($tenantSlug));
    exit();
}

$tenantName = $_SESSION['tenant_name'];
$tenantId = $_SESSION['tenant_id'];
$receptionistName = $_SESSION['username'] ?? 'Receptionist';
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_appointment'])) {
    $patientId = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $dentistId = isset($_POST['dentist_id']) ? (int)$_POST['dentist_id'] : 0;
    $appointmentDate = trim($_POST['appointment_date'] ?? '');
    $status = 'pending';

    if ($patientId > 0 && $dentistId > 0 && $appointmentDate !== '') {
        $stmtAdd = mysqli_prepare($conn, 'INSERT INTO appointment (tenant_id, patient_id, dentist_id, appointment_date, status) VALUES (?, ?, ?, ?, ?)');
        if ($stmtAdd) {
            mysqli_stmt_bind_param($stmtAdd, 'iiiss', $tenantId, $patientId, $dentistId, $appointmentDate, $status);
            if (mysqli_stmt_execute($stmtAdd)) {
                $successMessage = 'Appointment scheduled successfully.';
            } else {
                $errorMessage = 'Unable to schedule appointment. Please try again.';
            }
            mysqli_stmt_close($stmtAdd);
        } else {
            $errorMessage = 'Unable to prepare appointment statement.';
        }
    } else {
        $errorMessage = 'Please select a patient, dentist, and appointment date.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment'])) {
    $appointmentId = isset($_POST['update_id']) ? (int)$_POST['update_id'] : 0;
    $newStatus = trim($_POST['new_status'] ?? '');

    if ($appointmentId > 0 && $newStatus !== '') {
        $stmtUpdate = mysqli_prepare($conn, 'UPDATE appointment SET status = ? WHERE appointment_id = ? AND tenant_id = ?');
        if ($stmtUpdate) {
            mysqli_stmt_bind_param($stmtUpdate, 'sii', $newStatus, $appointmentId, $tenantId);
            if (mysqli_stmt_execute($stmtUpdate)) {
                $successMessage = 'Appointment status updated successfully.';
            } else {
                $errorMessage = 'Unable to update appointment status.';
            }
            mysqli_stmt_close($stmtUpdate);
        } else {
            $errorMessage = 'Unable to prepare appointment status statement.';
        }
    } else {
        $errorMessage = 'Valid appointment and status are required.';
    }
}

$patients = [];
$stmtPatients = mysqli_prepare($conn, 'SELECT patient_id, tenant_patient_id, first_name, last_name FROM patient WHERE tenant_id = ? ORDER BY first_name ASC');
if ($stmtPatients) {
    mysqli_stmt_bind_param($stmtPatients, 'i', $tenantId);
    mysqli_stmt_execute($stmtPatients);
    $resultPatients = mysqli_stmt_get_result($stmtPatients);
    while ($row = mysqli_fetch_assoc($resultPatients)) {
        $patients[] = $row;
    }
    mysqli_stmt_close($stmtPatients);
}

$dentists = [];
$stmtDentists = mysqli_prepare($conn, 'SELECT user_id AS dentist_id, first_name, last_name FROM users WHERE tenant_id = ? AND role = "Dentist" ORDER BY last_name ASC');
if ($stmtDentists) {
    mysqli_stmt_bind_param($stmtDentists, 'i', $tenantId);
    mysqli_stmt_execute($stmtDentists);
    $resultDentists = mysqli_stmt_get_result($stmtDentists);
    while ($row = mysqli_fetch_assoc($resultDentists)) {
        $dentists[] = $row;
    }
    mysqli_stmt_close($stmtDentists);
}

/* ============================================================
   DATA FETCHING
============================================================ */

$query = "SELECT 
            a.appointment_id, 
            a.appointment_date, 
            p.first_name, 
            p.last_name, 
            d.last_name AS d_last, 
            a.status 
          FROM appointment a 
          LEFT JOIN patient p ON a.patient_id = p.patient_id 
          LEFT JOIN users d ON a.dentist_id = d.user_id
          WHERE a.tenant_id = ?
          ORDER BY a.appointment_date DESC, a.appointment_id ASC";

$result = null;
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $tenantId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Front Desk Appointments</title>
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

      .queue-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
      .queue-table th { background: var(--dashboard-bg); color: #64748b; padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; }
      .queue-table td { padding: 15px; border-bottom: 1px solid var(--dashboard-border); font-size: 14px; }

      .time-badge { background: rgba(13, 59, 102, 0.1); color: var(--dashboard-accent); padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
      .action-link { color: var(--dashboard-accent); text-decoration: none; font-weight: 600; font-size: 13px; border: 1px solid var(--dashboard-accent); padding: 5px 12px; border-radius: 6px; transition: 0.2s; }
      .action-link:hover { background: var(--dashboard-accent); color: white; }

      .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
      .status-pill.pending { background: #fff3cd; color: #856404; }
      .status-pill.completed { background: #dcfce7; color: #166534; }
      .status-pill.cancelled { background: #fee2e2; color: #991b1b; }

      .alert-box { padding: 15px; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }

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

      .add-btn {
        background: var(--dashboard-accent);
        color: white;
        border: none;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 16px;
        cursor: pointer;
        text-decoration: none;
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
        z-index: 999;
      }

      .modal.active {
        display: flex;
      }

      .modal-content {
        background: white;
        border-radius: 14px;
        padding: 24px;
        width: 100%;
        max-width: 560px;
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

      .modal form {
        display: grid;
        gap: 16px;
      }

      .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
      }

      .form-group label {
        font-weight: 600;
        color: #334155;
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
        margin-top: 8px;
      }

      .modal-actions button {
        padding: 10px 16px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
      }

      .modal-actions .btn-secondary {
        background: #f1f5f9;
        color: #334155;
      }

      .modal-actions .btn-primary {
        background: var(--dashboard-accent);
        color: white;
      }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <!-- Header Bar -->
      <div class="tenant-header-bar">
        <div class="tenant-header-title"><?php echo h($tenantName); ?> Front Desk</div>
        <?php renderDateClock(); ?>
      </div>

      <!-- Content -->
      <?php if (isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
        <div class="alert-box">✅ Appointment updated successfully!</div>
      <?php endif; ?>

      <div class="content-section">
        <?php if ($successMessage): ?>
          <div class="alert-box" style="background: #ecfdf5; color: #16573b; border-color: #bbf7d0; margin-bottom: 20px;"><?php echo h($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
          <div class="alert-box" style="background: #fef2f2; color: #991b1b; border-color: #fecaca; margin-bottom: 20px;"><?php echo h($errorMessage); ?></div>
        <?php endif; ?>
        <div class="content-header">
          <h2 class="content-title">All Appointments Master List</h2>
          <button class="add-btn" type="button" onclick="openScheduleModal()">+ Schedule Appointment</button>
        </div>

        <table class="queue-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Patient</th>
              <th>Dentist</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
              <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></td>
                  <td><strong><?php echo h(($row['first_name'] ?? '') . " " . ($row['last_name'] ?? '')); ?></strong></td>
                  <td>Dr. <?php echo h($row['d_last'] ?? ''); ?></td>
                  <td><span class="status-pill <?php echo strtolower($row['status'] ?? ''); ?>"><?php echo h($row['status'] ?? ''); ?></span></td>
                  <td><a href="javascript:void(0);" class="action-link" onclick="openManageModal(<?php echo (int)$row['appointment_id']; ?>, <?php echo json_encode(h(($row['first_name'] ?? '') . " " . ($row['last_name'] ?? ''))); ?>, <?php echo json_encode('Dr. ' . h($row['d_last'] ?? '')); ?>, <?php echo json_encode(h($row['status'] ?? '')); ?>)">Manage</a></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5" style="text-align:center; padding:30px;">No appointments found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Schedule Appointment Modal -->
  <div id="scheduleModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Schedule Appointment</h3>
        <button class="modal-close" type="button" onclick="closeScheduleModal()">&times;</button>
      </div>
      <form method="POST" action="receptionist_appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>">
        <div class="form-group">
          <label for="patient_id">Patient</label>
          <select id="patient_id" name="patient_id" required>
            <option value="">Select patient</option>
            <?php foreach ($patients as $patient): ?>
              <option value="<?php echo (int)$patient['patient_id']; ?>"><?php echo h(formatTenantPatientId($patient['tenant_patient_id']) . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="dentist_id">Dentist</label>
          <select id="dentist_id" name="dentist_id" required>
            <option value="">Select dentist</option>
            <?php foreach ($dentists as $dentist): ?>
              <option value="<?php echo (int)$dentist['dentist_id']; ?>"><?php echo h('Dr. ' . $dentist['first_name'] . ' ' . $dentist['last_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="appointment_date">Appointment Date</label>
          <input type="date" id="appointment_date" name="appointment_date" required>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="closeScheduleModal()">Cancel</button>
          <button type="submit" class="btn-primary" name="add_appointment">Save Appointment</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Manage Appointment Modal -->
  <div id="manageModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Manage Appointment</h3>
        <button class="modal-close" type="button" onclick="closeManageModal()">&times;</button>
      </div>
      <form method="POST" action="receptionist_appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>">
        <input type="hidden" id="update_id" name="update_id" value="">
        <div class="form-group">
          <label>Appointment</label>
          <input type="text" id="manageAppointmentInfo" readonly>
        </div>
        <div class="form-group">
          <label for="new_status">Status</label>
          <select id="new_status" name="new_status" required>
            <option value="">Select status</option>
            <option value="pending">Pending</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="closeManageModal()">Cancel</button>
          <button type="submit" class="btn-primary" name="update_appointment">Update Status</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    <?php printDateClockScript(); ?>

    <?php printDateClockScript(); ?>

    function openScheduleModal() {
      document.getElementById('scheduleModal').classList.add('active');
    }

    function closeScheduleModal() {
      document.getElementById('scheduleModal').classList.remove('active');
    }

    function openManageModal(id, patientName, dentistName, status) {
      document.getElementById('update_id').value = id;
      document.getElementById('manageAppointmentInfo').value = patientName + ' with ' + dentistName + ' (' + status + ')';
      document.getElementById('new_status').value = status.toLowerCase();
      document.getElementById('manageModal').classList.add('active');
    }

    function closeManageModal() {
      document.getElementById('manageModal').classList.remove('active');
    }

    // Click outside modal to close
    window.addEventListener('click', function(event) {
      const scheduleModal = document.getElementById('scheduleModal');
      const manageModal = document.getElementById('manageModal');
      if (event.target === scheduleModal) {
        closeScheduleModal();
      }
      if (event.target === manageModal) {
        closeManageModal();
      }
    });
    
    // Verification logs
    console.log('UI Parity Active - Version 2.0');
    console.log('Receptionist Appointments Page Initialized');
    console.log('FINAL UI SYNC COMPLETE');
    console.log('Anti-Crash System Active - V2');
  </script>
</body>
</html>

