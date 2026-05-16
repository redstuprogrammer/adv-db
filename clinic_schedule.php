<?php
/**
 * CLINIC SCHEDULE MANAGEMENT - ADMIN
 * Refactored for compact layout and ghost-style actions
 */

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('admin');

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantData = $sessionManager->getTenantData();
$tenantId = $sessionManager->getTenantId();

$message = '';
$messageType = 'info';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    foreach ($days as $day) {
        $dayLower = strtolower($day);
        $isOpen = isset($_POST[$dayLower . '_open']) ? 1 : 0;
        $openTime = $_POST[$dayLower . '_open_time'] ?? '09:00';
        $closeTime = $_POST[$dayLower . '_close_time'] ?? '17:00';

        $stmt = $conn->prepare("INSERT INTO clinic_schedules (tenant_id, day_of_week, is_closed, opening_time, closing_time) 
                                VALUES (?, ?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE is_closed = VALUES(is_closed), opening_time = VALUES(opening_time), closing_time = VALUES(closing_time)");
        $isClosed = 1 - $isOpen;
        // Corrected types: i (tenantId), s (day), i (isClosed), s (openTime), s (closeTime)
        $stmt->bind_param('isiss', $tenantId, $day, $isClosed, $openTime, $closeTime);
        $stmt->execute();
        $stmt->close();
    }
    $message = 'Clinic schedule updated successfully!';
    $messageType = 'success';
}

// Load schedule
$schedule = [];
$stmt = $conn->prepare("SELECT day_of_week, CASE WHEN is_closed = 0 THEN 1 ELSE 0 END as is_open, opening_time as open_time, closing_time as close_time FROM clinic_schedules WHERE tenant_id = ?");
$stmt->bind_param('i', $tenantId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $schedule[$row['day_of_week']] = $row;
}
$stmt->close();

$daysOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
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
            --bg-muted: #f8fafc;
            --text-main: #1f2937;
            --text-sub: #6b7280;
        }

        /* Layout matching appointments.php */
        .schedule-page-container {
            max-width: 960px;
            margin: 24px auto;
            padding: 0 24px;
        }

        .batch-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 16px;
        }

        /* Ghost Button Style */
        .btn-ghost {
            padding: 8px 14px;
            background: transparent;
            color: var(--text-sub);
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .btn-ghost:hover {
            background: var(--bg-muted);
            border-color: var(--text-sub);
            color: var(--text-main);
        }

        /* Compact Table Design */
        .compact-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }
        .compact-table th {
            background: var(--bg-muted);
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            color: var(--text-sub);
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border);
        }
        .compact-table td {
            padding: 10px 16px; /* Reduced padding for density */
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        /* Row Conditional Styles */
        .row-closed {
            background-color: var(--bg-muted) !important;
        }
        .row-closed td {
            opacity: 0.6;
        }

        /* UI Elements */
        .status-pill {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .pill-open { background: #dcfce7; color: #166534; }
        .pill-closed { background: #fee2e2; color: #991b1b; }

        .time-input {
            padding: 6px 10px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 13px;
            color: var(--text-main);
        }

        .btn-save-main {
            background: var(--accent);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-save-main:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="tenant-layout">
        <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

        <main class="tenant-main-content">
            <div class="tenant-header-bar">
                <h1 style="margin:0; font-size: 20px; font-weight: 700; color: var(--text-main);">Clinic Schedule</h1>
                <?php renderDateClock(); ?>
            </div>

            <div class="schedule-page-container">
                <?php if ($message): ?>
                    <div style="padding:12px; background:#dcfce7; color:#166534; border-radius:6px; margin-bottom:16px; font-size:14px;">
                        <?= h($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th style="text-align:center">Status</th>
                                <th>Operating Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daysOrder as $day): 
                                $data = $schedule[$day] ?? ['is_open' => 1, 'open_time' => '09:00', 'close_time' => '17:00'];
                                $isClosed = !$data['is_open'];
                                $dayLower = strtolower($day);
                            ?>
                            <tr id="row-<?= $dayLower ?>" class="<?= $isClosed ? 'row-closed' : '' ?>">
                                <td style="font-weight: 600; width: 150px;"><?= $day ?></td>
                                <td style="text-align:center; width: 120px;">
                                    <label style="cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:4px;">
                                        <input type="checkbox" name="<?= $dayLower ?>_open" <?= $data['is_open'] ? 'checked' : '' ?> 
                                               onchange="toggleRow('<?= $dayLower ?>', this)" style="accent-color: var(--accent)">
                                        <span class="status-pill <?= $isClosed ? 'pill-closed' : 'pill-open' ?>" id="pill-<?= $dayLower ?>">
                                            <?= $isClosed ? 'Closed' : 'Open' ?>
                                        </span>
                                    </label>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <input type="time" name="<?= $dayLower ?>_open_time" value="<?= h(substr($data['open_time'] ?? '09:00', 0, 5)) ?>" 
                                               class="time-input" <?= $isClosed ? 'disabled' : '' ?>>
                                        <span style="color: var(--text-sub); font-size: 12px;">to</span>
                                        <input type="time" name="<?= $dayLower ?>_close_time" value="<?= h(substr($data['close_time'] ?? '17:00', 0, 5)) ?>" 
                                               class="time-input" <?= $isClosed ? 'disabled' : '' ?>>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="text-align: right; margin-top: 24px;">
                        <button type="submit" class="btn-save-main">💾 Save Schedule</button>
                    </div>
                </form>
            </div>

    <script>
        <?php printDateClockScript(); ?>

        function toggleRow(day, checkbox) {
            const row = document.getElementById(`row-${day}`);
            const pill = document.getElementById(`pill-${day}`);
            const inputs = row.querySelectorAll('input[type="time"]');

            if (checkbox.checked) {
                row.classList.remove('row-closed');
                pill.className = 'status-pill pill-open';
                pill.textContent = 'Open';
                inputs.forEach(i => i.disabled = false);
            } else {
                row.classList.add('row-closed');
                pill.className = 'status-pill pill-closed';
                pill.textContent = 'Closed';
                inputs.forEach(i => i.disabled = true);
            }
        }

    </script>
        </main>
    </div>
</body>
</html>