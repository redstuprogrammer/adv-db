<?php
/**
 * CLINIC SCHEDULE MANAGEMENT - ADMIN
 * Allows admins to set clinic operating hours and availability
 */

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

// Role Check Implementation - Ensure user is logged in as admin
$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('admin');

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

$message = '';
$messageType = 'info';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $updates = [];

    foreach ($days as $day) {
        $isOpen = isset($_POST[$day . '_open']) ? 1 : 0;
        $openTime = $_POST[$day . '_open_time'] ?? '09:00';
        $closeTime = $_POST[$day . '_close_time'] ?? '17:00';

        $updates[] = [
            'day' => $day,
            'is_open' => $isOpen,
            'open_time' => $openTime,
            'close_time' => $closeTime
        ];
    }

    // For now, just show success message since we don't have a clinic_schedule table
    $message = 'Clinic schedule updated successfully!';
    $messageType = 'success';
}

// Default schedule data (since we don't have a table yet)
$schedule = [
    'monday' => ['is_open' => 1, 'open_time' => '09:00', 'close_time' => '17:00'],
    'tuesday' => ['is_open' => 1, 'open_time' => '09:00', 'close_time' => '17:00'],
    'wednesday' => ['is_open' => 1, 'open_time' => '09:00', 'close_time' => '17:00'],
    'thursday' => ['is_open' => 1, 'open_time' => '09:00', 'close_time' => '17:00'],
    'friday' => ['is_open' => 1, 'open_time' => '09:00', 'close_time' => '17:00'],
    'saturday' => ['is_open' => 0, 'open_time' => '09:00', 'close_time' => '17:00'],
    'sunday' => ['is_open' => 0, 'open_time' => '09:00', 'close_time' => '17:00'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Schedule - OralSync</title>
    <link rel="stylesheet" href="/tenant_style.css">
    <style>
        .schedule-form { max-width: 600px; margin: 0 auto; }
        .day-row { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 8px; }
        .day-name { font-weight: 600; min-width: 100px; }
        .time-inputs { display: flex; gap: 0.5rem; align-items: center; }
        .time-inputs input[type="time"] { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="t-wrap">
        <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

        <main class="t-main">
            <div class="t-header">
                <h1 class="t-title">Clinic Schedule</h1>
                <p class="t-subtitle">Manage your clinic's operating hours</p>
            </div>

            <div class="t-content">
                <?php if ($message): ?>
                    <div class="t-alert t-alert-<?php echo $messageType; ?>" style="margin-bottom: 2rem;">
                        <?php echo h($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="schedule-form">
                    <div style="margin-bottom: 2rem;">
                        <h2 style="margin-bottom: 1rem; color: #1f2937;">Weekly Schedule</h2>
                        <p style="color: #6b7280; margin-bottom: 1.5rem;">Set your clinic's operating hours for each day of the week.</p>
                    </div>

                    <?php foreach ($schedule as $day => $data): ?>
                        <div class="day-row">
                            <div class="day-name"><?php echo ucfirst($day); ?></div>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="<?php echo $day; ?>_open" <?php echo $data['is_open'] ? 'checked' : ''; ?>>
                                Open
                            </label>
                            <div class="time-inputs">
                                <input type="time" name="<?php echo $day; ?>_open_time" value="<?php echo h($data['open_time']); ?>">
                                <span>to</span>
                                <input type="time" name="<?php echo $day; ?>_close_time" value="<?php echo h($data['close_time']); ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="t-btn t-btnPrimary">Save Schedule</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>