<?php
/**
 * DENTIST SCHEDULE AVAILABILITY MANAGEMENT
 * Allows dentists to set and manage their personal availability/working hours
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

$tenantData = $sessionManager->getTenantData();
$tenantName = $tenantData['tenant_name'] ?? '';
$tenantId = $sessionManager->getTenantId();
$dentistId = $sessionManager->getUserId();
$dentistName = $sessionManager->getUsername() ?? 'Doctor';

$message = '';
$messageType = '';

// Handle POST for setting availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_availability'])) {
    $dayOfWeek = trim($_POST['day_of_week'] ?? '');
    $isAvailable = isset($_POST['is_available']) ? 1 : 0;
    $startTime = trim($_POST['start_time'] ?? '09:00');
    $endTime = trim($_POST['end_time'] ?? '17:00');

    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    if (!in_array($dayOfWeek, $daysOfWeek)) {
        $message = 'Invalid day selected.';
        $messageType = 'error';
    } elseif ($isAvailable && ($startTime >= $endTime)) {
        $message = 'End time must be after start time.';
        $messageType = 'error';
    } else {
        try {
            // Check if record exists
            $checkStmt = mysqli_prepare($conn, "SELECT id FROM dentist_availability WHERE tenant_id = ? AND dentist_id = ? AND day_of_week = ?");
            if ($checkStmt) {
                mysqli_stmt_bind_param($checkStmt, "iis", $tenantId, $dentistId, $dayOfWeek);
                mysqli_stmt_execute($checkStmt);
                $checkResult = mysqli_stmt_get_result($checkStmt);
                $exists = mysqli_num_rows($checkResult) > 0;
                mysqli_stmt_close($checkStmt);

                if ($exists) {
                    // Update
                    $updateStmt = mysqli_prepare($conn, "UPDATE dentist_availability SET is_available = ?, start_time = ?, end_time = ? WHERE tenant_id = ? AND dentist_id = ? AND day_of_week = ?");
                    if ($updateStmt) {
                        mysqli_stmt_bind_param($updateStmt, "isssii", $isAvailable, $startTime, $endTime, $tenantId, $dentistId, $dayOfWeek);
                        if (mysqli_stmt_execute($updateStmt)) {
                            $message = 'Availability updated successfully.';
                            $messageType = 'success';
                            logActivity($conn, $tenantId, 'Schedule', 'Dentist updated availability', $dentistName, 'dentist', 'Dentist');
                        } else {
                            $message = 'Failed to update availability.';
                            $messageType = 'error';
                        }
                        mysqli_stmt_close($updateStmt);
                    }
                } else {
                    // Insert
                    $insertStmt = mysqli_prepare($conn, "INSERT INTO dentist_availability (tenant_id, dentist_id, day_of_week, is_available, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($insertStmt) {
                        mysqli_stmt_bind_param($insertStmt, "iisiss", $tenantId, $dentistId, $dayOfWeek, $isAvailable, $startTime, $endTime);
                        if (mysqli_stmt_execute($insertStmt)) {
                            $message = 'Availability added successfully.';
                            $messageType = 'success';
                            logActivity($conn, $tenantId, 'Schedule', 'Dentist added availability', $dentistName, 'dentist', 'Dentist');
                        } else {
                            $message = 'Failed to add availability.';
                            $messageType = 'error';
                        }
                        mysqli_stmt_close($insertStmt);
                    }
                }
            }
        } catch (Exception $e) {
            $message = 'Error setting availability.';
            $messageType = 'error';
        }
    }
}

// Fetch current availability
$availability = [];
try {
    $stmt = mysqli_prepare($conn, "SELECT day_of_week, is_available, start_time, end_time FROM dentist_availability WHERE tenant_id = ? AND dentist_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $tenantId, $dentistId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $availability[$row['day_of_week']] = $row;
        }
        mysqli_stmt_close($stmt);
    }
} catch (Exception $e) {
    error_log("Error fetching dentist availability: " . $e->getMessage());
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
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .schedule-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
        }
        .schedule-card h3 {
            margin-top: 0;
            color: #1f2937;
            font-size: 1.1rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.95rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        .form-group input[type="checkbox"] {
            width: auto;
            margin-right: 0.5rem;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        .time-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        .btn-save {
            background: #22c55e;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-save:hover {
            background: #16a34a;
        }
        .btn-save:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .message.success {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .message.error {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
    </style>
</head>
<body>
    <div class="t-wrap">
        <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

        <main class="t-main">
            <div class="t-header">
                <h1 class="t-title">My Schedule</h1>
                <p class="t-subtitle">Set your working hours and availability</p>
                <?php renderDateClock(); ?>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo h($message); ?>
                </div>
            <?php endif; ?>

            <div class="t-content">
                <div class="schedule-grid">
                    <?php
                    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    foreach ($daysOfWeek as $day):
                        $dayData = $availability[$day] ?? null;
                        $isAvailable = $dayData ? (int)$dayData['is_available'] : 0;
                        $startTime = $dayData ? $dayData['start_time'] : '09:00';
                        $endTime = $dayData ? $dayData['end_time'] : '17:00';
                    ?>
                    <div class="schedule-card">
                        <h3><?php echo h($day); ?></h3>
                        <form method="POST">
                            <input type="hidden" name="set_availability" value="1">
                            <input type="hidden" name="day_of_week" value="<?php echo h($day); ?>">

                            <div class="checkbox-label">
                                <input type="checkbox" id="available-<?php echo $day; ?>" name="is_available" value="1" <?php echo $isAvailable ? 'checked' : ''; ?> onchange="toggleTimeInputs(this)">
                                <label for="available-<?php echo $day; ?>" style="margin: 0; font-weight: 600;">
                                    <?php echo $isAvailable ? 'Available' : 'Not Available'; ?>
                                </label>
                            </div>

                            <div class="time-inputs" style="display: <?php echo $isAvailable ? 'grid' : 'none'; ?>;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="start-<?php echo $day; ?>">Start Time</label>
                                    <input type="time" id="start-<?php echo $day; ?>" name="start_time" value="<?php echo h($startTime); ?>" required>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="end-<?php echo $day; ?>">End Time</label>
                                    <input type="time" id="end-<?php echo $day; ?>" name="end_time" value="<?php echo h($endTime); ?>" required>
                                </div>
                            </div>

                            <button type="submit" class="btn-save" style="margin-top: 0.75rem;">Save</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        <?php printDateClockScript(); ?>

        function toggleTimeInputs(checkbox) {
            const card = checkbox.closest('.schedule-card');
            const timeInputs = card.querySelector('.time-inputs');
            const label = checkbox.nextElementSibling;
            
            if (checkbox.checked) {
                timeInputs.style.display = 'grid';
                label.textContent = 'Available';
            } else {
                timeInputs.style.display = 'none';
                label.textContent = 'Not Available';
            }
        }
    </script>
</body>
</html>
