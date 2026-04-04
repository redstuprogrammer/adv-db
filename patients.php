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

// Fetch all patients for this tenant
$patients = [];
$stmt = $conn->prepare('SELECT patient_id, first_name, last_name, contact_number, email, birthdate, gender FROM patient WHERE tenant_id = ? ORDER BY first_name ASC');
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
    <title><?php echo h($tenantName); ?> | Patients</title>
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

      .search-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
      }

      .search-bar input {
        flex: 1;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 13px;
      }

      .patient-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-height: 600px;
        overflow-y: auto;
      }

      .patient-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
      }

      .patient-item:hover {
        background: white;
        border-color: var(--accent);
        box-shadow: 0 2px 8px rgba(13, 59, 102, 0.1);
      }

      .patient-info h3 {
        margin: 0;
        font-size: 14px;
        color: var(--accent);
        font-weight: 600;
      }

      .patient-info p {
        margin: 4px 0 0 0;
        font-size: 12px;
        color: #64748b;
      }

      .patient-actions {
        display: flex;
        gap: 8px;
      }

      .action-btn {
        padding: 6px 12px;
        background: var(--accent);
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.2s ease;
      }

      .action-btn:hover {
        background: #0a2d4f;
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
        overflow-y: auto;
      }

      .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid var(--border);
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
      }

      .modal-header {
        font-size: 18px;
        font-weight: 700;
        color: var(--accent);
        margin-bottom: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
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

      .form-group.required label::after {
        content: ' *';
        color: red;
      }

      .form-group input,
      .form-group select,
      .form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 13px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        box-sizing: border-box;
      }

      .form-group textarea {
        resize: vertical;
        min-height: 60px;
      }

      .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
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
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        background: none;
        border: none;
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

      .error-msg {
        display: none;
        padding: 12px;
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border-radius: 8px;
        margin-bottom: 16px;
      }

      .empty-state {
        text-align: center;
        padding: 32px;
        color: #64748b;
      }

      .patient-detail-row {
        display: flex;
        justify-content: space-between;
        padding: 12px;
        border-bottom: 1px solid var(--border);
      }

      .patient-detail-label {
        font-weight: 700;
        color: var(--accent);
        font-size: 13px;
      }

      .patient-detail-value {
        font-size: 13px;
        color: #64748b;
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
          <a href="patients.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item active">
            <span class="sidebar-nav-icon">👥</span>
            <span>Patients</span>
          </a>
          <a href="appointments.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="sidebar-nav-item">
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
            <span>Staff Management</span>
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
        <div class="tenant-header-title">👥 Patients</div>
        <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
      </div>

      <div class="module-card">
        <?php if (isset($successMsg)): ?>
          <div class="success-msg" style="display: block;"><?php echo h($successMsg); ?></div>
        <?php endif; ?>
        <?php if (isset($errorMsg)): ?>
          <div class="error-msg" style="display: block;"><?php echo h($errorMsg); ?></div>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Patient Directory</h2>
        </div>

        <div class="search-bar">
          <input type="text" id="searchInput" placeholder="Search patient by name or ID..." onkeyup="filterPatients()" />
        </div>

        <div class="patient-list" id="patientList">
          <?php if (empty($patients)): ?>
            <div class="empty-state">
              <p>No patients registered yet.</p>
            </div>
          <?php else: ?>
            <?php foreach ($patients as $patient):
                  $birthdate = $patient['birthdate'] ?? '';
                  $age = ($birthdate && strtotime($birthdate)) ? floor((time() - strtotime($birthdate)) / (365.25 * 24 * 3600)) : 'N/A';
                  $gender = !empty($patient['gender']) ? h($patient['gender']) : 'N/A';
            ?>
              <div class="patient-item" data-patient-name="<?php echo strtolower(h($patient['first_name'] . ' ' . $patient['last_name'])); ?>">
                <div class="patient-info">
                  <h3><?php echo h($patient['first_name'] . ' ' . $patient['last_name']); ?></h3>
                  <p>Age: <?php echo $age; ?> | Gender: <?php echo $gender; ?> | Phone: <?php echo h($patient['contact_number']); ?></p>
                </div>
                <div class="patient-actions">
                  <a href="patients.php?tenant=<?php echo urlencode($tenantSlug); ?>&view_patient_id=<?php echo $patient['patient_id']; ?>" class="action-btn">View</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- View Patient Modal (if viewing) -->
  <?php if ($viewPatient): ?>
  <div id="viewPatientModal" class="modal" style="display: block;">
    <div class="modal-content">
      <div class="modal-header">
        <span><?php echo h($viewPatient['first_name'] . ' ' . $viewPatient['last_name']); ?> - Patient Details</span>
        <button class="close" onclick="closeViewPatientModal()">&times;</button>
      </div>
      <div style="max-height: 500px; overflow-y: auto;">
        <div class="patient-detail-row">
          <div class="patient-detail-label">Patient ID:</div>
          <div class="patient-detail-value">P<?php echo str_pad($viewPatient['patient_id'], 3, '0', STR_PAD_LEFT); ?></div>
        </div>
        <div class="patient-detail-row">
          <div class="patient-detail-label">Name:</div>
          <div class="patient-detail-value"><?php echo h($viewPatient['first_name'] . ' ' . $viewPatient['last_name']); ?></div>
        </div>
        <div class="patient-detail-row">
          <div class="patient-detail-label">Contact Number:</div>
          <div class="patient-detail-value"><?php echo h($viewPatient['contact_number']); ?></div>
        </div>
        <div class="patient-detail-row">
          <div class="patient-detail-label">Email:</div>
          <div class="patient-detail-value"><?php echo h($viewPatient['email'] ?? 'N/A'); ?></div>
        </div>
        <div class="patient-detail-row">
          <div class="patient-detail-label">Username (for mobile app):</div>
          <div class="patient-detail-value"><?php echo h($viewPatient['username'] ?? 'N/A'); ?></div>
        </div>
        <div class="patient-detail-row">
          <div class="patient-detail-label">Address:</div>
          <div class="patient-detail-value"><?php echo h($viewPatient['address'] ?? 'N/A'); ?></div>
        </div>
        <div class="patient-detail-row">
          <div class="patient-detail-label">Birthdate:</div>
          <div class="patient-detail-value"><?php echo h($viewPatient['birthdate'] ?? 'N/A'); ?></div>
        </div>
        <div class="patient-detail-row">
          <div class="patient-detail-label">Gender:</div>
          <div class="patient-detail-value"><?php echo h($viewPatient['gender'] ?? 'N/A'); ?></div>
        </div>
        <div class="patient-detail-row">
          <div class="patient-detail-label">Occupation:</div>
          <div class="patient-detail-value"><?php echo h($viewPatient['occupation'] ?? 'N/A'); ?></div>
        </div>
        <div class="patient-detail-row">
          <div class="patient-detail-label">Medical History:</div>
          <div class="patient-detail-value"><?php echo h($viewPatient['medical_history'] ?? 'N/A'); ?></div>
        </div>
        <div class="patient-detail-row">
          <div class="patient-detail-label">Allergies:</div>
          <div class="patient-detail-value"><?php echo h($viewPatient['allergies'] ?? 'N/A'); ?></div>
        </div>
        <div class="patient-detail-row">
          <div class="patient-detail-label">Notes:</div>
          <div class="patient-detail-value"><?php echo h($viewPatient['notes'] ?? 'N/A'); ?></div>
        </div>
      </div>
      <div class="form-actions">
        <a href="patients.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="btn-cancel" style="text-decoration: none; text-align: center; padding: 10px;">Back to Patients</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script>
    function closeViewPatientModal() {
      window.location.href = 'patients.php?tenant=<?php echo urlencode($tenantSlug); ?>';
    }

    function filterPatients() {
      const searchInput = document.getElementById('searchInput').value.toLowerCase();
      const patientItems = document.querySelectorAll('.patient-item');
      
      patientItems.forEach(item => {
        const patientName = item.getAttribute('data-patient-name');
        if (patientName.includes(searchInput)) {
          item.style.display = 'flex';
        } else {
          item.style.display = 'none';
        }
      });
    }
  </script>
</body>
</html>
