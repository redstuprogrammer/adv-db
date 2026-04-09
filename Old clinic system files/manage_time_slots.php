<?php
session_start();
require '../config/db.php';

// Check if admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

$message = '';
$success = false;
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Default schedule (8 AM - 5 PM)
$default_slots = [
    '08:00:00', '09:00:00', '10:00:00', '11:00:00', '12:00:00',
    '13:00:00', '14:00:00', '15:00:00', '16:00:00', '17:00:00'
];

// ============================================
// HANDLE ACTIONS
// ============================================

// Toggle slot availability
if(isset($_POST['toggle_slot'])) {
    $slot_date = $_POST['slot_date'];
    $time_slot = $_POST['time_slot'];
    
    // Check if override exists
    $check = $conn->prepare("SELECT override_id, is_available FROM time_slot_overrides WHERE slot_date = ? AND time_slot = ?");
    $check->bind_param("ss", $slot_date, $time_slot);
    $check->execute();
    $result = $check->get_result();
    
    if($result->num_rows > 0) {
        // Override exists - toggle it or delete if becoming available again
        $row = $result->fetch_assoc();
        if($row['is_available'] == 0) {
            // Was closed, now open - delete override (back to default)
            $stmt = $conn->prepare("DELETE FROM time_slot_overrides WHERE slot_date = ? AND time_slot = ?");
            $stmt->bind_param("ss", $slot_date, $time_slot);
            $stmt->execute();
            $message = "Slot reopened (back to default)";
        } else {
            // Was explicitly open, now close
            $stmt = $conn->prepare("UPDATE time_slot_overrides SET is_available = 0 WHERE slot_date = ? AND time_slot = ?");
            $stmt->bind_param("ss", $slot_date, $time_slot);
            $stmt->execute();
            $message = "Slot closed";
        }
    } else {
        // No override exists - create one to close the slot
        $stmt = $conn->prepare("INSERT INTO time_slot_overrides (slot_date, time_slot, is_available) VALUES (?, ?, 0)");
        $stmt->bind_param("ss", $slot_date, $time_slot);
        $stmt->execute();
        $message = "Slot closed";
    }
    
    $success = true;
    $selected_date = $slot_date;
    $check->close();
}

// Close entire day
if(isset($_POST['close_all'])) {
    $close_date = $_POST['close_date'];
    
    foreach($default_slots as $time_slot) {
        $stmt = $conn->prepare("INSERT INTO time_slot_overrides (slot_date, time_slot, is_available) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE is_available = 0");
        $stmt->bind_param("ss", $close_date, $time_slot);
        $stmt->execute();
        $stmt->close();
    }
    
    $success = true;
    $message = "All slots closed for " . date('M j, Y', strtotime($close_date));
    $selected_date = $close_date;
}

// Open entire day
if(isset($_POST['open_all'])) {
    $open_date = $_POST['open_date'];
    
    // Delete all overrides for this date (back to default open)
    $stmt = $conn->prepare("DELETE FROM time_slot_overrides WHERE slot_date = ?");
    $stmt->bind_param("s", $open_date);
    $stmt->execute();
    $stmt->close();
    
    $success = true;
    $message = "All slots opened for " . date('M j, Y', strtotime($open_date));
    $selected_date = $open_date;
}

// ============================================
// GET DATA
// ============================================

// Get overrides for selected date
$overrides = [];
$stmt = $conn->prepare("SELECT time_slot, is_available FROM time_slot_overrides WHERE slot_date = ?");
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    $overrides[$row['time_slot']] = $row['is_available'];
}
$stmt->close();

// Build slot status array
$slots = [];
foreach($default_slots as $time) {
    $slots[] = [
        'time' => $time,
        'is_available' => isset($overrides[$time]) ? $overrides[$time] : 1 // Default is available
    ];
}

// Get stats
$total_overrides = $conn->query("SELECT COUNT(*) as count FROM time_slot_overrides")->fetch_assoc()['count'];
$closed_slots = $conn->query("SELECT COUNT(*) as count FROM time_slot_overrides WHERE is_available = 0")->fetch_assoc()['count'];
$dates_modified = $conn->query("SELECT COUNT(DISTINCT slot_date) as count FROM time_slot_overrides")->fetch_assoc()['count'];

// Check if all slots closed for selected date
$closed_count = 0;
foreach($slots as $slot) {
    if($slot['is_available'] == 0) $closed_count++;
}
$all_closed = ($closed_count == count($default_slots));

// Get pending users count
$pending_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'pending'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Time Slots - Villangca Dental Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #F8FAFB;
            color: #1E293B;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(180deg, #1E40AF 0%, #1E3A8A 100%);
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 4px 0 12px rgba(0,0,0,0.1);
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }

        .clinic-logo {
            padding: 2rem 1.5rem;
            background: rgba(255,255,255,0.1);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .clinic-logo h1 {
            color: white;
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .clinic-logo .icon {
            font-size: 1.75rem;
        }

        .clinic-logo p {
            color: rgba(255,255,255,0.8);
            font-size: 0.75rem;
            margin-top: 0.25rem;
            margin-left: 2.5rem;
        }

        .user-profile {
            padding: 1.5rem;
            background: rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #60A5FA 0%, #3B82F6 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
        }

        .user-info h3 {
            color: white;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .user-role {
            display: inline-block;
            background: rgba(251,191,36,0.2);
            color: #FCD34D;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .nav-menu {
            padding: 1.5rem 0;
        }

        .nav-section-title {
            color: rgba(255,255,255,0.5);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 1.5rem;
            margin-bottom: 0.75rem;
            margin-top: 1rem;
        }

        .nav-section-title:first-child {
            margin-top: 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            gap: 0.875rem;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #60A5FA;
        }

        .nav-item.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left-color: #FCD34D;
            font-weight: 600;
        }

        .nav-item .icon {
            font-size: 1.25rem;
            width: 24px;
            text-align: center;
        }

        .nav-item .badge {
            margin-left: auto;
            background: #EF4444;
            color: white;
            padding: 0.125rem 0.5rem;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .logout-btn {
            margin: 1rem 1.5rem;
            padding: 0.875rem;
            background: rgba(239,68,68,0.2);
            color: #FCA5A5;
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .main-content {
            margin-left: 280px;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .page-title h2 {
            font-size: 1.75rem;
            color: #0F172A;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .page-title p {
            color: #64748B;
            font-size: 0.9rem;
        }

        .date-display {
            color: #64748B;
            font-size: 0.9rem;
            padding: 0.625rem 1rem;
            background: #F1F5F9;
            border-radius: 8px;
        }

        .content-area {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #10B981;
        }

        .info-card {
            background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
            border: 2px solid #3B82F6;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card h3 {
            color: #1E40AF;
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .info-card p {
            color: #1E40AF;
            line-height: 1.6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #3B82F6;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #64748B;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .date-picker-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .date-picker-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 1.5rem;
        }

        .date-input-group {
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .date-input-group input {
            flex: 1;
            padding: 0.875rem;
            border: 2px solid #E2E8F0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
        }

        .date-input-group input:focus {
            outline: none;
            border-color: #3B82F6;
        }

        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59,130,246,0.4);
        }

        .btn-danger {
            background: #EF4444;
            color: white;
        }

        .btn-success {
            background: #10B981;
            color: white;
        }

        .quick-dates {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .quick-date-btn {
            padding: 0.5rem 1rem;
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            color: #475569;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .quick-date-btn:hover {
            background: #E2E8F0;
        }

        .selected-date {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .selected-date h3 {
            font-size: 1.5rem;
            color: #0F172A;
            font-weight: 700;
        }

        .slots-table-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .slots-table-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 1.5rem;
        }

        .slots-table {
            width: 100%;
            border-collapse: collapse;
        }

        .slots-table th {
            background: #F8FAFC;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #E2E8F0;
        }

        .slots-table td {
            padding: 1rem;
            border-bottom: 1px solid #E2E8F0;
        }

        .slots-table tr:last-child td {
            border-bottom: none;
        }

        .slots-table tr:hover {
            background: #F8FAFC;
        }

        .time-cell {
            font-size: 1.125rem;
            font-weight: 600;
            color: #0F172A;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .status-open {
            background: #D1FAE5;
            color: #065F46;
        }

        .status-closed {
            background: #FEE2E2;
            color: #991B1B;
        }

        .toggle-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.875rem;
        }

        .toggle-btn.close {
            background: #EF4444;
            color: white;
        }

        .toggle-btn.open {
            background: #10B981;
            color: white;
        }

        .bulk-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid #E2E8F0;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .slots-table {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="clinic-logo">
        <h1><span class="icon">🦷</span> Villangca Dental</h1>
        <p>Professional Dental Care</p>
    </div>

    <div class="user-profile">
        <div class="user-avatar">👨‍⚕️</div>
        <div class="user-info">
            <h3><?php echo htmlspecialchars($_SESSION['fullname']); ?></h3>
            <span class="user-role">Administrator</span>
        </div>
    </div>

    <nav class="nav-menu">
        <div class="nav-section-title">Main Menu</div>
        <a href="dashboard.php" class="nav-item">
            <span class="icon">📊</span>
            <span>Dashboard</span>
        </a>
        
        <div class="nav-section-title">User Management</div>
        <a href="pending_accounts.php" class="nav-item">
            <span class="icon">⏳</span>
            <span>Pending Approvals</span>
            <?php if($pending_count > 0): ?>
                <span class="badge"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="manage_users.php" class="nav-item">
            <span class="icon">👥</span>
            <span>Manage Users</span>
        </a>
        
        <div class="nav-section-title">System Settings</div>
        <a href="manage_services.php" class="nav-item">
            <span class="icon">🔧</span>
            <span>Manage Services</span>
        </a>
        <a href="manage_time_slots.php" class="nav-item active">
            <span class="icon">⏰</span>
            <span>Manage Time Slots</span>
        </a>
        
        <div class="nav-section-title">Reports & Analytics</div>
        <a href="reports.php" class="nav-item">
            <span class="icon">📈</span>
            <span>System Reports</span>
        </a>
    </nav>

    <button class="logout-btn" onclick="window.location.href='../logout.php'">
        <span>🚪</span>
        <span>Logout</span>
    </button>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div class="page-title">
            <h2>⏰ Manage Time Slots</h2>
            <p>Default schedule: 8:00 AM - 5:00 PM (all dates)</p>
        </div>
        <div class="date-display">
            📅 <?php echo date('l, F j, Y'); ?>
        </div>
    </div>

    <div class="content-area">
        <?php if($message): ?>
            <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>">
                <span><?php echo $success ? '✓' : '✕'; ?></span>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Info Card -->
        <div class="info-card">
            <h3>ℹ️ How Time Slots Work</h3>
            <p><strong>Default Schedule:</strong> All dates automatically have slots from 8:00 AM to 5:00 PM (10 hourly slots).</p>
            <p><strong>Override Dates:</strong> Use this page to close specific time slots on specific dates (holidays, meetings, etc.).</p>
            <p><strong>Patient Booking:</strong> Patients can only book slots that are marked as "Available".</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $dates_modified; ?></div>
                <div class="stat-label">Dates Modified</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $closed_slots; ?></div>
                <div class="stat-label">Closed Slots</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_overrides; ?></div>
                <div class="stat-label">Total Overrides</div>
            </div>
        </div>

        <!-- Date Picker -->
        <div class="date-picker-card">
            <h3>📅 Select Date to Manage</h3>
            <form method="GET">
                <div class="date-input-group">
                    <input type="date" name="date" value="<?php echo $selected_date; ?>" required>
                    <button type="submit" class="btn btn-primary">View Schedule</button>
                </div>
            </form>
            <div class="quick-dates">
                <a href="?date=<?php echo date('Y-m-d'); ?>" class="quick-date-btn">Today</a>
                <a href="?date=<?php echo date('Y-m-d', strtotime('+1 day')); ?>" class="quick-date-btn">Tomorrow</a>
                <a href="?date=<?php echo date('Y-m-d', strtotime('+7 days')); ?>" class="quick-date-btn">Next Week</a>
                <a href="?date=<?php echo date('Y-m-d', strtotime('+30 days')); ?>" class="quick-date-btn">Next Month</a>
            </div>
        </div>

        <!-- Selected Date -->
        <div class="selected-date">
            <h3>🗓️ <?php echo date('l, F j, Y', strtotime($selected_date)); ?></h3>
            <?php if($all_closed): ?>
                <span class="status-badge status-closed">
                    <span>🔒</span>
                    <span>Clinic Closed</span>
                </span>
            <?php else: ?>
                <span class="status-badge status-open">
                    <span>✅</span>
                    <span>Clinic Open</span>
                </span>
            <?php endif; ?>
        </div>

        <!-- Time Slots Table -->
        <div class="slots-table-card">
            <h3>⏰ Time Schedule</h3>
            <table class="slots-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($slots as $slot): ?>
                        <tr>
                            <td class="time-cell">
                                <?php echo date('h:i A', strtotime($slot['time'])); ?>
                            </td>
                            <td>
                                <?php if($slot['is_available']): ?>
                                    <span class="status-badge status-open">
                                        <span>✅</span>
                                        <span>Available</span>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-closed">
                                        <span>🔒</span>
                                        <span>Closed</span>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="slot_date" value="<?php echo $selected_date; ?>">
                                    <input type="hidden" name="time_slot" value="<?php echo $slot['time']; ?>">
                                    <button type="submit" name="toggle_slot" class="toggle-btn <?php echo $slot['is_available'] ? 'close' : 'open'; ?>">
                                        <?php echo $slot['is_available'] ? 'Close Slot' : 'Open Slot'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Bulk Actions -->
            <div class="bulk-actions">
                <form method="POST" style="margin: 0;" onsubmit="return confirm('Close ALL time slots for this date?');">
                    <input type="hidden" name="close_date" value="<?php echo $selected_date; ?>">
                    <button type="submit" name="close_all" class="btn btn-danger">
                        🔒 Close Entire Day
                    </button>
                </form>

                <?php if(!$all_closed): ?>
                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Reset to default schedule (all slots open)?');">
                        <input type="hidden" name="open_date" value="<?php echo $selected_date; ?>">
                        <button type="submit" name="open_all" class="btn btn-success">
                            ✅ Reset to Default
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

</body>
</html>