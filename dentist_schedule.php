<?php
/**
 * DENTIST SCHEDULE MANAGEMENT
 * Allows dentists to view and manage their personal schedule
 */

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

// Role Check Implementation - Ensure user is logged in as dentist
$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('dentist');

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function baseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
$tenantData = $sessionManager->getTenantData();
$tenantName = $tenantData['tenant_name'] ?? '';
$tenantId = $sessionManager->getTenantId();
$dentistId = $sessionManager->getUserId();
$dentistName = $sessionManager->getUsername() ?? 'Doctor';

// Get dentist's appointments for today and upcoming
$today = date('Y-m-d');
$upcomingAppointments = [];

try {
    $stmt = mysqli_prepare($conn, "
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, a.notes,
               p.first_name, p.last_name, s.service_name
        FROM appointment a
        LEFT JOIN patient p ON a.patient_id = p.patient_id
        LEFT JOIN service s ON a.service_id = s.service_id
        WHERE a.tenant_id = ? AND a.dentist_id = ? AND a.appointment_date >= ?
        ORDER BY a.appointment_date, a.appointment_time
        LIMIT 20
    ");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iis", $tenantId, $dentistId, $today);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $upcomingAppointments[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
} catch (Exception $e) {
    error_log("Error fetching dentist appointments: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - OralSync</title>
    <link rel="stylesheet" href="/tenant_style.css">
    <style>
        .schedule-card { background: white; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem; border: 1px solid #e2e8f0; }
        .appointment-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 0.5rem; }
        .appointment-info { flex: 1; }
        .appointment-time { font-weight: 600; color: #059669; }
        .appointment-patient { font-weight: 500; margin: 0.25rem 0; }
        .appointment-service { color: #6b7280; font-size: 0.9rem; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="t-wrap">
        <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

        <main class="t-main">
            <div class="t-header">
                <h1 class="t-title">My Schedule</h1>
                <p class="t-subtitle">View your upcoming appointments and availability</p>
            </div>

            <div class="t-content">
                <div class="schedule-card">
                    <h2 style="margin-bottom: 1rem; color: #1f2937;">Today's Appointments</h2>
                    <?php
                    $todayAppointments = array_filter($upcomingAppointments, function($apt) use ($today) {
                        return $apt['appointment_date'] === $today;
                    });

                    if (empty($todayAppointments)): ?>
                        <p style="color: #6b7280; font-style: italic;">No appointments scheduled for today.</p>
                    <?php else: ?>
                        <?php foreach ($todayAppointments as $appointment): ?>
                            <div class="appointment-item">
                                <div class="appointment-info">
                                    <div class="appointment-time">
                                        <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    </div>
                                    <div class="appointment-patient">
                                        <?php echo h($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                    </div>
                                    <div class="appointment-service">
                                        <?php echo h($appointment['service_name'] ?? 'General Checkup'); ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo h(ucfirst($appointment['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="schedule-card">
                    <h2 style="margin-bottom: 1rem; color: #1f2937;">Upcoming Appointments</h2>
                    <?php
                    $futureAppointments = array_filter($upcomingAppointments, function($apt) use ($today) {
                        return $apt['appointment_date'] > $today;
                    });

                    if (empty($futureAppointments)): ?>
                        <p style="color: #6b7280; font-style: italic;">No upcoming appointments.</p>
                    <?php else: ?>
                        <?php foreach ($futureAppointments as $appointment): ?>
                            <div class="appointment-item">
                                <div class="appointment-info">
                                    <div class="appointment-time">
                                        <?php echo date('M j, g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])); ?>
                                    </div>
                                    <div class="appointment-patient">
                                        <?php echo h($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                    </div>
                                    <div class="appointment-service">
                                        <?php echo h($appointment['service_name'] ?? 'General Checkup'); ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo h(ucfirst($appointment['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>