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
            'is_closed' => 1 - $isOpen,
            'opening_time' => $openTime,
            'closing_time' => $closeTime
        ];
    }

    // Save to database
    foreach ($updates as $update) {
        $stmt = $conn->prepare("INSERT INTO clinic_schedules (tenant_id, day_of_week, is_closed, opening_time, closing_time) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_closed = VALUES(is_closed), opening_time = VALUES(opening_time), closing_time = VALUES(closing_time)");
        $stmt->bind_param('iisss', $tenantId, $update['day'], $update['is_closed'], $update['opening_time'], $update['closing_time']);
        $stmt->execute();
        $stmt->close();
    }

    $message = 'Clinic schedule updated successfully!';
    $messageType = 'success';
}

// Load schedule from database
$schedule = [];
$stmt = $conn->prepare("SELECT day_of_week, CASE WHEN is_closed = 0 THEN 1 ELSE 0 END as is_open, opening_time as open_time, closing_time as close_time FROM clinic_schedules WHERE tenant_id = ?");
$stmt->bind_param('i', $tenantId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $schedule[$row['day_of_week']] = [
        'is_open' => (int)$row['is_open'],
        'open_time' => $row['open_time'],
        'close_time' => $row['close_time']
    ];
}
$stmt->close();

// Default schedule data if not set
$defaultSchedule = [
    'monday' => ['is_open' => 1, 'open_time' => '09:00', 'close_time' => '17:00'],
    'tuesday' => ['is_open' => 1, 'open_time' => '09:00', 'close_time' => '17:00'],
    'wednesday' => ['is_open' => 1, 'open_time' => '09:00', 'close_time' => '17:00'],
    'thursday' => ['is_open' => 1, 'open_time' => '09:00', 'close_time' => '17:00'],
    'friday' => ['is_open' => 1, 'open_time' => '09:00', 'close_time' => '17:00'],
    'saturday' => ['is_open' => 0, 'open_time' => '09:00', 'close_time' => '17:00'],
    'sunday' => ['is_open' => 0, 'open_time' => '09:00', 'close_time' => '17:00'],
];

foreach ($defaultSchedule as $day => $data) {
    if (!isset($schedule[$day])) {
        $schedule[$day] = $data;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Schedule - OralSync</title>
    <link rel="stylesheet" href="/tenant_style.css">
    <style>
        :root {
            --accent: #0d3b66;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
        }

        .schedule-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .schedule-header {
            margin-bottom: 2rem;
        }

        .schedule-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .schedule-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .batch-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--border);
        }

        .btn-batch {
            padding: 10px 16px;
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: background 0.2s ease;
        }

        .btn-batch:hover {
            background: #4b5563;
        }

        .btn-batch.primary {
            background: var(--accent);
        }

        .btn-batch.primary:hover {
            background: #0a2d4f;
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }

        .schedule-table thead {
            background: var(--bg);
            border-bottom: 2px solid var(--border);
        }

        .schedule-table th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 700;
            color: var(--accent);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .schedule-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.2s ease;
        }

        .schedule-table tbody tr:hover {
            background: var(--bg);
        }

        .schedule-table tbody tr.closed {
            background: #fef3c7;
            opacity: 0.7;
        }

        .schedule-table tbody tr.closed td {
            color: #92400e;
        }

        .schedule-table td {
            padding: 16px;
            color: var(--text-primary);
        }

        .day-cell {
            font-weight: 700;
            font-size: 15px;
            min-width: 120px;
        }

        .day-cell.weekend {
            color: #dc2626;
        }

        .status-cell {
            text-align: center;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #d1fae5;
            color: #065f46;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.closed {
            background: #fecaca;
            color: #991b1b;
        }

        .time-cell {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #475569;
            min-width: 200px;
        }

        .time-inputs-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .time-input {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            width: 100px;
        }

        .time-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.08);
            outline: none;
        }

        .time-separator {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 12px;
        }

        .checkbox-cell {
            text-align: center;
        }

        .checkbox-cell input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--accent);
        }

        .row-group-header {
            background: rgba(13, 59, 102, 0.05);
            font-weight: 700;
            color: var(--accent);
            padding: 12px 16px !important;
            font-size: 12px;
            text-transform: uppercase;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn-save {
            background: var(--accent);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s ease;
            flex: 1;
            min-width: 150px;
        }

        .btn-save:hover {
            background: #0a2d4f;
        }

        .message-box {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-weight: 600;
            font-size: 14px;
        }

        .message-box.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .message-box.info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        @media (max-width: 768px) {
            .schedule-table {
                font-size: 12px;
            }

            .schedule-table td,
            .schedule-table th {
                padding: 12px 8px;
            }

            .time-input {
                width: 80px;
            }

            .time-separator {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="t-wrap">
        <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

        <main class="t-main">
            <div class="t-content">
                <div class="schedule-container">
                    <?php if ($message): ?>
                        <div class="message-box <?php echo $messageType; ?>">
                            <?php echo h($message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="schedule-header">
                        <h2>📅 Weekly Schedule</h2>
                        <p>Configure your clinic's operating hours for each day of the week</p>
                    </div>

                    <form method="POST">
                        <div class="batch-actions">
                            <button type="button" class="btn-batch" onclick="copyMondayToAll()">📋 Copy Monday to All Days</button>
                            <button type="button" class="btn-batch" onclick="copyMondayToWeekdays()">📋 Copy Monday to Weekdays</button>
                        </div>

                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Day</th>
                                    <th style="width: 100px; text-align: center;">Status</th>
                                    <th>Operating Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Weekdays -->
                                <?php $weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']; ?>
                                <?php foreach ($weekdays as $day): 
                                    $data = $schedule[$day];
                                    $isClosed = !$data['is_open'];
                                ?>
                                <tr <?php echo $isClosed ? 'class="closed"' : ''; ?>>
                                    <td class="day-cell"><?php echo ucfirst($day); ?></td>
                                    <td class="status-cell">
                                        <label style="display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer;">
                                            <input type="checkbox" name="<?php echo $day; ?>_open" <?php echo $data['is_open'] ? 'checked' : ''; ?> onchange="updateRowClass(this)">
                                            <span class="status-badge <?php echo $isClosed ? 'closed' : ''; ?>">
                                                <?php echo $isClosed ? 'Closed' : 'Open'; ?>
                                            </span>
                                        </label>
                                    </td>
                                    <td class="time-cell">
                                        <div class="time-inputs-group">
                                            <input type="time" name="<?php echo $day; ?>_open_time" value="<?php echo h($data['open_time']); ?>" class="time-input" <?php echo $isClosed ? 'disabled' : ''; ?>>
                                            <span class="time-separator">to</span>
                                            <input type="time" name="<?php echo $day; ?>_close_time" value="<?php echo h($data['close_time']); ?>" class="time-input" <?php echo $isClosed ? 'disabled' : ''; ?>>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <!-- Weekends -->
                                <?php $weekends = ['saturday', 'sunday']; ?>
                                <?php foreach ($weekends as $day): 
                                    $data = $schedule[$day];
                                    $isClosed = !$data['is_open'];
                                ?>
                                <tr <?php echo $isClosed ? 'class="closed"' : ''; ?>>
                                    <td class="day-cell weekend"><?php echo ucfirst($day); ?></td>
                                    <td class="status-cell">
                                        <label style="display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer;">
                                            <input type="checkbox" name="<?php echo $day; ?>_open" <?php echo $data['is_open'] ? 'checked' : ''; ?> onchange="updateRowClass(this)">
                                            <span class="status-badge <?php echo $isClosed ? 'closed' : ''; ?>">
                                                <?php echo $isClosed ? 'Closed' : 'Open'; ?>
                                            </span>
                                        </label>
                                    </td>
                                    <td class="time-cell">
                                        <div class="time-inputs-group">
                                            <input type="time" name="<?php echo $day; ?>_open_time" value="<?php echo h($data['open_time']); ?>" class="time-input" <?php echo $isClosed ? 'disabled' : ''; ?>>
                                            <span class="time-separator">to</span>
                                            <input type="time" name="<?php echo $day; ?>_close_time" value="<?php echo h($data['close_time']); ?>" class="time-input" <?php echo $isClosed ? 'disabled' : ''; ?>>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="action-buttons">
                            <button type="submit" class="btn-save">💾 Save Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        function updateRowClass(checkbox) {
            const row = checkbox.closest('tr');
            const statusBadge = row.querySelector('.status-badge');
            const timeInputs = row.querySelectorAll('.time-input');
            
            if (checkbox.checked) {
                row.classList.remove('closed');
                statusBadge.classList.remove('closed');
                statusBadge.textContent = 'Open';
                timeInputs.forEach(input => input.disabled = false);
            } else {
                row.classList.add('closed');
                statusBadge.classList.add('closed');
                statusBadge.textContent = 'Closed';
                timeInputs.forEach(input => input.disabled = true);
            }
        }

        function copyMondayToAll() {
            const mondayOpenTime = document.querySelector('input[name="monday_open_time"]').value;
            const mondayCloseTime = document.querySelector('input[name="monday_close_time"]').value;
            const mondayOpen = document.querySelector('input[name="monday_open"]').checked;

            const days = ['tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            days.forEach(day => {
                document.querySelector(`input[name="${day}_open_time"]`).value = mondayOpenTime;
                document.querySelector(`input[name="${day}_close_time"]`).value = mondayCloseTime;
                const checkbox = document.querySelector(`input[name="${day}_open"]`);
                checkbox.checked = mondayOpen;
                checkbox.dispatchEvent(new Event('change'));
            });
        }

        function copyMondayToWeekdays() {
            const mondayOpenTime = document.querySelector('input[name="monday_open_time"]').value;
            const mondayCloseTime = document.querySelector('input[name="monday_close_time"]').value;
            const mondayOpen = document.querySelector('input[name="monday_open"]').checked;

            const weekdays = ['tuesday', 'wednesday', 'thursday', 'friday'];
            
            weekdays.forEach(day => {
                document.querySelector(`input[name="${day}_open_time"]`).value = mondayOpenTime;
                document.querySelector(`input[name="${day}_close_time"]`).value = mondayCloseTime;
                const checkbox = document.querySelector(`input[name="${day}_open"]`);
                checkbox.checked = mondayOpen;
                checkbox.dispatchEvent(new Event('change'));
            });
        }
    </script>
</body>
</html>