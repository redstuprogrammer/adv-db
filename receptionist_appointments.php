<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

// Role Check Implementation - Ensure user is logged in as receptionist
$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('receptionist');

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';
require_once __DIR__ . '/includes/tenant_tier_helper.php';

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

function formatTime12Hour($time) {
    if (empty($time)) return 'TBD';
    $parts = explode(':', $time);
    if (count($parts) < 2) return $time;
    $hour = (int)$parts[0];
    $minute = $parts[1];
    $ampm = $hour >= 12 ? 'PM' : 'AM';
    if ($hour > 12) $hour -= 12;
    if ($hour === 0) $hour = 12;
    return $hour . ':' . $minute . ' ' . $ampm;
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
$activeTab = $_GET['tab'] ?? 'appointments';
// requireTenantLogin is now handled by session manager above

$tenantData = $sessionManager->getTenantData();
$tenantName = $tenantData['tenant_name'] ?? '';
$tenantId = $sessionManager->getTenantId();
$receptionistName = $sessionManager->getUsername() ?? 'Receptionist';
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_appointment'])) {
    $patientId = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $dentistId = isset($_POST['dentist_id']) ? (int)$_POST['dentist_id'] : 0;
    $appointmentDate = trim($_POST['appointment_date'] ?? '');
    $appointmentTime = trim($_POST['appointment_time'] ?? '');
    $status = 'Pending'; // Automatically set to Pending on creation

    if (!tenantHasTierFeature((int)$tenantId, 'appointment_scheduling', $conn)) {
        $errorMessage = 'Appointment scheduling is not available on your current plan.';
    } elseif ($patientId > 0 && $dentistId > 0 && $appointmentDate !== '' && $appointmentTime !== '') {
        // Check if a booking already exists for this dentist at the same date and time
        $stmtCheck = mysqli_prepare($conn, 'SELECT appointment_id FROM appointment WHERE tenant_id = ? AND dentist_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ("Cancelled", "Disapproved")');
        mysqli_stmt_bind_param($stmtCheck, 'iiss', $tenantId, $dentistId, $appointmentDate, $appointmentTime);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_store_result($stmtCheck);
        
        if (mysqli_stmt_num_rows($stmtCheck) > 0) {
            $errorMessage = 'Booking already exists for this dentist at the selected date and time.';
            $isBookingError = true;
            mysqli_stmt_close($stmtCheck);
        } else {
            mysqli_stmt_close($stmtCheck);
            $stmtAdd = mysqli_prepare($conn, 'INSERT INTO appointment (tenant_id, patient_id, dentist_id, appointment_date, appointment_time, status, is_appointment_request) VALUES (?, ?, ?, ?, ?, ?, 0)');
            if ($stmtAdd) {
                mysqli_stmt_bind_param($stmtAdd, 'iiisss', $tenantId, $patientId, $dentistId, $appointmentDate, $appointmentTime, $status);
                if (mysqli_stmt_execute($stmtAdd)) {
                    $successMessage = 'Appointment scheduled successfully.';
                } else {
                    $errorMessage = 'Unable to schedule appointment. DB Error: ' . $conn->error;
                    $isBookingError = true;
                    error_log("Appt add failed for tenant $tenantId: " . $conn->error);
                }
                mysqli_stmt_close($stmtAdd);
            } else {
                $errorMessage = 'Unable to prepare appointment statement.';
                $isBookingError = true;
            }
        }
    } else {
        $errorMessage = 'Please select a patient, dentist, date, and time for the appointment.';
        $isBookingError = true;
    }
}

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
                } else {
                    $errorMessage = 'Unable to update appointment status.';
                }
                mysqli_stmt_close($stmtUpdate);
            } else {
                $errorMessage = 'Unable to prepare appointment status statement.';
            }
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

$queryActiveAppointments = "SELECT 
            a.appointment_id, 
            a.appointment_date, 
            a.appointment_time,
            a.procedure_name,
            p.first_name, 
            p.last_name, 
            d.last_name AS d_last, 
            a.status,
            a.dentist_id
          FROM appointment a 
          LEFT JOIN patient p ON a.patient_id = p.patient_id AND p.tenant_id = a.tenant_id
          LEFT JOIN users d ON a.dentist_id = d.user_id AND d.tenant_id = a.tenant_id
          WHERE a.tenant_id = ? AND a.status <> 'Disapproved'
          ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.appointment_id ASC";

$appointmentsResult = null;
$stmt = mysqli_prepare($conn, $queryActiveAppointments);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $tenantId);
    mysqli_stmt_execute($stmt);
    $appointmentsResult = mysqli_stmt_get_result($stmt);
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
      .status-pill.in-progress { background: #e0f2fe; color: #0369a1; }
      .status-pill.cancelled { background: #fee2e2; color: #991b1b; }
      .status-pill.disapproved { background: #fee2e2; color: #991b1b; }
      .status-pill.approved { background: #dcfce7; color: #166534; }

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

      .tab-button {
        background: #e2e8f0;
        color: #0f172a;
        border: 1px solid transparent;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 14px;
        cursor: pointer;
        transition: background 0.2s, color 0.2s, border-color 0.2s;
      }

      .tab-button.active {
        background: var(--dashboard-accent);
        color: white;
        border-color: var(--dashboard-accent);
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

      .modal-content.wide {
        max-width: 1000px;
        padding: 0;
        overflow: hidden;
      }

      .booking-grid {
        display: grid;
        grid-template-columns: 350px 1fr;
        min-height: 600px;
      }

      .booking-sidebar {
        padding: 24px;
        background: #f8fafc;
        border-right: 1px solid var(--dashboard-border);
        display: flex;
        flex-direction: column;
        gap: 20px;
      }

      .booking-main {
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 20px;
        max-height: 80vh;
        overflow-y: auto;
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
        display: contents;
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
        margin-top: auto;
        padding-top: 20px;
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

      /* Calendar Grid */
      .calendar-container {
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        padding: 16px;
      }

      .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
      }

      .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 4px;
      }

      .cal-day-header {
        text-align: center;
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        padding: 8px 0;
      }

      .cal-day {
        aspect-ratio: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        cursor: pointer;
        transition: 0.2s;
        border: 1px solid transparent;
        position: relative;
        font-size: 14px;
      }

      .cal-day:hover:not(.disabled) {
        background: #f1f5f9;
        border-color: var(--dashboard-accent);
      }

      .cal-day.active {
        background: var(--dashboard-accent) !important;
        color: white !important;
      }

      .cal-day.disabled {
        cursor: not-allowed;
        opacity: 0.3;
        background: #f1f5f9;
      }

      .cal-day.today {
        border: 2px solid var(--dashboard-accent);
        font-weight: 700;
      }

      .cal-day.working {
        background: #ecfdf5;
        color: #065f46;
        font-weight: 600;
      }

      .cal-dot {
        width: 4px;
        height: 4px;
        background: var(--dashboard-accent);
        border-radius: 50%;
        margin-top: 2px;
      }

      /* Time Grid */
      .time-slot-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
        margin-top: 10px;
      }

      .time-chip {
        padding: 10px;
        text-align: center;
        border: 1px solid var(--dashboard-border);
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
      }

      .time-chip:hover:not(.disabled) {
        border-color: var(--dashboard-accent);
        background: #f0f9ff;
      }

      .time-chip.active {
        background: var(--dashboard-accent);
        color: white;
        border-color: var(--dashboard-accent);
      }

      .time-chip.disabled {
        background: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
        text-decoration: line-through;
        opacity: 0.6;
      }

      .booking-summary-card {
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        padding: 16px;
        margin-top: auto;
      }

      .summary-item {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        margin-bottom: 8px;
      }

      .summary-label { color: #64748b; }
      .summary-value { font-weight: 700; color: var(--dashboard-accent); }
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
        <?php if ($errorMessage && !isset($_POST['add_appointment'])): ?>
          <div class="alert-box" style="background: #fef2f2; color: #991b1b; border-color: #fecaca; margin-bottom: 20px;"><?php echo h($errorMessage); ?></div>
        <?php endif; ?>
        <div class="content-header">          <div>
            <h2 class="content-title">Front Desk Appointments</h2>
          </div>
          <button class="add-btn" type="button" onclick="openScheduleModal()">+ Schedule Appointment</button>
        </div>
 
        <div id="appointmentsSection">
          <table class="queue-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Patient</th>
                <th>Dentist</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($appointmentsResult && $appointmentsResult->num_rows > 0): ?>
                <?php while($row = $appointmentsResult->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></td>
                    <td><span class="time-badge"><?php echo h(formatTime12Hour($row['appointment_time'])); ?></span></td>
                    <td><strong><?php echo h(($row['first_name'] ?? '') . " " . ($row['last_name'] ?? '')); ?></strong></td>
                    <td>Dr. <?php echo h($row['d_last'] ?? ''); ?></td>
                    <td><span class="status-pill <?php echo str_replace(' ', '-', strtolower($row['status'] ?? '')); ?>"><?php echo h($row['status'] ?? ''); ?></span></td>
                    <td class="actions-cell">
                      <?php 
                        $isFinalStatus = in_array(strtolower($row['status'] ?? ''), ['completed', 'cancelled']);
                        if (!$isFinalStatus): 
                      ?>
                        <a href="javascript:void(0);" class="action-link" onclick="openManageModal(<?php echo (int)$row['appointment_id']; ?>, <?php echo htmlspecialchars(json_encode(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode('Dr. ' . ($row['d_last'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)">Manage</a>
                      <?php else: ?>
                        <span class="action-link" style="opacity: 0.5; cursor: not-allowed; border-color: #ccc; color: #999;">Locked</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="6" style="text-align:center; padding:30px;">No appointments found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Schedule Appointment Modal -->
  <div id="scheduleModal" class="modal">
    <div class="modal-content wide">
      <form method="POST" action="receptionist_appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>">
        <div class="booking-grid">
          <!-- Sidebar: Selection -->
          <div class="booking-sidebar">
            <div class="modal-header">
              <h3 class="modal-title">Schedule Appointment</h3>
              <button class="modal-close" type="button" onclick="closeScheduleModal()">&times;</button>
            </div>

            <?php if ($errorMessage && isset($_POST['add_appointment'])): ?>
              <div style="background: #fef2f2; color: #991b1b; padding: 12px; border-radius: 8px; border: 1px solid #fecaca; font-size: 13px; margin-bottom: 15px; font-weight: 600;">
                ⚠️ <?php echo h($errorMessage); ?>
              </div>
            <?php endif; ?>

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
              <label for="dentist_id">Preferred Dentist</label>
              <select id="dentist_id" onchange="handleDentistChange()">
                <option value="">Any Dentist (First Available)</option>
                <?php foreach ($dentists as $dentist): ?>
                  <option value="<?php echo (int)$dentist['dentist_id']; ?>">Dr. <?php echo h($dentist['first_name'] . ' ' . $dentist['last_name']); ?></option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" id="final_dentist_id" name="dentist_id" value="">
            </div>

            <div class="booking-summary-card">
              <h4 style="margin: 0 0 12px 0; font-size: 14px; color: var(--dashboard-accent);">Appointment Summary</h4>
              <div class="summary-item">
                <span class="summary-label">Date:</span>
                <span class="summary-value" id="summary-date">Not selected</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Time:</span>
                <span class="summary-value" id="summary-time">Not selected</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Dentist:</span>
                <span class="summary-value" id="summary-dentist">First Available</span>
              </div>
            </div>

            <input type="hidden" id="appointment_date" name="appointment_date" required>
            <input type="hidden" id="appointment_time" name="appointment_time" required>

            <div class="modal-actions">
              <button type="button" class="btn-secondary" onclick="closeScheduleModal()">Cancel</button>
              <button type="submit" class="btn-primary" name="add_appointment">Confirm Booking</button>
            </div>
          </div>

          <!-- Main: Calendar & Slots -->
          <div class="booking-main">
            <div class="calendar-container">
              <div class="calendar-header">
                <button type="button" class="action-link" onclick="prevMonth()" style="padding: 4px 10px;">❮</button>
                <h4 id="calendar-month-year" style="margin: 0; font-weight: 700;">September 2026</h4>
                <button type="button" class="action-link" onclick="nextMonth()" style="padding: 4px 10px;">❯</button>
              </div>
              <div class="calendar-grid" id="calendar-grid">
                <!-- Calendar will be rendered here -->
              </div>
            </div>

            <div id="slots-container" style="display: none;">
              <h4 style="margin: 0; font-size: 14px; color: var(--dashboard-accent);">Available Time Slots</h4>
              <div class="time-slot-grid" id="time-slot-grid">
                <!-- Slots will be rendered here -->
              </div>
            </div>

            <div id="no-slots-msg" style="display: block; padding: 40px; text-align: center; color: #64748b; background: #f8fafc; border-radius: 12px;">
              <p>Please select a date from the calendar to view available times.</p>
            </div>
          </div>
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
            <option value="In Progress">In Progress</option>
            <option value="Completed">Completed</option>
            <option value="Cancelled">Cancelled</option>
          </select>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-secondary" onclick="closeManageModal()">Cancel</button>
          <button type="submit" id="updateStatusBtn" class="btn-primary" name="update_appointment">Update Status</button>
        </div>
      </form>
    </div>
  </div>




  <script>
    const tenantId = <?php echo json_encode($tenantId); ?>;

    function openScheduleModal() {
      console.log('Schedule modal opened');
      const form = document.querySelector('#scheduleModal form');
      if (form) form.reset();
      
      // Reset internal state
      selectedBookingDate = null;
      selectedBookingTime = null;
      selectedBookingDentist = null;
      currentViewDate = new Date();
      
      document.getElementById('appointment_date').value = '';
      document.getElementById('appointment_time').value = '';
      document.getElementById('summary-date').textContent = 'Not selected';
      document.getElementById('summary-time').textContent = 'Not selected';
      document.getElementById('summary-dentist').textContent = 'First Available';
      document.getElementById('slots-container').style.display = 'none';
      document.getElementById('no-slots-msg').style.display = 'block';
      
      document.getElementById('scheduleModal').classList.add('active');
      fetchAvailabilityData(currentViewDate.getMonth(), currentViewDate.getFullYear());
    }

    function closeScheduleModal() {
      document.getElementById('scheduleModal').classList.remove('active');
    }

    let currentViewDate = new Date();
    let selectedBookingDate = null;
    let selectedBookingTime = null;
    let availabilityData = null;

    async function fetchAvailabilityData(month, year, dentistId = null) {
      const url = `api/get_monthly_availability.php?tenant_id=${tenantId}&month=${month + 1}&year=${year}${dentistId ? '&dentist_id=' + dentistId : ''}`;
      try {
        const response = await fetch(url);
        availabilityData = await response.json();
        renderBookingCalendar();
      } catch (err) {
        console.error('Failed to fetch availability:', err);
      }
    }

    function renderBookingCalendar() {
      const grid = document.getElementById('calendar-grid');
      const monthYearLabel = document.getElementById('calendar-month-year');
      if (!grid || !monthYearLabel) return;

      grid.innerHTML = '';
      const year = currentViewDate.getFullYear();
      const month = currentViewDate.getMonth();
      
      monthYearLabel.textContent = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(currentViewDate);

      // Days of week header
      ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
        const div = document.createElement('div');
        div.className = 'cal-day-header';
        div.textContent = day;
        grid.appendChild(div);
      });

      const firstDay = new Date(year, month, 1).getDay();
      const daysInMonth = new Date(year, month + 1, 0).getDate();

      // Empty slots before first day
      for (let i = 0; i < firstDay; i++) {
        grid.appendChild(document.createElement('div'));
      }

      const today = new Date().toISOString().split('T')[0];

      for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const dayDiv = document.createElement('div');
        dayDiv.className = 'cal-day';
        dayDiv.textContent = d;

        const dayName = new Intl.DateTimeFormat('en-US', { weekday: 'long' }).format(new Date(year, month, d));
        const isClinicClosed = availabilityData?.clinic_closed_days?.includes(dayName);
        const isDentistWorking = availabilityData?.dentist_working_days?.length > 0 ? availabilityData.dentist_working_days.includes(dayName) : true;
        
        const isPast = dateStr < today;

        if (isClinicClosed || !isDentistWorking || isPast) {
          dayDiv.classList.add('disabled');
        } else {
          dayDiv.classList.add('working');
          dayDiv.onclick = () => handleDateClick(dateStr);
        }

        if (dateStr === today) dayDiv.classList.add('today');
        if (dateStr === selectedBookingDate) dayDiv.classList.add('active');

        grid.appendChild(dayDiv);
      }
    }

    function prevMonth() {
      currentViewDate.setMonth(currentViewDate.getMonth() - 1);
      fetchAvailabilityData(currentViewDate.getMonth(), currentViewDate.getFullYear(), document.getElementById('dentist_id').value);
    }

    function nextMonth() {
      currentViewDate.setMonth(currentViewDate.getMonth() + 1);
      fetchAvailabilityData(currentViewDate.getMonth(), currentViewDate.getFullYear(), document.getElementById('dentist_id').value);
    }

    function handleDentistChange() {
      const dentistId = document.getElementById('dentist_id').value;
      const dentistName = document.getElementById('dentist_id').options[document.getElementById('dentist_id').selectedIndex].text;
      document.getElementById('summary-dentist').textContent = dentistId ? dentistName : 'First Available';
      document.getElementById('final_dentist_id').value = dentistId;
      
      // Reset date/time selection when dentist changes if they were selected
      selectedBookingTime = null;
      document.getElementById('appointment_time').value = '';
      document.getElementById('summary-time').textContent = 'Not selected';
      
      fetchAvailabilityData(currentViewDate.getMonth(), currentViewDate.getFullYear(), dentistId);
      
      if (selectedBookingDate) {
        loadSlotsForDate(selectedBookingDate, dentistId);
      }
    }

    async function handleDateClick(dateStr) {
      selectedBookingDate = dateStr;
      document.getElementById('appointment_date').value = dateStr;
      document.getElementById('summary-date').textContent = new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      
      // Reset time
      selectedBookingTime = null;
      document.getElementById('appointment_time').value = '';
      document.getElementById('summary-time').textContent = 'Not selected';
      
      renderBookingCalendar();
      loadSlotsForDate(dateStr, document.getElementById('dentist_id').value);
    }

    async function loadSlotsForDate(date, dentistId) {
      const slotsContainer = document.getElementById('slots-container');
      const timeGrid = document.getElementById('time-slot-grid');
      const noSlotsMsg = document.getElementById('no-slots-msg');

      slotsContainer.style.display = 'block';
      noSlotsMsg.style.display = 'none';
      timeGrid.innerHTML = '<p style="grid-column: 1/-1; text-align:center; padding: 20px;">Loading slots...</p>';

      let url = '';
      if (dentistId) {
        url = `api/get_available_slots.php?tenant_id=${tenantId}&dentist_id=${dentistId}&date=${date}`;
        document.getElementById('final_dentist_id').value = dentistId;
      } else {
        url = `api/get_available_dentists.php?tenant_id=${tenantId}&date=${date}`;
        try {
          const resp = await fetch(url);
          const data = await resp.json();
          if (data.success && data.dentists.length > 0) {
            const firstDentistId = data.dentists[0].dentist_id;
            const firstDentistName = `Dr. ${data.dentists[0].first_name} ${data.dentists[0].last_name}`;
            document.getElementById('summary-dentist').textContent = firstDentistName + " (Auto-selected)";
            document.getElementById('final_dentist_id').value = firstDentistId;
            url = `api/get_available_slots.php?tenant_id=${tenantId}&dentist_id=${firstDentistId}&date=${date}`;
          } else {
             timeGrid.innerHTML = '<p style="grid-column: 1/-1; text-align:center; padding: 20px; color: #ef4444;">No dentists available on this day.</p>';
             return;
          }
        } catch(e) { console.error(e); }
      }

      try {
        const response = await fetch(url);
        const data = await response.json();
        
        timeGrid.innerHTML = '';
        if (!data.success || !data.slots || data.slots.length === 0) {
          timeGrid.innerHTML = '<p style="grid-column: 1/-1; text-align:center; padding: 20px;">No available slots for this selection.</p>';
          return;
        }

        data.slots.forEach(slot => {
          const chip = document.createElement('div');
          chip.className = `time-chip ${slot.available ? '' : 'disabled'}`;
          chip.textContent = slot.label;
          if (slot.available) {
            chip.onclick = () => {
              document.querySelectorAll('.time-chip').forEach(c => c.classList.remove('active'));
              chip.classList.add('active');
              selectedBookingTime = slot.time;
              document.getElementById('appointment_time').value = slot.time;
              document.getElementById('summary-time').textContent = slot.label;
            };
          }
          if (selectedBookingTime === slot.time) chip.classList.add('active');
          timeGrid.appendChild(chip);
        });
      } catch (err) {
        console.error('Failed to load slots:', err);
        timeGrid.innerHTML = '<p style="grid-column: 1/-1; text-align:center; padding: 20px; color: #ef4444;">Error loading slots.</p>';
      }
    }

    function openManageModal(id, patientName, dentistName, status) {
      document.getElementById('update_id').value = id;
      document.getElementById('manageAppointmentInfo').value = patientName + ' with ' + dentistName + ' (' + status + ')';
      
      const newStatusSelect = document.getElementById('new_status');
      const updateBtn = document.getElementById('updateStatusBtn');
      newStatusSelect.value = status;
      
      const lowerStatus = status ? status.toLowerCase() : '';
      if (lowerStatus === 'completed' || lowerStatus === 'cancelled') {
        // Fully lock the status if it's in a final state
        newStatusSelect.disabled = true;
        if (updateBtn) updateBtn.disabled = true;
      } else {
        // Allow modification for Pending or In Progress
        newStatusSelect.disabled = false;
        if (updateBtn) updateBtn.disabled = false;
        
        // Ensure "In Progress" option visibility logic
        const inProgressOption = newStatusSelect.querySelector('option[value="In Progress"]');
        if (inProgressOption) {
            inProgressOption.disabled = false;
            inProgressOption.style.display = 'block';
        }
      }
      
      document.getElementById('manageModal').classList.add('active');
    }

    function closeManageModal() {
      document.getElementById('manageModal').classList.remove('active');
    }


    window.addEventListener('click', function(event) {
      const scheduleModal = document.getElementById('scheduleModal');
      const manageModal = document.getElementById('manageModal');
      if (event.target === scheduleModal) closeScheduleModal();
      if (event.target === manageModal) closeManageModal();
    });
    
    
    // Auto-reopen modal if there was an error booking
    <?php if ($errorMessage && isset($_POST['add_appointment'])): ?>
    document.addEventListener('DOMContentLoaded', () => {
      openScheduleModal();
      // Restore previous selections if available
      const prevPatient = <?php echo json_encode($_POST['patient_id'] ?? ''); ?>;
      const prevDentist = <?php echo json_encode($_POST['dentist_id'] ?? ''); ?>;
      const prevDate = <?php echo json_encode($_POST['appointment_date'] ?? ''); ?>;
      const prevTime = <?php echo json_encode($_POST['appointment_time'] ?? ''); ?>;
      
      if (prevPatient) document.getElementById('patient_id').value = prevPatient;
      if (prevDentist) {
        document.getElementById('dentist_id').value = prevDentist;
        handleDentistChange();
      }
      if (prevDate) handleDateClick(prevDate);
      // Note: Time slot will be re-rendered after handleDateClick and fetchAvailabilityData
    });
    <?php endif; ?>

    console.log('Receptionist Appointments Page Initialized');
  </script>
</body>
</html>

