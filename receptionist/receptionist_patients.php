<?php
/**
 * ============================================
 * RECEPTIONIST PATIENT MANAGEMENT
 * Last Updated: April 4, 2026
 * Features: Patient Directory, Contact Management, View Details
 * ✓ ROLE-SPECIFIC: Receptionist-only page (no admin features)
 * ============================================
 */

// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7);
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/tenant_utils.php';

// Role Check Implementation - Ensure user is a Receptionist
if (!isset($_SESSION['role'])) {
    header("Location: /tenant_login.php");
    exit();
}

if ($_SESSION['role'] !== 'Receptionist') {
    header("Location: /tenant_login.php");
    exit();
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function formatTenantPatientId($tenant_patient_id) {
    return '#' . str_pad($tenant_patient_id, 4, '0', STR_PAD_LEFT);
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_patient'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($firstName === '' || $lastName === '' || $contactNumber === '') {
        $errorMessage = 'First name, last name, and contact number are required.';
    } else {
        // Step A: Fetch the current maximum tenant_patient_id for this tenant
        $maxIdStmt = $conn->prepare('SELECT MAX(tenant_patient_id) FROM patient WHERE tenant_id = ?');
        $maxIdStmt->bind_param('i', $tenantId);
        $maxIdStmt->execute();
        $maxIdResult = $maxIdStmt->get_result();
        $maxIdRow = $maxIdResult->fetch_assoc();
        $maxIdStmt->close();
        
        // Step B: Calculate the new tenant_patient_id
        $newTenantPatientId = ($maxIdRow['MAX(tenant_patient_id)'] ?? 0) + 1;
        
        // Step C: Include in the INSERT statement
        $insertStmt = $conn->prepare('INSERT INTO patient (tenant_id, tenant_patient_id, first_name, last_name, contact_number, email, birthdate, gender, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if ($insertStmt) {
            $insertStmt->bind_param('iisssssss', $tenantId, $newTenantPatientId, $firstName, $lastName, $contactNumber, $email, $birthdate, $gender, $address);
            if ($insertStmt->execute()) {
                $successMessage = 'Patient added successfully.';
            } else {
                $errorMessage = 'Unable to add patient. Please try again.';
            }
            $insertStmt->close();
        } else {
            $errorMessage = 'Unable to prepare patient insert statement.';
        }
    }
}

// Fetch all patients for this tenant
$patients = [];
$stmt = $conn->prepare('SELECT p.patient_id, p.tenant_patient_id, p.first_name, p.last_name, p.contact_number, p.email, p.birthdate, p.gender, MAX(a.appointment_date) as last_visit FROM patient p LEFT JOIN appointment a ON p.patient_id = a.patient_id WHERE p.tenant_id = ? GROUP BY p.patient_id, p.tenant_patient_id ORDER BY p.first_name ASC');
if ($stmt) {
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
    $stmt->close();
}

// Handle View Patient
$viewPatient = null;
if (isset($_GET['view_patient_id'])) {
    $viewPatientId = (int)$_GET['view_patient_id'];
    $stmt = $conn->prepare('SELECT * FROM patient WHERE patient_id = ? AND tenant_id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $viewPatientId, $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $viewPatient = $result->fetch_assoc();
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Patient Records</title>
    <link rel="stylesheet" href="/tenant_style.css">
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
        margin-bottom: 24px;
      }

      .search-container {
        margin-bottom: 20px;
      }

      .search-input {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
      }

      .patient-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
      }

      .patient-table th,
      .patient-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border);
        text-align: left;
        color: #334155;
      }

      .patient-table th {
        background: var(--bg);
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .patient-table tbody tr:hover {
        background: #f8fafc;
      }

      .patient-table td:last-child {
        white-space: nowrap;
      }

      .message {
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 20px;
        font-size: 14px;
      }

      .message.success {
        background: #ecfdf5;
        color: #115e59;
        border: 1px solid #a7f3d0;
      }

      .message.error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
      }

      .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.02em;
      }

      .status-paid {
        background: #d1fae5;
        color: #065f46;
      }

      .status-pending {
        background: #fef3c7;
        color: #92400e;
      }

      .form-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 16px;
      }

      .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
      }

      .form-group.required label::after {
        content: ' *';
        color: #dc2626;
      }

      .form-group input,
      .form-group select {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
      }

      .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 20px;
      }

      .btn-cancel,
      .btn-submit {
        padding: 12px 18px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
      }

      .btn-cancel {
        background: #f1f5f9;
        color: #334155;
      }

      .btn-cancel:hover {
        background: #e2e8f0;
      }

      .btn-submit {
        background: var(--accent);
        color: white;
      }

      .btn-submit:hover {
        background: #0a2d4f;
      }

      .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
      }

      .empty-icon {
        font-size: 48px;
        margin-bottom: 16px;
      }

      /* Modal Styles */
      .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
      }

      .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .modal-content {
        background: white;
        border-radius: 12px;
        padding: 32px;
        max-width: 600px;
        width: 90%;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
      }

      .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
      }

      .modal-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--accent);
      }

      .modal-close {
        font-size: 28px;
        cursor: pointer;
        color: #94a3b8;
        border: none;
        background: none;
      }

      .patient-detail-row {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 16px;
        margin-bottom: 16px;
      }

      .patient-detail-label {
        font-weight: 700;
        color: var(--accent);
        font-size: 13px;
      }

      .patient-detail-value {
        color: #475569;
        font-size: 14px;
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
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/../includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">👥 Patient Records</div>
        <div style="display: flex; align-items: center; gap: 16px;">
          <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
          <div id="liveClock" class="live-clock-badge">00:00:00 AM</div>
        </div>
      </div>

      <div class="module-card">
        <?php if ($successMessage): ?>
          <div class="message success"><?php echo h($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
          <div class="message error"><?php echo h($errorMessage); ?></div>
        <?php endif; ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 16px; flex-wrap: wrap;">
          <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Patient Directory</h2>
          <button class="btn-primary" type="button" onclick="openAddPatientModal()">+ Add Patient</button>
        </div>

        <div class="search-container">
          <input type="text" id="searchInput" placeholder="🔍 Search patient by name or contact..." class="search-input" onkeyup="filterPatients()" />
        </div>

        <div style="overflow-x:auto;">
          <table class="patient-table" id="patientTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Patient</th>
                <th>Contact</th>
                <th>Email</th>
                <th>Last Visit</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($patients)): ?>
                <tr>
                  <td colspan="6" style="text-align:center; padding: 40px; color: #94a3b8;">No patients registered in this clinic yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($patients as $patient): ?>
                  <tr data-patient-name="<?php echo strtolower($patient['first_name'] . ' ' . $patient['last_name']); ?>" data-patient-contact="<?php echo strtolower($patient['contact_number']); ?>">
                    <td><strong><?php echo h(formatTenantPatientId($patient['tenant_patient_id'])); ?></strong></td>
                    <td><strong><?php echo h(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')); ?></strong></td>
                    <td><?php echo h($patient['contact_number'] ?? 'N/A'); ?></td>
                    <td><?php echo h($patient['email'] ?? 'N/A'); ?></td>
                    <td><?php echo h($patient['last_visit'] ?? 'Never'); ?></td>
                    <td><span class="status-pill <?php echo (!empty($patient['last_visit']) && strtotime($patient['last_visit']) > strtotime('-1 year')) ? 'status-paid' : 'status-pending'; ?>"><?php echo (!empty($patient['last_visit']) && strtotime($patient['last_visit']) > strtotime('-1 year')) ? 'Active' : 'Inactive'; ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Patient Modal -->
  <div id="addPatientModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">Add Patient</h2>
        <button class="modal-close" onclick="closeAddPatientModal()">&times;</button>
      </div>
      <form method="POST" class="patient-form">
        <div class="form-row">
          <div class="form-group required">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" required>
          </div>
          <div class="form-group required">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group required">
            <label for="contact_number">Contact Number</label>
            <input type="text" id="contact_number" name="contact_number" required>
          </div>
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="birthdate">Birthdate</label>
            <input type="date" id="birthdate" name="birthdate">
          </div>
          <div class="form-group">
            <label for="gender">Gender</label>
            <select id="gender" name="gender">
              <option value="">-- Select Gender --</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label for="address">Address</label>
          <input type="text" id="address" name="address">
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" onclick="closeAddPatientModal()">Cancel</button>
          <button type="submit" class="btn-submit" name="add_patient">Save Patient</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Live Clock Update Function
    function updateClock() {
      const now = new Date();
      const timeString = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
      });
      const clockElement = document.getElementById('liveClock');
      if (clockElement) {
        clockElement.textContent = timeString;
      }
    }
    updateClock();
    setInterval(updateClock, 1000);

    function filterPatients() {
      const searchInput = document.getElementById('searchInput').value.toLowerCase();
      const rows = document.querySelectorAll('#patientTable tbody tr');

      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchInput) ? '' : 'none';
      });
    }

    function openAddPatientModal() {
      document.getElementById('addPatientModal').classList.add('active');
    }

    function closeAddPatientModal() {
      document.getElementById('addPatientModal').classList.remove('active');
    }

    // Click outside modal to close
    window.onclick = function(e) {
      const modal = document.getElementById('addPatientModal');
      if (e.target === modal) {
        modal.classList.remove('active');
      }
    }

    // Verification logs
    console.log('UI Parity Active - Version 2.0');
    console.log('Receptionist Patients Page Initialized');
  </script>
</body>
</html>


