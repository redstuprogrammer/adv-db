<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

// 1. SECURITY CHECK (Receptionist/Staff Only)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Receptionist' && $_SESSION['role'] !== 'Staff')) {
    header("Location: login.php");
    exit();
}

$todayDate = date('Y-m-d');
$receptionistName = $_SESSION['username'] ?? 'Receptionist';

/* =========================
   2. RECEPTIONIST METRICS
========================= */
// 1. Pending Appointments (Patients yet to arrive/be seen)
$pendingCount = $conn->query("SELECT COUNT(*) AS total FROM appointment WHERE appointment_date = '$todayDate' AND status = 'Pending'")->fetch_assoc()['total'] ?? 0;

// 2. Completed Today (Patients who finished their session)
$completedCount = $conn->query("SELECT COUNT(*) AS total FROM appointment WHERE appointment_date = '$todayDate' AND status = 'Completed'")->fetch_assoc()['total'] ?? 0;

// 3. New Patients Added This Month
$newPatients = $conn->query("SELECT COUNT(*) AS total FROM patient WHERE MONTH(created_at) = MONTH(CURRENT_DATE())")->fetch_assoc()['total'] ?? 0;

/* =========================
   3. ARRIVAL LIST (QUEUE)
========================= */
$queueQuery = "SELECT a.appointment_id, a.appointment_time, p.first_name, p.last_name, s.service_name, d.last_name AS d_last, a.status 
               FROM appointment a 
               JOIN patient p ON a.patient_id = p.patient_id 
               JOIN service s ON a.service_id = s.service_id 
               JOIN dentist d ON a.dentist_id = d.dentist_id
               WHERE a.appointment_date = '$todayDate' 
               ORDER BY a.appointment_time ASC";
$queueResult = $conn->query($queueQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Receptionist</title>
    <link rel="stylesheet" href="style1.css"> 
    <style>
        /* Receptionist Specific Colors */
        .stat-card.blue { border-left: 5px solid #3498db; }
        .stat-card.green { border-left: 5px solid #2ecc71; }
        .stat-card.orange { border-left: 5px solid #f39c12; }
        
        .queue-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
        .queue-table th { background: #f8fafc; color: #64748b; padding: 15px; text-align: left; font-size: 12px; text-transform: uppercase; }
        .queue-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; }
        
        .time-badge { background: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .action-link { color: #0d3b66; text-decoration: none; font-weight: 600; font-size: 13px; }
        .action-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a class="menu-item active"><span>🏠</span> Front Desk</a>
                <a href="receptionist_appoinment.php" class="menu-item"><span>📅</span> Schedule Manager</a>
                <a href="patients.php" class="menu-item"><span>👤</span> Patient Records</a>
                <a href="receptionist_billing.php" class="menu-item"><span>💳</span> Billing/Payments</a>
            </nav>
        </div>
        <div class="sidebar-bottom">
            <a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="header-left">
                <h1 style="color: #0d3b66; margin:0;">Front Desk Overview</h1>
                <p style="color: #64748b;">Welcome back, <?= htmlspecialchars($receptionistName) ?></p>
            </div>
            <div class="header-right">
                <div id="liveClock" class="clock">00:00:00 AM</div>
            </div>
        </header>

        <section class="stats-grid">
            <div class="stat-card orange"><h3><?= $pendingCount ?></h3><p>Waiting/Pending</p></div>
            <div class="stat-card green"><h3><?= $completedCount ?></h3><p>Check-outs Done</p></div>
            <div class="stat-card blue"><h3><?= $newPatients ?></h3><p>New Patients (Month)</p></div>
        </section>

        <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">
            <div class="queue-section">
                <div class="pop-card">
                    <div class="card-header">
                        <span>Today's Patient Arrival List</span>
                        <a href="appointments.php" class="add-btn" style="text-decoration:none; display:flex; align-items:center; justify-content:center;">+</a>
                    </div>
                    <div style="padding: 10px;">
                        <table class="queue-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    <th>Dentist</th>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($queueResult->num_rows > 0): ?>
                                    <?php while($row = $queueResult->fetch_assoc()): ?>
                                        <tr>
                                            <td><span class="time-badge"><?= date('h:i A', strtotime($row['appointment_time'])) ?></span></td>
                                            <td><strong><?= htmlspecialchars($row['first_name']." ".$row['last_name']) ?></strong></td>
                                            <td>Dr. <?= htmlspecialchars($row['d_last']) ?></td>
                                            <td><?= htmlspecialchars($row['service_name']) ?></td>
                                            <td><span class="status-pill <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span></td>
                                            <td><a href="appointments.php" class="action-link">Manage</a></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center; padding:20px;">No patients scheduled for today.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="quick-actions">
                <div class="pop-card" style="margin-bottom: 20px;">
                    <div class="card-header">Quick Tasks</div>
                    <div style="padding: 15px;">
                        <button onclick="location.href='patients.php'" style="width:100%; padding:12px; margin-bottom:10px; border:none; border-radius:8px; background:#0d3b66; color:white; cursor:pointer;">🆕 Register New Patient</button>
                        <button onclick="location.href='receptionist_billing.php'" style="width:100%; padding:12px; border:1px solid #0d3b66; border-radius:8px; background:white; color:#0d3b66; cursor:pointer;">💵 Process Payment</button>
                    </div>
                </div>
                
                <div class="pop-card">
                    <div class="card-header">Clinic Notice</div>
                    <div style="padding: 15px; font-size: 13px; color: #64748b;">
                        <p><strong>Reminder:</strong> Please verify patient contact information during check-in for the automated SMS reminders.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function updateClock() {
        document.getElementById('liveClock').textContent = new Date().toLocaleTimeString('en-US', { hour12: true });
    }
    setInterval(updateClock, 1000); updateClock();
</script>
</body>
</html>