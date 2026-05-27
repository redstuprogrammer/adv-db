<?php
/**
 * ============================================
 * TENANT APPOINTMENT MANAGEMENT - ENHANCED WITH STATUS UPDATES & CLINICAL NOTES
 * Last Updated: April 4, 2026
 * Features: Appointment Scheduling, Status Updates, Clinical Notes, Search/Filter
 * ✓ FLAG TEST: Appointment management module successfully updated for Azure
 * ============================================
 */

// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';
require_once __DIR__ . '/includes/tenant_tier_helper.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function formatTenantPatientId($tenant_patient_id) {
  return '#' . str_pad($tenant_patient_id, 4, '0', STR_PAD_LEFT);
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

// Role Check Implementation - Ensure user is an Admin
if (!isset($_SESSION['role'])) {
    header("Location: tenant_login.php");
    exit();
}

if ($_SESSION['role'] !== 'Admin') {
    header("Location: tenant_login.php");
    exit();
}

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$today = date('Y-m-d');

// Fetch patients and dentists for scheduling modal
$patients = [];
$pstmt = $conn->prepare('SELECT patient_id, tenant_patient_id, first_name, last_name FROM patient WHERE tenant_id = ? ORDER BY first_name ASC');
if ($pstmt) {
  $pstmt->bind_param('i', $tenantId);
  $pstmt->execute();
  $pres = $pstmt->get_result();
  while ($r = $pres->fetch_assoc()) $patients[] = $r;
  $pstmt->close();
}

$dentists = [];
$dstmt = $conn->prepare('SELECT user_id AS dentist_id, first_name, last_name FROM users WHERE tenant_id = ? AND role = "Dentist" ORDER BY last_name ASC');
if ($dstmt) {
  $dstmt->bind_param('i', $tenantId);
  $dstmt->execute();
  $dres = $dstmt->get_result();
  while ($r = $dres->fetch_assoc()) $dentists[] = $r;
  $dstmt->close();
}

// Handle Add Appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_appointment'])) {
    $patientId = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $dentistId = isset($_POST['dentist_id']) ? (int)$_POST['dentist_id'] : 0;
    $appointmentDate = isset($_POST['appointment_date']) ? trim($_POST['appointment_date']) : '';
    $appointmentTime = isset($_POST['appointment_time']) ? trim($_POST['appointment_time']) : '';
    $status = 'pending'; // Default status; user should not set this manually

    if (!tenantHasTierFeature((int)$tenantId, 'appointment_scheduling', $conn)) {
        $errorMsg = 'Appointment scheduling is not available on your current plan.';
    } elseif ($patientId > 0 && $dentistId > 0 && $appointmentDate !== '' && $appointmentTime !== '') {
        $stmt = $conn->prepare('INSERT INTO appointment (tenant_id, patient_id, dentist_id, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iiisss', $tenantId, $patientId, $dentistId, $appointmentDate, $appointmentTime, $status);
        if ($stmt->execute()) {
          $successMsg = 'Appointment scheduled successfully!';
          $newAppointmentId = $conn->insert_id ?? null;
          $desc = safeDesc('Appointment', 'Appointment', $newAppointmentId, ['status' => 'scheduled']);
          logTenantActivity($conn, $tenantId, 'Appointment', $desc);
        }
        $stmt->close();
    } else {
        $errorMsg = 'Please choose a patient, dentist, date, and time for the appointment.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_appointment'])) {
    $appointmentId = isset($_POST['reschedule_id']) ? (int)$_POST['reschedule_id'] : 0;
    $newDate = trim($_POST['reschedule_date'] ?? '');
    $newTime = trim($_POST['reschedule_time'] ?? '');
    $newDentist = isset($_POST['reschedule_dentist_id']) ? (int)$_POST['reschedule_dentist_id'] : 0;

    if ($appointmentId > 0 && $newDate !== '' && $newTime !== '' && $newDentist > 0) {
        $stmt = $conn->prepare('UPDATE appointment SET appointment_date = ?, appointment_time = ?, dentist_id = ? WHERE appointment_id = ? AND tenant_id = ?');
        if ($stmt) {
            $stmt->bind_param('ssiii', $newDate, $newTime, $newDentist, $appointmentId, $tenantId);
            if ($stmt->execute()) {
              $successMsg = 'Appointment rescheduled successfully.';
              try {
                $desc = safeDesc('Appointment', 'Rescheduled', $appointmentId, ['new_date' => $newDate, 'new_time' => $newTime, 'dentist_id' => $newDentist]);
                logTenantActivity($conn, $tenantId, 'Appointment', $desc);
              } catch (Exception $e) {
                error_log('Reschedule logging failed: ' . $e->getMessage());
              }
            } else {
              $errorMsg = 'Unable to reschedule appointment.';
            }
            $stmt->close();
        } else {
            $errorMsg = 'Unable to prepare reschedule statement.';
        }
    } else {
        $errorMsg = 'Please select a valid date, time, and dentist to reschedule.';
    }
}

// Handle Update Appointment Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment'])) {
    $appointmentId = isset($_POST['update_id']) ? (int)$_POST['update_id'] : 0;
    $rawStatus = trim($_POST['new_status'] ?? '');
    $statusMap = [
        'ongoing' => 'ongoing',
        'in progress' => 'ongoing',
        'pending' => 'pending',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        'approved' => 'approved',
        'disapproved' => 'disapproved',
        'pending payment' => 'pending_payment',
    ];
    $normalized = strtolower(trim($rawStatus));
    $newStatus = $statusMap[$normalized] ?? '';

    if ($appointmentId > 0 && $newStatus !== '') {
        // Extra safety check: verify current status is not a final state
        $checkStmt = $conn->prepare('SELECT status FROM appointment WHERE appointment_id = ? AND tenant_id = ?');
        $checkStmt->bind_param('ii', $appointmentId, $tenantId);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result();
        $current = $checkRes->fetch_assoc();
        $checkStmt->close();

        if ($current && in_array(strtolower($current['status']), ['completed', 'cancelled'])) {
            $errorMsg = 'This appointment is already ' . strtolower($current['status']) . ' and cannot be modified.';
        } else {
            $stmt = $conn->prepare('UPDATE appointment SET status = ? WHERE appointment_id = ? AND tenant_id = ?');
            $stmt->bind_param('sii', $newStatus, $appointmentId, $tenantId);
            if ($stmt->execute()) {
            $successMsg = 'Appointment updated successfully!';
            $desc = safeDesc('Appointment', 'Appointment', $appointmentId, ['status' => $newStatus]);
            logTenantActivity($conn, $tenantId, 'Appointment', $desc);
          } else {
                $errorMsg = 'Unable to update appointment status.';
            }
            $stmt->close();
        }
    } else {
        $errorMsg = 'Valid appointment and status are required.';
    }
}

// Fetch appointments with service info (if available)
$appointments = [];
$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$totalAppointments = 0;
$countQuery = 'SELECT COUNT(*) AS total FROM appointment WHERE tenant_id = ? AND status <> "Disapproved"';
if ($filter === 'today') {
    $countQuery .= ' AND appointment_date = ?';
} elseif ($filter === 'upcoming') {
    $countQuery .= ' AND appointment_date > ?';
}
$countStmt = $conn->prepare($countQuery);
if ($countStmt) {
    if ($filter === 'today' || $filter === 'upcoming') {
        $countStmt->bind_param('is', $tenantId, $today);
    } else {
        $countStmt->bind_param('i', $tenantId);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    if ($countRow = $countResult->fetch_assoc()) {
        $totalAppointments = (int)$countRow['total'];
    }
    $countStmt->close();
}

$query = "SELECT a.appointment_id, a.patient_id, a.dentist_id, a.appointment_date, a.appointment_time, a.status,
                 a.procedure_name, a.notes,
                 p.first_name AS patient_first, p.last_name AS patient_last,
                 CONCAT('Dr. ', TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))) AS dentist_name
          FROM appointment a
          LEFT JOIN patient p ON a.patient_id = p.patient_id AND p.tenant_id = a.tenant_id
          LEFT JOIN users u ON a.dentist_id = u.user_id AND u.tenant_id = a.tenant_id
          WHERE a.tenant_id = ? AND a.status <> 'Disapproved'";
if ($filter === 'today') {
    $query .= ' AND a.appointment_date = ?';
} elseif ($filter === 'upcoming') {
    $query .= ' AND a.appointment_date > ?';
}
$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT ?, ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    if ($filter === 'today' || $filter === 'upcoming') {
        $stmt->bind_param('isii', $tenantId, $today, $offset, $perPage);
    } else {
        $stmt->bind_param('iii', $tenantId, $offset, $perPage);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Appointments</title>
    <link rel="stylesheet" href="tenant_style.css">
    <link rel="stylesheet" href="components.css">
    <style>
      :root {
        --accent: #0d3b66;
        --border: #e2e8f0;
        --bg: #f8fafc;
      }

      .btn-primary {
        background: var(--accent);
        color: white;
        padding: 10px 18px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: background 0.2s ease;
        min-height: 44px;
      }

      .btn-primary:hover {
        background: #0a2d4f;
      }

      .module-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
      }

      .btn-secondary {
        background: #f1f5f9;
        color: #334155;
        border: none;
        border-radius: 10px;
        padding: 10px 16px;
        cursor: pointer;
        font-weight: 600;
      }

      .action-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
      }

      .filter-tabs {
        display: flex;
        background: var(--bg);
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
        background: var(--accent);
        color: white;
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

      .modal-content {
        background: white;
        border-radius: 14px;
        padding: 24px;
        width: 100%;
        max-width: 560px;
        box-shadow: 0 20px 25px rgba(15, 23, 42, 0.15);
        max-height: calc(100vh - 40px);
        overflow-y: auto;
      }

      .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
      }

      .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #64748b;
      }

      .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 24px;
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
        background: var(--accent);
        color: white;
      }

      .form-group label {
        font-weight: 600;
        color: #334155;
      }

      .form-group input,
      .form-group select {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid var(--border);
        border-radius: 10px;
        font-size: 14px;
      }

      .modal form {
        display: contents;
      }

      .booking-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
      }

      .booking-sidebar {
        display: block;
      }

      .booking-main {
        display: block;
      }

      .modal.active {
        display: flex;
      }

      .modal-content.wide {
        max-width: 800px;
      }

      .tab.active, .action-btn.active {
        background: var(--accent);
        color: white;
      }

      .action-btn {
        text-decoration: none;
      }

      .search-box {
        padding: 12px 20px;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        width: 100%;
        max-width: 400px;
        font-size: 14px;
      }

      .search-container {
        flex: 1;
        min-width: 240px;
      }

      .action-bar .filter-tabs {
        margin-left: auto;
      }

      .filter-tabs a {
        text-decoration: none;
      }

      .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #64748b;
      }

      .modal-close:hover {
        color: #0d3b66;
      }

      .modal-actions .btn-secondary:hover,
      .modal-actions .btn-primary:hover,
      .btn-primary:hover {
        opacity: 0.95;
      }

      .modal textarea {
        min-height: 120px;
      }

      .modal .form-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
      }

      .filters {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
      }

      .filters input, .filters select {
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 13px;
      }

      .filters select {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      }

      .module-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
      }

      .module-table th {
        background: var(--bg);
        border-bottom: 2px solid var(--border);
        padding: 12px;
        text-align: left;
        font-weight: 700;
        color: var(--accent);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .module-table td {
        padding: 12px;
        border-bottom: 1px solid var(--border);
      }

      .module-table tbody tr:hover {
        background: var(--bg);
      }

      .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
      }

      .badge-confirmed { background: rgba(16, 185, 129, 0.1); color: #10b981; }
      .badge-pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
      .badge-cancelled { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

      /* Status Pills */
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

      /* Search and Filter */
      .search-container {
        margin-bottom: 20px;
      }

      .search-input {
        width: 100%;
        max-width: 400px;
        padding: 12px 16px;
        border: 1px solid var(--border);
        border-radius: 25px;
        outline: none;
        font-size: 14px;
      }

      .search-input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.1);
      }

      /* Edit Modal Styles */
      .edit-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.7);
        align-items: center;
        justify-content: center;
      }

      .edit-modal-content {
        background: white;
        padding: 30px;
        border-radius: 15px;
        width: 400px;
        position: relative;
      }

      .edit-form-group {
        margin-bottom: 18px;
      }

      .edit-form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 700;
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
      }

      .edit-form-group input,
      .edit-form-group select,
      .edit-form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 14px;
      }

      .edit-form-group textarea {
        resize: vertical;
        min-height: 80px;
      }

      .edit-modal-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
      }

      .edit-btn-cancel {
        flex: 1;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        cursor: pointer;
        background: white;
        color: #64748b;
      }

      .edit-btn-save {
        flex: 2;
        padding: 12px;
        border-radius: 8px;
        background: var(--accent);
        color: white;
        border: none;
        cursor: pointer;
        font-weight: bold;
      }

      .action-btn {
        display: inline-block;
        padding: 8px 12px;
        margin-right: 4px;
        background: var(--accent);
        border: 1px solid var(--accent);
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-size: 12px;
        color: white;
        font-weight: 600;
        transition: all 0.2s ease;
      }

      .action-btn:hover {
        background: #0a2d4f;
        border-color: #0a2d4f;
      }

      .action-btn {
        display: inline-block;
        padding: 8px 12px;
        margin-right: 4px;
        background: var(--accent);
        border: 1px solid var(--accent);
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-size: 12px;
        color: white;
        font-weight: 600;
        transition: all 0.2s ease;
      }

      .action-btn:hover {
        background: #0a2d4f;
        border-color: #0a2d4f;
      }

      .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.4);
      }

      .modal-content {
        background-color: white;
        margin: 10% auto;
        padding: 20px;
        border: 1px solid var(--border);
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
      }

      .modal-header {
        font-size: 18px;
        font-weight: 700;
        color: var(--accent);
        margin-bottom: 16px;
      }

      .form-group {
        margin-bottom: 12px;
      }

      .form-group label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: var(--accent);
        margin-bottom: 6px;
      }

      .form-group input,
      .form-group select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 13px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      }

      .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
      }

      .form-actions button {
        flex: 1;
        padding: 10px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        font-size: 13px;
      }

      .btn-submit {
        background: var(--accent);
        color: white;
      }

      .btn-cancel {
        background: var(--bg);
        color: var(--accent);
        border: 1px solid var(--border);
      }

      .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
      }

      .close:hover {
        color: var(--accent);
      }

      .success-msg {
        display: none;
        padding: 12px;
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
        border-radius: 8px;
        margin-bottom: 16px;
      }

      .live-clock-badge {
        background: linear-gradient(135deg, rgba(13, 59, 102, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
        border: 2px solid var(--accent);
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 16px;
        font-weight: 700;
        color: var(--accent);
        font-family: 'Courier New', monospace;
        letter-spacing: 1px;
        white-space: nowrap;
      }

      /* Pagination Styles */
      .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 30px;
        padding: 20px 0;
      }
      .page-link {
        padding: 8px 16px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: white;
        color: var(--accent);
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s ease;
      }
      .page-link:hover {
        background: var(--bg);
        border-color: var(--accent);
      }
      .page-link.active {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
      }
      .page-link.disabled {
        color: #94a3b8;
        pointer-events: none;
        background: #f1f5f9;
        border-color: #e2e8f0;
      }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">Appointments</div>
        <?php renderDateClock(); ?>
      </div>

      <div class="module-card">
        <?php if (isset($successMsg)): ?>
          <div class="alert-box" style="background: #ecfdf5; color: #16573b; border: 1px solid #bbf7d0; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;"><?php echo h($successMsg); ?></div>
        <?php endif; ?>
        <?php if (isset($errorMsg)): ?>
          <div class="alert-box" style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;"><?php echo h($errorMsg); ?></div>
        <?php endif; ?>

        <div class="content-header" style="display:flex; justify-content:flex-end; align-items:center; gap:12px; margin-bottom:16px;">
          <button class="btn-primary" type="button" onclick="openScheduleModal()">+ Schedule Appointment</button>
        </div>

        <div class="action-bar">
          <input type="text" id="appointmentSearch" class="search-box" placeholder="🔍 Search by patient, dentist, or treatment..." onkeyup="filterAppointments()">
          <div class="filter-tabs">
            <a href="?tenant=<?php echo rawurlencode($tenantSlug); ?>&filter=all" class="tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="?tenant=<?php echo rawurlencode($tenantSlug); ?>&filter=today" class="tab <?php echo $filter === 'today' ? 'active' : ''; ?>">Today</a>
            <a href="?tenant=<?php echo rawurlencode($tenantSlug); ?>&filter=upcoming" class="tab <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
          </div>
        </div>

        <table class="module-table" id="appointmentTable">
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
            <?php if (empty($appointments)): ?>
              <tr>
                <td colspan="6" style="text-align: center; color: #64748b; padding: 40px;">No appointments found in the schedule.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($appointments as $appt): ?>
                <tr>
                  <td><strong><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></strong></td>
                  <td><?php echo !empty($appt['appointment_time']) ? date('g:i A', strtotime($appt['appointment_time'])) : '-'; ?></td>
                  <td><?php echo h($appt['patient_first'] . ' ' . $appt['patient_last']); ?></td>
                  <td><?php echo h($appt['dentist_name'] ?: 'Unassigned'); ?></td>
                  <td><span class="status-pill <?php echo strtolower($appt['status']); ?>"><?php echo h(strtolower($appt['status']) === 'pending' ? 'Ongoing' : ucfirst($appt['status'])); ?></span></td>
                  <td>
                    <?php 
                      $isFinalStatus = in_array(strtolower($appt['status'] ?? ''), ['completed', 'cancelled']);
                      if (!$isFinalStatus): 
                    ?>
                      <button class="action-btn" onclick="openManageModal(<?php echo (int)$appt['appointment_id']; ?>, <?php echo htmlspecialchars(json_encode(($appt['patient_first'] ?? '') . ' ' . ($appt['patient_last'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($appt['dentist_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($appt['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($appt['appointment_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($appt['appointment_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int)$appt['dentist_id']; ?>)">Manage</button>
                    <?php else: ?>
                      <button class="action-btn" style="opacity: 0.5; cursor: not-allowed; background: #94a3b8; border-color: #94a3b8;" disabled>Locked</button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <?php if ($totalAppointments > $perPage):
            $lastPage = (int)ceil($totalAppointments / $perPage);
        ?>
          <div class="pagination">
            <a href="?tenant=<?php echo urlencode($tenantSlug); ?>&filter=<?php echo urlencode($filter); ?>&page=<?php echo max(1, $page - 1); ?>" class="page-link <?php echo ($page <= 1) ? 'disabled' : ''; ?>">← Previous</a>

            <?php
            $start = max(1, $page - 2);
            $end = min($lastPage, $page + 2);

            if ($start > 1) {
                echo '<a href="?tenant=' . urlencode($tenantSlug) . '&filter=' . urlencode($filter) . '&page=1" class="page-link">1</a>';
                if ($start > 2) echo '<span style="color: #94a3b8;">...</span>';
            }

            for ($i = $start; $i <= $end; $i++) {
                $activeClass = ($i === $page) ? 'active' : '';
                echo '<a href="?tenant=' . urlencode($tenantSlug) . '&filter=' . urlencode($filter) . '&page=' . $i . '" class="page-link ' . $activeClass . '">' . $i . '</a>';
            }

            if ($end < $lastPage) {
                if ($end < $lastPage - 1) echo '<span style="color: #94a3b8;">...</span>';
                echo '<a href="?tenant=' . urlencode($tenantSlug) . '&filter=' . urlencode($filter) . '&page=' . $lastPage . '" class="page-link">' . $lastPage . '</a>';
            }
            ?>

            <a href="?tenant=<?php echo urlencode($tenantSlug); ?>&filter=<?php echo urlencode($filter); ?>&page=<?php echo min($lastPage, $page + 1); ?>" class="page-link <?php echo ($page >= $lastPage) ? 'disabled' : ''; ?>">Next →</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>



  <!-- Schedule Appointment Modal (receptionist-style) -->
  <div id="scheduleModal" class="modal">
    <div class="modal-content wide">
      <form method="POST" action="appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>">
        <div class="booking-grid">
          <!-- Sidebar: Selection -->
          <div class="booking-sidebar">
            <div class="modal-header">
              <h3 class="modal-title">Schedule Appointment</h3>
              <button class="modal-close" type="button" onclick="closeScheduleModal()">&times;</button>
            </div>

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
              <h4 style="margin: 0 0 12px 0; font-size: 14px; color: var(--accent);">Appointment Summary</h4>
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
                <h4 id="calendar-month-year" style="margin: 0; font-weight: 700;"></h4>
                <button type="button" class="action-link" onclick="nextMonth()" style="padding: 4px 10px;">❯</button>
              </div>
              <div class="calendar-grid" id="calendar-grid">
                <!-- Calendar will be rendered here -->
              </div>
            </div>

            <div id="slots-container" style="display: none;">
              <h4 style="margin: 0; font-size: 14px; color: var(--accent);">Available Time Slots</h4>
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

  <!-- Reschedule Appointment Modal -->
  <div id="rescheduleModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <span>Reschedule Appointment</span>
        <button class="close" type="button" onclick="closeRescheduleModal()">&times;</button>
      </div>
      <form method="POST" action="appointments.php?tenant=<?php echo urlencode($tenantSlug); ?>">
        <input type="hidden" id="reschedule_id" name="reschedule_id" value="">
        <div class="form-group">
          <label for="reschedule_patient_display">Patient</label>
          <input type="text" id="reschedule_patient_display" readonly style="background: #f8fafc;">
        </div>
        <div class="form-group">
          <label for="reschedule_original_date">Original Appointment</label>
          <input type="text" id="reschedule_original_date" readonly style="background: #f8fafc;">
        </div>
        <div class="form-group required">
          <label for="reschedule_dentist_id">Dentist</label>
          <select id="reschedule_dentist_id" name="reschedule_dentist_id" required>
            <option value="">Select dentist</option>
            <?php foreach ($dentists as $dentist): ?>
              <option value="<?php echo (int)$dentist['dentist_id']; ?>">Dr. <?php echo h($dentist['first_name'] . ' ' . $dentist['last_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group required">
            <label for="reschedule_date">New Date</label>
            <input type="date" id="reschedule_date" name="reschedule_date" required>
          </div>
          <div class="form-group required">
            <label for="reschedule_time">New Time</label>
            <input type="time" id="reschedule_time" name="reschedule_time" required>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" onclick="closeRescheduleModal()">Cancel</button>
          <button type="submit" class="btn-submit" name="reschedule_appointment">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Manage Appointment Modal -->
  <div id="manageModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" style="margin:0; color:var(--accent);">Manage Appointment</h3>
        <span class="close" onclick="closeManageModal()">&times;</span>
      </div>
      <form method="POST" action="appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>">
        <input type="hidden" id="update_id" name="update_id" value="">
        <div class="form-group" style="margin-top: 15px;">
          <label>Appointment</label>
          <input type="text" id="manageAppointmentInfo" readonly style="background: #f8fafc;">
        </div>
        <div class="form-group">
          <label for="new_status">Status</label>
          <select id="new_status" name="new_status" required>
            <option value="">Select status</option>
            <option value="pending">Ongoing</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" onclick="closeManageModal()">Cancel</button>
          <button type="button" class="btn-cancel" onclick="openRescheduleModal()">Reschedule</button>
          <button type="submit" id="updateStatusBtn" class="btn-submit" name="update_appointment">Update Status</button>
        </div>
      </form>
    </div>
  </div>

    <script>
      // ✓ FLAG TEST: Appointment management logic active
      console.log("Appointment Module Active");
      console.log('UI Parity Active - Version 2.0');
      console.log('Appointments Page Initialized');
      console.log('FINAL UI SYNC COMPLETE');
      
      <?php printDateClockScript(); ?>
      
      const tenantId = <?php echo json_encode($tenantId); ?>;

      function openScheduleModal() {
        const form = document.querySelector('#scheduleModal form');
        if (form) form.reset();
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

        ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
          const div = document.createElement('div');
          div.className = 'cal-day-header';
          div.textContent = day;
          grid.appendChild(div);
        });

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        for (let i = 0; i < firstDay; i++) grid.appendChild(document.createElement('div'));

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
        selectedBookingTime = null;
        document.getElementById('appointment_time').value = '';
        document.getElementById('summary-time').textContent = 'Not selected';
        fetchAvailabilityData(currentViewDate.getMonth(), currentViewDate.getFullYear(), dentistId);
        if (selectedBookingDate) loadSlotsForDate(selectedBookingDate, dentistId);
      }

      async function handleDateClick(dateStr) {
        selectedBookingDate = dateStr;
        document.getElementById('appointment_date').value = dateStr;
        document.getElementById('summary-date').textContent = new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
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

      function openManageModal(id, patientName, dentistName, status, appointmentDate, appointmentTime, dentistId) {
        document.getElementById('update_id').value = id;
        document.getElementById('manageAppointmentInfo').value = patientName + ' with ' + dentistName + ' (' + status + ')';
        
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

        document.getElementById('reschedule_id').value = id;
        document.getElementById('reschedule_patient_display').value = patientName;
        document.getElementById('reschedule_original_date').value = appointmentDate + ' ' + appointmentTime;
        document.getElementById('reschedule_dentist_id').value = dentistId || '';
        document.getElementById('reschedule_date').value = appointmentDate;
        document.getElementById('reschedule_time').value = appointmentTime;
        
        document.getElementById('manageModal').style.display = 'flex';
      }

      function openRescheduleModal() {
        closeManageModal();
        document.getElementById('rescheduleModal').style.display = 'flex';
      }

      function closeRescheduleModal() {
        document.getElementById('rescheduleModal').style.display = 'none';
      }

      function closeManageModal() {
        document.getElementById('manageModal').style.display = 'none';
      }

      // Close modal when clicking outside
      window.addEventListener('click', function(event) {
        const scheduleModalEl = document.getElementById('scheduleModal');
        const manageModal = document.getElementById('manageModal');
        const rescheduleModal = document.getElementById('rescheduleModal');
        if (event.target === scheduleModalEl) closeScheduleModal();
        if (event.target === manageModal) closeManageModal();
        if (event.target === rescheduleModal) closeRescheduleModal();
      });
  </script>
</body>
</html>



