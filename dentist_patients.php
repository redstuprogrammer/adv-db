<?php
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
$stmt = mysqli_prepare($conn, "SELECT p.patient_id, p.first_name, p.last_name, p.contact_number, MAX(a.appointment_date) AS last_visit
    FROM patient p
    INNER JOIN appointment a ON p.patient_id = a.patient_id
    WHERE a.tenant_id = ? AND a.dentist_id = ?
    GROUP BY p.patient_id
    ORDER BY p.last_name ASC");

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
    <link rel="stylesheet" href="style1.css">
    <style>
        body { background: #f8fafc; color: #0f172a; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; }
        .page-shell { display: flex; min-height: 100vh; }
        .page-sidebar { width: 260px; background: #0d3b66; color: white; padding: 24px; }
        .page-sidebar h2 { margin: 0 0 16px; font-size: 18px; }
        .menu-item { display: block; color: rgba(255,255,255,0.9); text-decoration: none; margin-bottom: 10px; padding: 10px 14px; border-radius: 8px; }
        .menu-item.active, .menu-item:hover { background: rgba(255,255,255,0.1); color: #ffffff; }
        .page-content { flex: 1; padding: 24px; }
        .page-title { margin-top: 0; font-size: 28px; }
        .table-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 12px 30px rgba(15,23,42,0.08); }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .data-table th, .data-table td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; }
        .data-table th { text-align: left; background: #f1f5f9; }
        .clickable-row { cursor: pointer; }
        .clickable-row:hover { background: #f8fafc; }
        .status-pill { display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; color: #0d3b66; background: #dbeafe; }
        .top-row { display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap; }
        .top-row p { margin: 0; color: #64748b; }
        
        .live-clock-badge {
          background: linear-gradient(135deg, rgba(13, 59, 102, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
          border: 2px solid #0d3b66;
          padding: 8px 16px;
          border-radius: 20px;
          font-size: 14px;
          font-weight: 700;
          color: #0d3b66;
          font-family: 'Courier New', monospace;
          letter-spacing: 1px;
          white-space: nowrap;
        }
        
        .header-time-row {
          display: flex;
          align-items: center;
          gap: 16px;
        }
    </style>
</head>
<body>
<div class="page-shell">
    <aside class="page-sidebar">
        <h2>OralSync Dentist</h2>
        <a href="dentist_dashboard.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="menu-item">📊 Dashboard</a>
        <a href="dentist_appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="menu-item">📅 Appointments</a>
        <a href="dentist_patients.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="menu-item active">👤 My Patients</a>
        <a href="dentist_logout.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="menu-item">🚪 Logout</a>
    </aside>

    <main class="page-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <div>
            <h2 style="color: #0d3b66; font-size: 20px; margin: 0;">My Patients</h2>
            <p style="color: #64748b; margin: 0; font-size: 13px;">Patients assigned to Dr. <?php echo h($dentistName); ?></p>
          </div>
          <div id="liveClock" class="live-clock-badge">00:00:00 AM</div>
        </div>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient Name</th>
                        <th>Contact</th>
                        <th>Last Visit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($patientList) === 0): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding: 40px; color: #64748b;">No patients found for your schedule.</td>
                        </tr>
                    <?php else: ?>
                        <?php $count = 1; foreach ($patientList as $patient): ?>
                            <tr class="clickable-row" onclick="location.href='dentist_patient_view.php?id=<?php echo (int)$patient['patient_id']; ?>&tenant=<?php echo urlencode($tenantSlug); ?>'">
                                <td><?php echo $count++; ?></td>
                                <td><?php echo h($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                <td><?php echo h($patient['contact_number']); ?></td>
                                <td><?php echo $patient['last_visit'] ? h(date('M d, Y', strtotime($patient['last_visit']))) : 'No record'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
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
  
  // Verification logs
  console.log('UI Parity Active - Version 2.0');
  console.log('Dentist Patients Page Initialized');
</script>
</body>
</html>