<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

// 1. SECURITY & IDENTITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Dentist') {
    header("Location: login.php");
    exit();
}

// Get the logged-in Dentist's ID from the session
$loggedInDentistId = $_SESSION['user_id']; 
$dentistName = $_SESSION['username'] ?? 'Doctor';
$todayDate = date('Y-m-d');

/* =========================
   2. DATA FETCHING (STATS)
========================= */
$totalAppt  = $conn->query("SELECT COUNT(*) AS total FROM appointment WHERE dentist_id = '$loggedInDentistId'")->fetch_assoc()['total'] ?? 0;
$todayAppt  = $conn->query("SELECT COUNT(*) AS total FROM appointment WHERE dentist_id = '$loggedInDentistId' AND appointment_date = '$todayDate'")->fetch_assoc()['total'] ?? 0;
$weekAppt   = $conn->query("SELECT COUNT(*) AS total FROM appointment WHERE dentist_id = '$loggedInDentistId' AND YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1)")->fetch_assoc()['total'] ?? 0;
$monthAppt  = $conn->query("SELECT COUNT(*) AS total FROM appointment WHERE dentist_id = '$loggedInDentistId' AND MONTH(appointment_date) = MONTH(CURRENT_DATE()) AND YEAR(appointment_date) = YEAR(CURRENT_DATE())")->fetch_assoc()['total'] ?? 0;

/* =========================
   3. TODAY'S SCHEDULE
========================= */
$scheduleQuery = "SELECT a.appointment_time, p.first_name, p.last_name, s.service_name 
                  FROM appointment a 
                  JOIN patient p ON a.patient_id = p.patient_id 
                  JOIN service s ON a.service_id = s.service_id 
                  WHERE a.appointment_date = '$todayDate' 
                  AND a.dentist_id = '$loggedInDentistId' 
                  ORDER BY a.appointment_time ASC";
$scheduleResult = $conn->query($scheduleQuery);

/* =========================
   4. CALENDAR LOGIC
========================= */
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$numberDays = date('t', $firstDayOfMonth);
$dayOfWeek = date('w', $firstDayOfMonth); 

$prevMonth = ($month == 1) ? 12 : $month - 1; 
$prevYear = ($month == 1) ? $year - 1 : $year;
$nextMonth = ($month == 12) ? 1 : $month + 1; 
$nextYear = ($month == 12) ? $year + 1 : $year;

// Get appointment dates for this specific dentist to show dots
$calendarAppts = [];
$calRes = $conn->query("SELECT DISTINCT appointment_date FROM appointment WHERE dentist_id = '$loggedInDentistId' AND MONTH(appointment_date) = '$month' AND YEAR(appointment_date) = '$year'");
while($row = $calRes->fetch_assoc()){ 
    $calendarAppts[] = $row['appointment_date']; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Dentist Dashboard</title>
    <link rel="stylesheet" href="style1.css"> 
    <style>
        /* Calendar Styling */
        .calendar-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .calendar-table th { color: #64748b; font-size: 12px; padding: 10px 0; font-weight: 600; text-transform: uppercase; }
        .calendar-table td { height: 60px; vertical-align: top; padding: 8px; border: 1px solid #f1f5f9; width: 14.28%; }
        
        .day-container { display: flex; flex-direction: column; align-items: center; justify-content: flex-start; height: 100%; }
        .day-num { font-size: 14px; font-weight: 500; color: #334155; display: inline-block; width: 24px; height: 24px; line-height: 24px; text-align: center; }
        
        .today-highlight .day-num { background: #0d3b66; color: white; border-radius: 50%; }
        .appt-dot { width: 6px; height: 6px; background-color: #38bdf8; border-radius: 50%; margin-top: 5px; }
        
        /* Schedule Styling */
        .schedule-item-pop { background: #f8fafc; border-left: 4px solid #0d3b66; padding: 12px; margin-bottom: 10px; border-radius: 4px; }
        .schedule-item-pop strong { color: #0d3b66; font-size: 14px; }
        .schedule-item-pop span { font-size: 13px; color: #475569; }
        .schedule-item-pop small { color: #64748b; font-weight: bold; }
        
        .cal-nav { display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f8fafc; border-radius: 8px; margin-bottom: 10px; }
        .cal-nav a { text-decoration: none; color: #0d3b66; font-weight: bold; font-size: 18px; }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a class="menu-item active"><span>📊</span> Dashboard</a>
                <a href="dentist_appointments.php" class="menu-item"><span>📅</span> My Appointments</a>
                <a href="dentist_patients.php" class="menu-item"><span>👤</span> My Patients</a>
            </nav>
        </div>
        <div class="sidebar-bottom">
            <a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="header-left">
                <h1 style="color: #0d3b66; font-size: 24px; font-weight: 800;">Welcome, Dr. <?= htmlspecialchars($dentistName) ?></h1>
                <p style="color: #64748b; font-size: 14px;">Here is your clinical overview for today.</p>
            </div>
            <div class="header-right">
                <span class="admin-label">Dentist Panel | 👤</span>
                <div id="liveClock" class="clock">00:00:00 AM</div>
            </div>
        </header>

        <section class="stats-grid">
            <div class="stat-card"><h3><?= $totalAppt ?></h3><p>Your Total Cases</p></div>
            <div class="stat-card"><h3><?= $todayAppt ?></h3><p>Today's Patients</p></div>
            <div class="stat-card"><h3><?= $weekAppt ?></h3><p>Upcoming (Week)</p></div>
            <div class="stat-card"><h3><?= $monthAppt ?></h3><p>Monthly Volume</p></div>
        </section>

        <div class="dashboard-grid">
            <div class="calendar-section">
                <div class="pop-card">
                    <div class="card-header">Your Calendar</div>
                    <div style="padding: 15px;">
                        <div class="cal-nav">
                            <a href="?m=<?= $prevMonth ?>&y=<?= $prevYear ?>">❮</a>
                            <span style="font-weight: 700; color: #0d3b66;"><?= date('F Y', $firstDayOfMonth) ?></span>
                            <a href="?m=<?= $nextMonth ?>&y=<?= $nextYear ?>">❯</a>
                        </div>
                        
                        <table class="calendar-table">
                            <thead>
                                <tr>
                                    <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <?php
                                    // Empty slots before first day
                                    for ($i = 0; $i < $dayOfWeek; $i++) {
                                        echo "<td></td>";
                                    }

                                    for ($day = 1; $day <= $numberDays; $day++) {
                                        // Start new row every 7 days
                                        if (($i + $day - 1) % 7 == 0 && $day != 1) {
                                            echo "</tr><tr>";
                                        }

                                        $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                        $isToday = ($currentDate == date('Y-m-d')) ? 'today-highlight' : '';
                                        $hasAppt = in_array($currentDate, $calendarAppts) ? '<div class="appt-dot"></div>' : '';

                                        echo "<td class='$isToday'>
                                                <div class='day-container'>
                                                    <span class='day-num'>$day</span>
                                                    $hasAppt
                                                </div>
                                              </td>";
                                    }

                                    // Empty slots after last day
                                    while (($i + $day - 1) % 7 != 0) {
                                        echo "<td></td>";
                                        $day++;
                                    }
                                    ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="schedule-section">
                <div class="pop-card">
                    <div class="card-header">Your Schedule for Today</div>
                    <div class="schedule-inner" style="padding: 15px;">
                        <p class="schedule-date" style="font-weight:bold; color:#64748b; margin-bottom:15px;">
                            📅 <?= date('D, M d, Y') ?>
                        </p>
                        
                        <?php if ($scheduleResult->num_rows > 0): ?>
                            <?php while($row = $scheduleResult->fetch_assoc()): ?>
                                <div class="schedule-item-pop">
                                    <small>⏰ <?= date('h:i A', strtotime($row['appointment_time'])) ?></small><br>
                                    <strong><?= htmlspecialchars($row['service_name']) ?></strong><br>
                                    <span>Patient: <?= htmlspecialchars($row['first_name']." ".$row['last_name']) ?></span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align:center; padding: 40px 0;">
                                <p style="color:#94a3b8;">You have no appointments today.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function updateClock() {
        const now = new Date();
        document.getElementById('liveClock').textContent = now.toLocaleTimeString('en-US', { hour12: true });
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>

</body>
</html>