<?php
/**
 * ============================================
 * DENTIST PATIENT DIRECTORY - MODERN CARD UI
 * Last Updated: April 4, 2026
 * Features: Patient Cards, Contact Management, Appointment History
 * ✓ MODERN UI: Card-based layout
 * ============================================
 */

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

// Role Check Implementation - Ensure user is a Dentist
if (!isset($_SESSION['role'])) {
    header("Location: /tenant_login.php");
    exit();
}

if ($_SESSION['role'] !== 'Dentist') {
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
$tenantName = $_SESSION['tenant_name'];
$dentistName = $_SESSION['username'] ?? 'Dentist';
$tenantId = $_SESSION['tenant_id'];
$dentistId = $_SESSION['user_id'];

$patientList = [];
$stmt = mysqli_prepare($conn, "SELECT p.patient_id, p.tenant_patient_id, p.first_name, p.last_name, p.contact_number, p.email, p.birthdate, MAX(a.appointment_date) AS last_visit
    FROM patient p
    INNER JOIN appointment a ON p.patient_id = a.patient_id
    WHERE a.tenant_id = ? AND a.dentist_id = ?
    GROUP BY p.patient_id, p.tenant_patient_id
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
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | My Patients</title>
    <link rel="stylesheet" href="/tenant_style.css">
    <style>
      :root {
        --accent: #0d3b66;
        --border: #e2e8f0;
        --bg: #f8fafc;
      }

      .patient-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 24px;
      }

      .patient-table th,
      .patient-table td {
        padding: 14px 16px;
        text-align: left;
        border-bottom: 1px solid var(--border);
        color: #334155;
      }

      .patient-table th {
        background: #f8fafc;
        color: var(--accent);
        font-weight: 700;
        font-size: 13px;
      }

      .patient-table tbody tr:hover {
        background: #eef2ff;
      }

      .patient-name {
        font-size: 18px;
        font-weight: 700;
        color: var(--accent);
        margin-bottom: 12px;
      }

      .patient-detail {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
        font-size: 14px;
        color: #64748b;
      }

      .patient-detail-icon {
        width: 16px;
        margin-right: 8px;
        opacity: 0.7;
      }

      .patient-last-visit {
        background: #f1f5f9;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        color: #475569;
        margin-top: 12px;
        display: inline-block;
      }

      .search-container {
        margin-bottom: 24px;
      }

      .search-input {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
        max-width: 400px;
      }

      .patient-table {
        width: 100%;
        border-collapse: collapse;
      }

      .patient-table th,
      .patient-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
        color: #334155;
      }

      .patient-table th {
        background: var(--bg);
        color: var(--accent);
        font-size: 13px;
        font-weight: 700;
      }

      .patient-table tbody tr:hover {
        background: #f8fafc;
      }

      .patient-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 16px;
        margin-top: 16px;
      }

      .patient-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
        transition: all 0.2s ease;
      }

      .patient-card:hover {
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.12);
        border-color: var(--accent);
      }

      .patient-card-header {
        margin-bottom: 16px;
      }

      .patient-name {
        font-size: 18px;
        font-weight: 700;
        color: var(--accent);
      }

      .patient-card-body {
        display: flex;
        flex-direction: column;
        gap: 16px;
      }

      .patient-info {
        display: flex;
        flex-direction: column;
        gap: 8px;
      }

      .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
      }

      .info-label {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .info-value {
        font-size: 14px;
        color: #102a43;
        font-weight: 500;
      }

      .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
        grid-column: 1 / -1;
      }

      .empty-icon {
        font-size: 48px;
        margin-bottom: 16px;
      }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">👥 My Patients</div>
        <div style="display: flex; align-items: center; gap: 16px;">
          <div class="tenant-header-date text-xl font-bold"><?php echo date('l, M d, Y'); ?></div>
          <div id="liveClock" class="live-clock-badge text-xl font-bold">00:00:00 AM</div>
        </div>
      </div>

      <div class="module-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2 style="margin: 0; color: var(--accent); font-size: 16px;">Patient Directory</h2>
        </div>

        <div class="search-container">
          <input type="text" id="searchInput" placeholder="🔍 Search patients by name..." class="search-input" onkeyup="filterPatients()" />
        </div>

        <div style="overflow-x:auto;">
          <table class="patient-table" id="patientGrid">
            <thead>
              <tr>
                <th>ID</th>
                <th>Patient</th>
                <th>Contact</th>
                <th>Email</th>
                <th>Birthdate</th>
                <th>Last Visit</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($patientList)): ?>
                <?php foreach ($patientList as $patient):
                  $lastVisit = $patient['last_visit'] ? date('M d, Y', strtotime($patient['last_visit'])) : 'Never';
                  $isActive = ($patient['last_visit'] && strtotime($patient['last_visit']) > strtotime('-1 year'));
                ?>
                  <tr data-patient-name="<?php echo strtolower($patient['first_name'] . ' ' . $patient['last_name']); ?>">
                    <td><?php echo h(formatTenantPatientId($patient['tenant_patient_id'])); ?></td>
                    <td><strong><?php echo h($patient['first_name'] . ' ' . $patient['last_name']); ?></strong></td>
                    <td><?php echo h($patient['contact_number'] ?? 'N/A'); ?></td>
                    <td><?php echo h($patient['email'] ?? 'N/A'); ?></td>
                    <td><?php echo $patient['birthdate'] ? date('M d, Y', strtotime($patient['birthdate'])) : 'N/A'; ?></td>
                    <td><?php echo $lastVisit; ?></td>
                    <td><span class="status-pill <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>"><?php echo $isActive ? 'Active' : 'Inactive'; ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="7" style="text-align:center; padding: 40px; color: #64748b;">No patients found in your records.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
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
      const rows = document.querySelectorAll('#patientGrid tbody tr');

      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchInput) ? '' : 'none';
      });
    }

    console.log('Anti-Crash System Active - V2');
  </script>
</body>
</html>


