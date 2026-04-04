<?php
/**
 * ============================================
 * DENTIST PATIENT DIRECTORY
 * Last Updated: April 4, 2026
 * Features: Patient List, Contact Management, Appointment History
 * ✓ MODERN UI: Full OralSync Template with Tailwind Styling
 * ============================================
 */

session_start();
require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Dentist' || $_SESSION['tenant_slug'] !== $tenantSlug) {
    header('Location: tenant_login.php?tenant=' . rawurlencode($tenantSlug));
    exit();
}

requireTenantLogin($tenantSlug);
$tenantName = $_SESSION['tenant_name'];
$dentistName = $_SESSION['username'] ?? 'Dentist';
$tenantId = $_SESSION['tenant_id'];
$dentistId = $_SESSION['user_id'];

$patientList = [];
$stmt = mysqli_prepare($conn, "SELECT p.patient_id, p.first_name, p.last_name, p.contact_number, p.email, p.birthdate, MAX(a.appointment_date) AS last_visit
    FROM patient p
    INNER JOIN appointment a ON p.patient_id = a.patient_id
    WHERE a.tenant_id = ? AND a.dentist_id = ?
    GROUP BY p.patient_id
    ORDER BY p.first_name ASC");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $tenantId, $dentistId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $patientList[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | My Patients</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
      :root {
        --dashboard-accent: #0d3b66;
        --dashboard-success: #10b981;
        --dashboard-warning: #f59e0b;
        --dashboard-border: #e2e8f0;
        --dashboard-bg: #f8fafc;
      }

      .page-table-card {
        background: white;
        border: 1px solid var(--dashboard-border);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
      }

      .page-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
      }

      .page-table th {
        background: var(--dashboard-bg);
        color: #64748b;
        padding: 12px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        border-bottom: 2px solid var(--dashboard-border);
      }

      .page-table td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--dashboard-border);
        font-size: 14px;
      }

      .page-table tbody tr:hover {
        background: var(--dashboard-bg);
      }

      .patient-name {
        font-weight: 700;
        color: var(--dashboard-accent);
      }

      .contact-info {
        color: #64748b;
        font-size: 13px;
      }

      .visit-badge {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
      }

      .view-btn {
        background: var(--dashboard-accent);
        color: white;
        padding: 6px 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 12px;
        text-decoration: none;
        display: inline-block;
        transition: background 0.2s;
      }

      .view-btn:hover {
        background: #0a2d4f;
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

      .search-box {
        padding: 12px 16px;
        border: 1px solid var(--dashboard-border);
        border-radius: 8px;
        width: 100%;
        max-width: 400px;
        font-size: 14px;
        margin-bottom: 16px;
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
          <div class="sidebar-section-title">Dentist</div>
          <a href="dentist_dashboard.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">📊</span>
            <span>Dashboard</span>
          </a>
        </div>

        <div class="sidebar-section">
          <div class="sidebar-section-title">Core Features</div>
          <a href="dentist_appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item">
            <span class="sidebar-nav-icon">📅</span>
            <span>My Appointments</span>
          </a>
          <a href="dentist_patients.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-nav-item active">
            <span class="sidebar-nav-icon">👥</span>
            <span>My Patients</span>
          </a>
        </div>
      </div>

      <div class="sidebar-footer">
        <a href="dentist_logout.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="sidebar-logout-btn">
          <span>🚪</span>
          <span>Sign Out</span>
        </a>
      </div>
    </nav>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <!-- Header Bar -->
      <div class="tenant-header-bar">
        <div class="tenant-header-title">My Patients</div>
        <div style="display: flex; align-items: center; gap: 16px;">
          <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
          <div id="liveClock" class="live-clock-badge">00:00:00 AM</div>
        </div>
      </div>

      <!-- Dashboard Header -->
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
          <h2 style="margin: 0; font-size: 24px; color: var(--dashboard-accent); font-weight: 900;">My Patient Directory</h2>
          <p style="margin: 8px 0 0; color: #64748b;">Patients assigned to your clinical schedule</p>
        </div>
      </div>

      <!-- Page Table Card -->
      <div class="page-table-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h3 style="margin: 0; color: var(--dashboard-accent); font-size: 18px;">Patient List</h3>
          <input type="text" id="patientSearch" class="search-box" placeholder="🔍 Search by name or contact..." onkeyup="filterTable()">
        </div>

        <?php if (empty($patientList)): ?>
          <div class="empty-state">
            <div class="empty-icon">📂</div>
            <p>You don't have any patients scheduled yet.</p>
          </div>
        <?php else: ?>
          <table class="page-table" id="patientTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Patient Name</th>
                <th>Contact</th>
                <th>Email</th>
                <th>Last Activity</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($patientList as $index => $patient): ?>
                <tr class="patient-row" data-name="<?php echo strtolower($patient['first_name'] . ' ' . $patient['last_name']); ?>" data-contact="<?php echo strtolower($patient['contact_number'] ?? ''); ?>">
                  <td style="color: #94a3b8; font-weight: 600;"><?php echo $index + 1; ?></td>
                  <td class="patient-name"><?php echo h($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                  <td class="contact-info">📞 <?php echo h($patient['contact_number'] ?? 'N/A'); ?></td>
                  <td class="contact-info">✉ <?php echo h($patient['email'] ?? 'N/A'); ?></td>
                  <td>
                    <?php if ($patient['last_visit']): ?>
                      <span class="visit-badge"><?php echo h(date('M d, Y', strtotime($patient['last_visit']))); ?></span>
                    <?php else: ?>
                      <span style="color: #94a3b8; font-size: 12px;">No record</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="dentist_patient_view.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&id=<?php echo (int)$patient['patient_id']; ?>" class="view-btn">View Details</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
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

    // Search/Filter Function
    function filterTable() {
      const searchInput = document.getElementById('patientSearch').value.toLowerCase();
      const rows = document.querySelectorAll('.patient-row');
      
      rows.forEach(row => {
        const name = row.getAttribute('data-name');
        const contact = row.getAttribute('data-contact');
        if (name.includes(searchInput) || contact.includes(searchInput)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    // Verification logs
    console.log('UI Parity Active - Version 2.0');
    console.log('Dentist Patients Page Initialized');
    console.log('FINAL UI SYNC COMPLETE');
  </script>
</body>
</html>