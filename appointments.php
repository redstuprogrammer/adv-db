<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/security_headers.php';
require_once 'connect.php';
require_once 'tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();

// Handle Add Appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_appointment'])) {
    $patientId = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $dentistId = isset($_POST['dentist_id']) ? (int)$_POST['dentist_id'] : 0;
    $appointmentDate = isset($_POST['appointment_date']) ? trim($_POST['appointment_date']) : '';
    $status = 'pending'; // Default status; user should not set this manually

    if ($patientId > 0 && $dentistId > 0 && $appointmentDate !== '') {
        $stmt = $conn->prepare('INSERT INTO appointment (tenant_id, patient_id, dentist_id, appointment_date, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('iiiss', $tenantId, $patientId, $dentistId, $appointmentDate, $status);        if ($stmt->execute()) {
            $successMsg = 'Appointment scheduled successfully!';
            logTenantActivity($conn, $tenantId, 'Appointment Created', "New appointment scheduled for patient ID: $patientId");
        }
        $stmt->close();
    }
}

// Fetch appointments
$appointments = [];
$stmt = $conn->prepare('SELECT a.appointment_id, a.patient_id, a.dentist_id, a.appointment_date, a.status, p.first_name AS patient_first, p.last_name AS patient_last, u.username AS dentist_name FROM appointment a LEFT JOIN patient p ON a.patient_id = p.patient_id LEFT JOIN users u ON a.dentist_id = u.user_id WHERE a.tenant_id = ? ORDER BY a.appointment_date DESC');
$stmt->bind_param('i', $tenantId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Appointments</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
      :root {
        --accent: #0d3b66;
        --border: #e2e8f0;
        --bg: #f8fafc;
      }

      .btn-primary {
        background: var(--accent);
        color: white;
        padding: 10px 16px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: background 0.2s ease;
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
    </style>
</head>
<body>
  <div class="tenant-layout">
    <!-- Sidebar Navigation -->
    <nav class="tenant-sidebar">
      <div class="sidebar-header">
        <div class="logo-white-box">
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="main-logo">
            <rect width="32" height="32" rx="8" fill="#0d3b66"/>
            <text x="16" y="22" font-size="20" font-weight="bold" fill="white" text-anchor="middle">O</text>
          </svg>
        </div>
        <div>
          <div class="sidebar-logo-text">OralSync</div>
          <div class="sidebar-clinic-name"><?php echo h($tenantName); ?></div>
        </div>
      </div>

      <div class="sidebar-nav">
        <div class="sidebar-section">
          <div class="sidebar-section-title">Main</div>
          <a href="tenant_dashboard.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">📊</span>
            <span>Dashboard</span>
          </a>
        </div>

        <div class="sidebar-section">
          <div class="sidebar-section-title">Core Features</div>
          <a href="patients.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">👥</span>
            <span>Patients</span>
          </a>
          <a href="appointments.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item active">
            <span class="sidebar-nav-icon">📅</span>
            <span>Appointments</span>
          </a>
          <a href="billing.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">💳</span>
            <span>Billing</span>
          </a>
        </div>

        <div class="sidebar-section">
          <div class="sidebar-section-title">Management</div>
          <a href="manage_users.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">👤</span>
            <span>Staff & Users</span>
          </a>
          <a href="tenant_reports.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">📈</span>
            <span>Reports</span>
          </a>
          <a href="tenant_settings.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">⚙️</span>
            <span>Settings</span>
          </a>
        </div>
      </div>

      <div class="sidebar-footer">
        <a href="tenant_logout.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-logout-btn">
          <span>🚪</span>
          <span>Sign Out</span>
        </a>
      </div>
    </nav>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">📅 Appointments</div>
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
      </div>

      <div class="module-card">
        <?php if (isset($successMsg)): ?>
          <div class="success-msg" style="display: block;"><?php echo h($successMsg); ?></div>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Upcoming Appointments</h2>
          <button class="btn-primary" onclick="openScheduleModal()">+ Schedule Appointment</button>
        </div>
        
        <div class="filters">
          <input type="date" placeholder="Filter by date" />
          <select>
            <option>All Status</option>
            <option>Confirmed</option>
            <option>Pending</option>
            <option>Cancelled</option>
          </select>
        </div>

        <table class="module-table">
          <thead>
            <tr>
              <th>Patient</th>
              <th>Dentist</th>
              <th>Date & Time</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($appointments)): ?>
              <tr>
                <td colspan="5" style="text-align: center; color: #64748b;">No appointments scheduled</td>
              </tr>
            <?php else: ?>
              <?php foreach ($appointments as $appt): ?>
                <tr>
                  <td><?php echo h(($appt['patient_first'] ?? '') . ' ' . ($appt['patient_last'] ?? '')); ?></td>
                  <td><?php echo h($appt['dentist_name'] ?: 'N/A'); ?></td>
                  <td><?php echo h(date('M d, Y g:i A', strtotime($appt['appointment_date']))); ?></td>
                  <td><span class="badge badge-<?php echo strtolower($appt['status']); ?>"><?php echo ucfirst($appt['status']); ?></span></td>
                  <td>
                    <a href="#" class="action-btn" onclick="alert('View appointment - coming soon'); return false;">View</a>
                    <a href="#" class="action-btn" onclick="alert('Edit appointment - coming soon'); return false;">Edit</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Schedule Appointment Modal -->
  <div id="scheduleModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeScheduleModal()">&times;</span>
      <div class="modal-header">Schedule Appointment</div>
      <form method="POST">
        <div class="form-group">
          <label>Patient *</label>
          <select name="patient_id" required>
            <option value="">Select Patient</option>
            <?php
            $patientStmt = $conn->prepare('SELECT patient_id, first_name, last_name FROM patient WHERE tenant_id = ? ORDER BY first_name');
            $patientStmt->bind_param('i', $tenantId);
            $patientStmt->execute();
            $patientResult = $patientStmt->get_result();
            while ($p = $patientResult->fetch_assoc()) {
                echo '<option value="' . $p['patient_id'] . '">' . h($p['first_name'] . ' ' . $p['last_name']) . '</option>';
            }
            $patientStmt->close();
            ?>
          </select>
        </div>
        <div class="form-group">
          <label>Dentist *</label>
          <select name="dentist_id" required>
            <option value="">Select Dentist</option>
            <?php
            $dentistStmt = $conn->prepare('SELECT user_id, username FROM users WHERE tenant_id = ? AND LOWER(role) IN ("dentist", "doctor") ORDER BY username');
            if ($dentistStmt) {
                $dentistStmt->bind_param('i', $tenantId);
                $dentistStmt->execute();
                $dentistResult = $dentistStmt->get_result();
                while ($d = $dentistResult->fetch_assoc()) {
                    echo '<option value="' . (int)$d['user_id'] . '">' . h($d['username']) . '</option>';
                }
                $dentistStmt->close();
            }
            ?>
          </select>
        </div>
        <div class="form-group">
          <label>Appointment Date & Time *</label>
          <input type="datetime-local" name="appointment_date" required>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" onclick="closeScheduleModal()">Cancel</button>
          <button type="submit" name="add_appointment" class="btn-submit">Schedule</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openScheduleModal() {
      const dateInput = document.querySelector('input[name="appointment_date"]');
      if (dateInput) {
        const now = new Date();
        const localIso = new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0,16);
        dateInput.min = localIso;
      }
      document.getElementById('scheduleModal').style.display = 'block';
    }

    function closeScheduleModal() {
      document.getElementById('scheduleModal').style.display = 'none';
    }

    window.onclick = function(event) {
      const modal = document.getElementById('scheduleModal');
      if (event.target == modal) {
        modal.style.display = 'none';
      }
    }
  </script>
</body>
</html>
