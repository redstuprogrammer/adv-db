<?php
/**
 * DENTIST SCHEDULE AVAILABILITY MANAGEMENT - REFACTORED
 * Consolidates individual day cards into a single management table.
 */

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('dentist');

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantId = $sessionManager->getTenantId();
$dentistId = $sessionManager->getUserId();
$dentistName = $sessionManager->getUsername() ?? 'Doctor';

$message = '';
$messageType = '';

// Handle Single POST for the entire week
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all_schedule'])) {
    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $successCount = 0;

    // Start transaction or just clear existing for this dentist to avoid duplicates
    mysqli_query($conn, "DELETE FROM dentist_schedule WHERE tenant_id = $tenantId AND dentist_id = $dentistId");

    foreach ($daysOfWeek as $day) {
        $isAvailable = isset($_POST["available_$day"]) ? 1 : 0;
        $startTime = $_POST["start_$day"] ?? '09:00';
        $endTime = $_POST["end_$day"] ?? '17:00';

        $query = "INSERT INTO dentist_schedule (tenant_id, dentist_id, day_of_week, is_available, start_time, end_time) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        if ($stmt = mysqli_prepare($conn, $query)) {
            mysqli_stmt_bind_param($stmt, "iisiss", $tenantId, $dentistId, $day, $isAvailable, $startTime, $endTime);
            if (mysqli_stmt_execute($stmt)) $successCount++;
            mysqli_stmt_close($stmt);
        }
    }

    if ($successCount === 7) {
        $message = 'Weekly schedule updated successfully.';
        $messageType = 'success';
        logActivity($conn, $tenantId, 'Schedule', 'Dentist updated full weekly schedule', $dentistName, 'dentist', 'Dentist');
    }
}

// Fetch current availability
$availability = [];
$stmt = mysqli_prepare($conn, "SELECT day_of_week, is_available, start_time, end_time FROM dentist_schedule WHERE tenant_id = ? AND dentist_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $tenantId, $dentistId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $availability[$row['day_of_week']] = $row;
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - OralSync</title>
    <link rel="stylesheet" href="/tenant_style.css">
    <style>
        /* Modern Table Dashboard Styles */
        .schedule-container {
            max-width: 960px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .management-card {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .compact-table {
            width: 100%;
            border-collapse: collapse;
        }
        .compact-table th {
            background: #f8fafc;
            text-align: left;
            padding: 12px 16px;
            font-size: 0.85rem;
            text-transform: uppercase;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }
        .compact-table td {
            padding: 10px 16px;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }
        .row-unavailable {
            background: #f8fafc;
            opacity: 0.6;
        }
        .time-input {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .batch-bar {
            display: flex;
            justify-content: flex-end;
            padding-bottom: 1rem;
        }
        .btn-ghost {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #4b5563;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-ghost:hover { background: #f1f5f9; }
        
        .btn-primary-save {
            background: #0d3b66;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }
        .status-pill {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .pill-available { background: #dcfce7; color: #166534; }
        .pill-not { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="tenant-layout">
        <?php include __DIR__ . '/includes/sidebar_main.php'; ?>
        <main class="tenant-main-content">
            <div class="tenant-header-bar">
                <div>
                    <h1 class="t-title" style="margin:0;">My Schedule</h1>
                    <p class="t-subtitle" style="margin:0;">Manage your weekly clinical availability</p>
                </div>
                <?php renderDateClock(); ?>
            </div>

            <div class="schedule-container">
                <?php if ($message): ?>
                    <div style="padding:12px; background:#dcfce7; color:#166534; border-radius:6px; margin-bottom:16px; font-size:14px; border: 1px solid #bbf7d0;">
                        <?php echo h($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="management-card">
                    <input type="hidden" name="save_all_schedule" value="1">
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th>Day of Week</th>
                                <th>Availability</th>
                                <th>Working Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            foreach ($days as $day):
                                $data = $availability[$day] ?? ['is_available' => 0, 'start_time' => '09:00', 'end_time' => '17:00'];
                                $isAvail = (int)$data['is_available'];
                            ?>
                            <tr id="row-<?php echo $day; ?>" class="<?php echo !$isAvail ? 'row-unavailable' : ''; ?>">
                                <td style="font-weight:600; color:#1f2937;"><?php echo $day; ?></td>
                                <td>
                                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                        <input type="checkbox" name="available_<?php echo $day; ?>" value="1" 
                                               <?php echo $isAvail ? 'checked' : ''; ?> 
                                               onchange="updateRowState('<?php echo $day; ?>', this)">
                                        <span id="pill-<?php echo $day; ?>" class="status-pill <?php echo $isAvail ? 'pill-available' : 'pill-not'; ?>">
                                            <?php echo $isAvail ? 'Available' : 'Not Available'; ?>
                                        </span>
                                    </label>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <input type="time" name="start_<?php echo $day; ?>" class="time-input" 
                                               value="<?php echo h($data['start_time']); ?>" <?php echo !$isAvail ? 'disabled' : ''; ?>>
                                        <span style="color:#94a3b8;">to</span>
                                        <input type="time" name="end_<?php echo $day; ?>" class="time-input" 
                                               value="<?php echo h($data['end_time']); ?>" <?php echo !$isAvail ? 'disabled' : ''; ?>>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="padding: 1.5rem; text-align: right; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                        <button type="submit" class="btn-primary-save">Save All Changes</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        <?php printDateClockScript(); ?>

        function updateRowState(day, checkbox) {
            const row = document.getElementById(`row-${day}`);
            const pill = document.getElementById(`pill-${day}`);
            const inputs = row.querySelectorAll('input[type="time"]');
            
            if (checkbox.checked) {
                row.classList.remove('row-unavailable');
                pill.textContent = 'Available';
                pill.className = 'status-pill pill-available';
                inputs.forEach(i => i.disabled = false);
            } else {
                row.classList.add('row-unavailable');
                pill.textContent = 'Not Available';
                pill.className = 'status-pill pill-not';
                inputs.forEach(i => i.disabled = true);
            }
        }

    </script>
</body>
</html>