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
require_once __DIR__ . '/security_headers.php';
require_once 'connect.php';
require_once 'tenant_utils.php';

// Role Check Implementation - Ensure user is a Receptionist
if (!isset($_SESSION['role'])) {
    header("Location: tenant_login.php");
    exit();
}

if ($_SESSION['role'] !== 'Receptionist') {
    header("Location: tenant_login.php");
    exit();
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();

// Fetch all patients for this tenant
$patients = [];
$stmt = $conn->prepare('SELECT p.patient_id, p.first_name, p.last_name, p.contact_number, p.email, p.birthdate, p.gender, MAX(a.appointment_date) as last_visit FROM patient p LEFT JOIN appointment a ON p.patient_id = a.patient_id WHERE p.tenant_id = ? GROUP BY p.patient_id ORDER BY p.first_name ASC');
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

      .patient-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 16px;
      }

      .patient-item {
        background: white;
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 16px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
      }

      .patient-item:hover {
        box-shadow: 0 4px 12px rgba(13, 59, 102, 0.15);
        border-color: var(--accent);
      }

      .patient-name {
        font-weight: 700;
        font-size: 16px;
        color: var(--accent);
        margin-bottom: 8px;
      }

      .patient-contact {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 4px;
      }

      .patient-email {
        font-size: 12px;
        color: #94a3b8;
        margin-bottom: 12px;
      }

      .patient-actions {
        display: flex;
        gap: 8px;
        margin-top: auto;
      }

      .patient-actions button {
        flex: 1;
        padding: 6px 12px;
        border: 1px solid var(--accent);
        background: white;
        color: var(--accent);
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 12px;
        transition: all 0.2s ease;
      }

      .patient-actions button:hover {
        background: var(--accent);
        color: white;
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
    <!-- Sidebar Navigation -->
    <nav class="tenant-sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo">
          <div class="sidebar-logo-icon">🏥</div>
          <div>
            <div class="sidebar-logo-text">OralSync</div>
            <div class="sidebar-clinic-name"><?php echo h($tenantName); ?></div>
          </div>
        </div>
      </div>

      <div class="sidebar-nav">
        <div class="sidebar-section">
          <div class="sidebar-section-title">Front Desk</div>
          <a href="receptionist_dashboard.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">📊</span>
            <span>Dashboard</span>
          </a>
        </div>

        <div class="sidebar-section">
          <div class="sidebar-section-title">Core Features</div>
          <a href="receptionist_appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">📅</span>
            <span>Appointments</span>
          </a>
          <a href="receptionist_patients.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item active">
            <span class="sidebar-nav-icon">👥</span>
            <span>Patients</span>
          </a>
          <a href="receptionist_billing.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">💳</span>
            <span>Billing</span>
          </a>
        </div>
      </div>

      <div class="sidebar-footer">
        <a href="receptionist_logout.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-logout-btn">
          <span>🚪</span>
          <span>Sign Out</span>
        </a>
      </div>
    </nav>

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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Patient Directory</h2>
          <span style="background: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase;">👁️ View Only</span>
        </div>

        <div class="search-container">
          <input type="text" id="searchInput" placeholder="🔍 Search patient by name or contact..." class="search-input" onkeyup="filterPatients()" />
        </div>

        <div class="patient-list" id="patientList">
          <?php if (empty($patients)): ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
              <div class="empty-icon">📂</div>
              <p>No patients registered in this clinic yet.</p>
            </div>
          <?php else: ?>
            <?php foreach ($patients as $patient): ?>
              <div class="patient-item" data-patient-name="<?php echo strtolower($patient['first_name'] . ' ' . $patient['last_name']); ?>" data-patient-contact="<?php echo strtolower($patient['contact_number']); ?>">
                <div class="patient-name"><?php echo h(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')); ?></div>
                <div class="patient-contact">📞 <?php echo h($patient['contact_number'] ?? 'N/A'); ?></div>
                <div class="patient-email">✉ <?php echo h($patient['email'] ?? 'N/A'); ?></div>
                <div class="patient-last-visit">Last Visit: <?php echo h($patient['last_visit'] ?? 'Never'); ?></div>
                <div class="patient-actions">
                  <button onclick="viewPatient(<?php echo (int)$patient['patient_id']; ?>)">View Details</button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- View Patient Modal -->
  <div id="viewPatientModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">Patient Details</h2>
        <button class="modal-close" onclick="closeModal()">&times;</button>
      </div>
      <div id="patientDetailsContainer">
        <!-- Patient details will be loaded here -->
      </div>
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
      const patientItems = document.querySelectorAll('.patient-item');
      
      patientItems.forEach(item => {
        const name = item.getAttribute('data-patient-name');
        const contact = item.getAttribute('data-patient-contact');
        if (name.includes(searchInput) || contact.includes(searchInput)) {
          item.style.display = 'flex';
        } else {
          item.style.display = 'none';
        }
      });
    }

    function viewPatient(patientId) {
      // Fetch patient details via AJAX
      fetch('get_patient_details.php?tenant=<?php echo urlencode($tenantSlug); ?>&patient_id=' + patientId)
        .then(response => response.json())
        .then(data => {
          let html = '';
          if (data.success) {
            const p = data.patient;
            html = `
              <div class="patient-detail-row">
                <div class="patient-detail-label">Name</div>
                <div class="patient-detail-value">${p.first_name} ${p.last_name}</div>
              </div>
              <div class="patient-detail-row">
                <div class="patient-detail-label">Contact</div>
                <div class="patient-detail-value">${p.contact_number || 'N/A'}</div>
              </div>
              <div class="patient-detail-row">
                <div class="patient-detail-label">Email</div>
                <div class="patient-detail-value">${p.email || 'N/A'}</div>
              </div>
              <div class="patient-detail-row">
                <div class="patient-detail-label">Birthdate</div>
                <div class="patient-detail-value">${p.birthdate || 'N/A'}</div>
              </div>
              <div class="patient-detail-row">
                <div class="patient-detail-label">Gender</div>
                <div class="patient-detail-value">${p.gender || 'N/A'}</div>
              </div>
            `;
          } else {
            html = '<p style="color: #ef4444;">Unable to load patient details.</p>';
          }
          document.getElementById('patientDetailsContainer').innerHTML = html;
          document.getElementById('viewPatientModal').classList.add('active');
        })
        .catch(err => {
          console.error('Error:', err);
          document.getElementById('patientDetailsContainer').innerHTML = '<p style="color: #ef4444;">Error loading patient details.</p>';
        });
    }

    function closeModal() {
      document.getElementById('viewPatientModal').classList.remove('active');
    }

    // Click outside modal to close
    window.onclick = function(e) {
      const modal = document.getElementById('viewPatientModal');
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
