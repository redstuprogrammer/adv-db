<?php
include "db.php"; 

// Set timezone
date_default_timezone_set('Asia/Manila');
$todayDate = date('Y-m-d');

/* =========================
   1. DATA FETCHING (STATS)
========================= */
$totalAppt  = $conn->query("SELECT COUNT(*) AS total FROM appointment")->fetch_assoc()['total'] ?? 0;
$todayAppt  = $conn->query("SELECT COUNT(*) AS total FROM appointment WHERE appointment_date = '$todayDate'")->fetch_assoc()['total'] ?? 0;
$weekAppt   = $conn->query("SELECT COUNT(*) AS total FROM appointment WHERE YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1)")->fetch_assoc()['total'] ?? 0;
$monthAppt  = $conn->query("SELECT COUNT(*) AS total FROM appointment WHERE MONTH(appointment_date) = MONTH(CURRENT_DATE()) AND YEAR(appointment_date) = YEAR(CURRENT_DATE())")->fetch_assoc()['total'] ?? 0;

/* =========================
   2. TODAY'S SCHEDULE
========================= */
$scheduleQuery = "SELECT a.appointment_time, p.first_name, p.last_name, s.service_name 
                  FROM appointment a 
                  JOIN patient p ON a.patient_id = p.patient_id 
                  JOIN service s ON a.service_id = s.service_id 
                  WHERE a.appointment_date = '$todayDate' 
                  ORDER BY a.appointment_time ASC";
$scheduleResult = $conn->query($scheduleQuery);

/* =========================
   3. DYNAMIC CALENDAR LOGIC
========================= */
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$numberDays = date('t', $firstDayOfMonth);
$dateComponents = getdate($firstDayOfMonth);
$dayOfWeek = $dateComponents['wday']; 

$prevMonth = ($month == 1) ? 12 : $month - 1;
$prevYear  = ($month == 1) ? $year - 1 : $year;
$nextMonth = ($month == 12) ? 1 : $month + 1;
$nextYear  = ($month == 12) ? $year + 1 : $year;

$calendarAppts = [];
$calQuery = "SELECT DISTINCT appointment_date FROM appointment WHERE MONTH(appointment_date) = '$month' AND YEAR(appointment_date) = '$year'";
$calRes = $conn->query($calQuery);
if($calRes){
    while($row = $calRes->fetch_assoc()){
        $calendarAppts[] = $row['appointment_date'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OralSync | Dashboard</title>
    <link rel="stylesheet" href="style1.css"> 
    <style>
        .nav-arrow { text-decoration: none; color: #333; font-weight: bold; padding: 5px 12px; background: #eee; border-radius: 5px; }
        .nav-arrow:hover { background: #ddd; }
        #searchStatus { font-size: 12px; font-weight: 500; }
        
        /* Validation Styling */
        input[type="date"]:invalid { border: 2px solid #ff4d4d; }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box">
                <img src="oral logo.png" alt="OralSync" class="main-logo">
            </div>
            <nav class="menu">
                <a class="menu-item active"><span>📊</span> Dashboard</a>
                <a href="appointments.php" class="menu-item"><span>📅</span> Appointment</a>
                <a href="patients.php" class="menu-item"><span>👤</span> Patient</a>
                <a href="dentists.php" class="menu-item"><span>👨‍⚕️</span> Dentist</a>
                <a href="reports.php" class="menu-item"><span>📄</span> Report</a>
            </nav>
        </div>
        <div class="sidebar-bottom">
            <a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="header-right">
                <span class="admin-label">Admin | 👤</span>
                <div id="liveClock" class="clock">00:00:00 AM</div>
            </div>
        </header>

        <section class="stats-grid">
            <div class="stat-card"><h3><?= $totalAppt ?></h3><p>Total Appointment</p></div>
            <div class="stat-card"><h3><?= $todayAppt ?></h3><p>Today's Appointment</p></div>
            <div class="stat-card"><h3><?= $weekAppt ?></h3><p>This week</p></div>
            <div class="stat-card"><h3><?= $monthAppt ?></h3><p>This month</p></div>
        </section>

        <div class="dashboard-grid">
            <div class="calendar-section">
                <div class="pop-card">
                    <div class="card-header">
                        <span>Calendar Overview</span>
                        <button class="add-btn" title="Add Appointment">+</button>
                    </div>
                    <div class="calendar-controls">
                        <a href="?m=<?= $prevMonth ?>&y=<?= $prevYear ?>" class="nav-arrow">&lt;</a>
                        <h2 class="current-month"><?= date('F Y', $firstDayOfMonth) ?></h2>
                        <a href="?m=<?= $nextMonth ?>&y=<?= $nextYear ?>" class="nav-arrow">&gt;</a>
                    </div>
                    <table class="calendar-table">
                        <thead>
                            <tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                            <?php
                            for ($i = 0; $i < $dayOfWeek; $i++) echo "<td class='inactive'></td>";
                            $currentDay = 1;
                            while ($currentDay <= $numberDays) {
                                if ($dayOfWeek == 7) {
                                    $dayOfWeek = 0;
                                    echo "</tr><tr>";
                                }
                                $fullDate = sprintf('%04d-%02d-%02d', $year, $month, $currentDay);
                                $class = ($fullDate == $todayDate) ? 'today' : '';
                                echo "<td class='$class'>$currentDay";
                                if (in_array($fullDate, $calendarAppts)) echo "<br><span class='pin'></span>";
                                echo "</td>";
                                $currentDay++;
                                $dayOfWeek++;
                            }
                            while ($dayOfWeek < 7) { echo "<td class='inactive'></td>"; $dayOfWeek++; }
                            ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="schedule-section">
                <div class="pop-card">
                    <div class="card-header">Today's Schedule</div>
                    <div class="schedule-inner">
                        <p class="schedule-date"><?= date('D M d, Y') ?></p>
                        <?php if ($scheduleResult && $scheduleResult->num_rows > 0): ?>
                            <?php while($row = $scheduleResult->fetch_assoc()): ?>
                                <div class="schedule-item-pop">
                                    <div class="item-info">
                                        <strong><?= htmlspecialchars($row['service_name']) ?></strong>
                                        <span><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']) ?></span>
                                        <br><small><?= date('h:i a', strtotime($row['appointment_time'])) ?></small>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="no-data"><p>No appointments for today.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="schedModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Appointment</h3>
            <span class="close-btn">&times;</span>
        </div>
        <form action="appointments.php" method="POST" id="apptForm">
            <div class="form-group">
                <label>Name of Patient</label>
                <input type="text" name="patient_name" id="patientSearchInput" list="patientList" placeholder="Search registered name..." required autocomplete="off">
                <datalist id="patientList">
                    <?php
                    $pResult = $conn->query("SELECT first_name, last_name FROM patient");
                    while($pRow = $pResult->fetch_assoc()) {
                        echo "<option value='".$pRow['first_name']." ".$pRow['last_name']."'>";
                    }
                    ?>
                </datalist>
                <small id="searchStatus"></small>
            </div>
            <div class="form-group">
                <label>Procedure</label>
                <select name="service_id">
                    <?php
                    $sResult = $conn->query("SELECT * FROM service");
                    while($s = $sResult->fetch_assoc()) echo "<option value='".$s['service_id']."'>".$s['service_name']."</option>";
                    ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Time</label><input type="time" name="appt_time" required></div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="appt_date" id="apptDateInput" value="<?= $todayDate ?>" min="<?= $todayDate ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-close" id="cancelBtn">Close</button>
                <button type="submit" class="btn-save">+ New Appointment</button>
            </div>
        </form>
    </div>
</div>

<script>
    // 1. Clock
    function updateClock() {
        const now = new Date();
        document.getElementById('liveClock').textContent = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true 
        });
    }
    setInterval(updateClock, 1000);
    updateClock();

    // 2. Modal Controls
    const modal = document.getElementById("schedModal");
    document.querySelector(".add-btn").onclick = () => modal.style.display = "flex";
    document.querySelector(".close-btn").onclick = () => modal.style.display = "none";
    document.getElementById("cancelBtn").onclick = () => modal.style.display = "none";
    window.onclick = (e) => { if (e.target == modal) modal.style.display = "none"; }

    // 3. Form Validation (Past Date Check)
    document.getElementById('apptForm').addEventListener('submit', function(e) {
        const selectedDate = new Date(document.getElementById('apptDateInput').value);
        const today = new Date();
        // Reset hours for a fair date comparison
        today.setHours(0, 0, 0, 0);

        if (selectedDate < today) {
            e.preventDefault(); // Stop form submission
            alert("Error: You cannot book an appointment for a past date.");
        }
    });

    // 4. Patient Search Validation
    document.getElementById('patientSearchInput').addEventListener('input', function() {
        const options = document.getElementById('patientList').options;
        const status = document.getElementById('searchStatus');
        let found = false;
        for (let i = 0; i < options.length; i++) {
            if (options[i].value === this.value) { found = true; break; }
        }
        if (this.value === "") { status.innerHTML = ""; }
        else if (found) { status.innerHTML = "✅ Found"; status.style.color = "green"; }
        else { status.innerHTML = "❌ Not Registered"; status.style.color = "red"; }
    });
</script>
</body>
</html>