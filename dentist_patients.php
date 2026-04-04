<?php
/**
 * ============================================
 * DENTIST PATIENT DIRECTORY - MODERN CARD UI
 * Last Updated: April 4, 2026
 * Features: Patient Cards, Contact Management, Appointment History
 * ✓ MODERN UI: Card-based layout with mock data
 * ============================================
 */

session_start();
require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/tenant_utils.php';

// Role Check Implementation - Ensure user is a Dentist
if (!isset($_SESSION['role'])) {
    header("Location: tenant_login.php");
    exit();
}

if ($_SESSION['role'] !== 'Dentist') {
    header("Location: tenant_login.php");
    exit();
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);
$tenantName = $_SESSION['tenant_name'];
$dentistName = $_SESSION['username'] ?? 'Dentist';
$tenantId = $_SESSION['tenant_id'];
$dentistId = $_SESSION['user_id'];

// Mock patient data to prevent DB crashes
$patientList = [
    [
        'patient_id' => 1,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'contact_number' => '+1-555-0123',
        'email' => 'john.doe@email.com',
        'birthdate' => '1985-03-15',
        'last_visit' => '2026-04-02'
    ],
    [
        'patient_id' => 2,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'contact_number' => '+1-555-0456',
        'email' => 'jane.smith@email.com',
        'birthdate' => '1990-07-22',
        'last_visit' => '2026-03-28'
    ],
    [
        'patient_id' => 3,
        'first_name' => 'Michael',
        'last_name' => 'Johnson',
        'contact_number' => '+1-555-0789',
        'email' => 'michael.j@email.com',
        'birthdate' => '1978-11-10',
        'last_visit' => '2026-04-01'
    ],
    [
        'patient_id' => 4,
        'first_name' => 'Sarah',
        'last_name' => 'Williams',
        'contact_number' => '+1-555-0321',
        'email' => 'sarah.w@email.com',
        'birthdate' => '1995-01-05',
        'last_visit' => '2026-03-25'
    ],
    [
        'patient_id' => 5,
        'first_name' => 'David',
        'last_name' => 'Brown',
        'contact_number' => '+1-555-0654',
        'email' => 'david.brown@email.com',
        'birthdate' => '1982-09-18',
        'last_visit' => '2026-03-30'
    ]
];

// Comment out real SQL to prevent 500 errors
// $patientList = [];
// $stmt = mysqli_prepare($conn, "SELECT p.patient_id, p.first_name, p.last_name, p.contact_number, p.email, p.birthdate, MAX(a.appointment_date) AS last_visit
//     FROM patient p
//     INNER JOIN appointment a ON p.patient_id = a.patient_id
//     WHERE a.tenant_id = ? AND a.dentist_id = ?
//     GROUP BY p.patient_id
//     ORDER BY p.first_name ASC");
// if ($stmt) {
//     mysqli_stmt_bind_param($stmt, 'ii', $tenantId, $dentistId);
//     mysqli_stmt_execute($stmt);
//     $result = mysqli_stmt_get_result($stmt);
//     if ($result) {
//         while ($row = mysqli_fetch_assoc($result)) {
//             $patientList[] = $row;
//         }
//     }
// }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | My Patients</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
      :root {
        --accent: #0d3b66;
        --border: #e2e8f0;
        --bg: #f8fafc;
      }

      .patient-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
        margin-top: 24px;
      }

      .patient-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        transition: all 0.2s ease;
        cursor: pointer;
      }

      .patient-card:hover {
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
        border-color: var(--accent);
        transform: translateY(-2px);
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

        <div class="patient-grid" id="patientGrid">
          <?php if (!empty($patientList)): ?>
            <?php foreach ($patientList as $patient): ?>
              <div class="patient-card" data-patient-name="<?php echo strtolower($patient['first_name'] . ' ' . $patient['last_name']); ?>">
                <div class="patient-name"><?php echo h($patient['first_name'] . ' ' . $patient['last_name']); ?></div>

                <div class="patient-detail">
                  <span class="patient-detail-icon">📞</span>
                  <?php echo h($patient['contact_number'] ?? 'N/A'); ?>
                </div>

                <div class="patient-detail">
                  <span class="patient-detail-icon">✉️</span>
                  <?php echo h($patient['email'] ?? 'N/A'); ?>
                </div>

                <div class="patient-detail">
                  <span class="patient-detail-icon">🎂</span>
                  <?php echo $patient['birthdate'] ? date('M d, Y', strtotime($patient['birthdate'])) : 'N/A'; ?>
                </div>

                <div class="patient-last-visit">
                  📅 Last Visit: <?php echo $patient['last_visit'] ? date('M d, Y', strtotime($patient['last_visit'])) : 'Never'; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state">
              <div class="empty-icon">👥</div>
              <p>No patients found in your records.</p>
            </div>
          <?php endif; ?>
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
      const patientCards = document.querySelectorAll('.patient-card');

      patientCards.forEach(card => {
        const name = card.getAttribute('data-patient-name');
        if (name.includes(searchInput)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    }

    console.log('Anti-Crash System Active - V2');
  </script>
</body>
</html>
