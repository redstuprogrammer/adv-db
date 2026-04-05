<?php
session_start();
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
$patientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Dentist' || $_SESSION['tenant_slug'] !== $tenantSlug) {
    header('Location: /tenant_login.php?tenant=' . rawurlencode($tenantSlug));
    exit();
}

requireTenantLogin($tenantSlug);
$tenantName = $_SESSION['tenant_name'];
$dentistName = $_SESSION['username'] ?? 'Dentist';
$tenantId = $_SESSION['tenant_id'];

$patient = null;
$stmt = mysqli_prepare($conn, "SELECT p.*, MAX(a.appointment_date) as last_visit FROM patient p JOIN appointment a ON p.patient_id = a.patient_id WHERE p.patient_id = ? AND a.tenant_id = ? AND a.dentist_id = ? GROUP BY p.patient_id LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'iii', $patientId, $tenantId, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        $patient = mysqli_fetch_assoc($result);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Patient Detail</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        body { background: #f8fafc; color: #0f172a; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; }
        .page-shell { display: flex; min-height: 100vh; }
        .page-sidebar { width: 260px; background: #0d3b66; color: white; padding: 24px; }
        .page-sidebar h2 { margin: 0 0 16px; font-size: 18px; }
        .menu-item { display: block; color: rgba(255,255,255,0.9); text-decoration: none; margin-bottom: 10px; padding: 10px 14px; border-radius: 8px; }
        .menu-item:hover { background: rgba(255,255,255,0.1); color: #ffffff; }
        .page-content { flex: 1; padding: 24px; }
        .page-title { margin-top: 0; font-size: 28px; }
        .detail-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 12px 30px rgba(15,23,42,0.08); }
        .detail-row { display: grid; grid-template-columns: 1fr 2fr; gap: 16px; margin-bottom: 14px; }
        .detail-label { font-weight: 700; color: #475569; }
    </style>
</head>
<body>
<div class="page-shell">
    <aside class="page-sidebar">
        <h2>OralSync Dentist</h2>
        <a href="dentist_dashboard.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="menu-item">📊 Dashboard</a>
        <a href="dentist_appointments.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="menu-item">📅 Appointments</a>
        <a href="dentist_patients.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="menu-item">👤 My Patients</a>
        <a href="dentist_logout.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="menu-item">🚪 Logout</a>
    </aside>
    <main class="page-content">
        <h1 class="page-title">Patient Detail</h1>
        <p style="color: #64748b; margin-top: 0;">Information for the patient assigned to your care.</p>
        <div class="detail-card">
            <?php if (!$patient): ?>
                <p style="padding: 40px; text-align: center; color: #64748b;">Patient not found or you do not have access.</p>
            <?php else: ?>
                <div class="detail-row"><div class="detail-label">Name</div><div><?php echo h($patient['first_name'] . ' ' . $patient['last_name']); ?></div></div>
                <div class="detail-row"><div class="detail-label">Contact</div><div><?php echo h($patient['contact_number']); ?></div></div>
                <div class="detail-row"><div class="detail-label">Email</div><div><?php echo h($patient['email'] ?? 'N/A'); ?></div></div>
                <div class="detail-row"><div class="detail-label">Last Visit</div><div><?php echo $patient['last_visit'] ? h(date('M d, Y', strtotime($patient['last_visit']))) : 'No record'; ?></div></div>
                <div class="detail-row"><div class="detail-label">Gender</div><div><?php echo h($patient['gender'] ?? 'N/A'); ?></div></div>
                <div class="detail-row"><div class="detail-label">Birthdate</div><div><?php echo h($patient['birthdate'] ?? 'N/A'); ?></div></div>
            <?php endif; ?>
            <div style="margin-top: 24px;"><a href="dentist_patients.php?tenant=<?php echo urlencode($tenantSlug); ?>" style="color: #0d3b66; text-decoration: none; font-weight: 700;">← Back to My Patients</a></div>
        </div>
    </main>
</div>
</body>
</html>

