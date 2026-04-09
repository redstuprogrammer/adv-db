<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Receptionist' && $_SESSION['role'] !== 'Staff')) {
    header("Location: login.php");
    exit();
}

$receptionistName = $_SESSION['username'] ?? 'Receptionist';

/* ============================================================
   2. FIXED DATA FETCHING
   Using LEFT JOIN ensures rows show even if Service ID is missing
============================================================ */
$query = "SELECT 
            a.appointment_id, 
            a.appointment_date, 
            a.appointment_time, 
            p.first_name, 
            p.last_name, 
            COALESCE(s.service_name, 'Unassigned') AS service_name, 
            d.last_name AS d_last, 
            a.status 
          FROM appointment a 
          LEFT JOIN patient p ON a.patient_id = p.patient_id 
          LEFT JOIN service s ON a.service_id = s.service_id 
          LEFT JOIN dentist d ON a.dentist_id = d.dentist_id
          ORDER BY a.appointment_date DESC, a.appointment_time ASC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Schedule Manager</title>
    <link rel="stylesheet" href="style1.css"> 
    <style>
        /* Exact UI match to your Patient Records screenshot */
        .queue-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
        .queue-table th { background: #f8fafc; color: #64748b; padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; }
        .queue-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        
        .time-badge { background: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .action-link { color: #0d3b66; text-decoration: none; font-weight: 600; font-size: 13px; border: 1px solid #0d3b66; padding: 5px 12px; border-radius: 6px; transition: 0.2s; }
        .action-link:hover { background: #0d3b66; color: white; }

        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-pill.pending { background: #fff3cd; color: #856404; }
        .status-pill.completed { background: #dcfce7; color: #166534; }
        .status-pill.cancelled { background: #fee2e2; color: #991b1b; }

        .alert-box { padding: 15px; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a href="receptionist_dashboard.php" class="menu-item"><span>🏠</span> Front Desk</a>
                <a class="menu-item active"><span>📅</span> Schedule Manager</a>
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
                <h1 style="color: #0d3b66; margin:0;">Schedule Manager</h1>
                <p style="color: #64748b;">Welcome back, <?= htmlspecialchars($receptionistName) ?></p>
            </div>
            <div class="header-right">
                <div id="liveClock" class="clock" style="background: #e0f2fe; padding: 8px 15px; border-radius: 8px; font-weight: bold; color: #0369a1;">00:00:00 AM</div>
            </div>
        </header>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
            <div class="alert-box">✅ Appointment updated successfully!</div>
        <?php endif; ?>

        <div class="pop-card" style="margin-top: 20px;">
            <div class="card-header">
                <span>All Appointments Master List</span>
                <a href="add_appointment.php" class="add-btn" style="text-decoration:none;">+</a>
            </div>
            
            <div style="padding: 10px;">
                <table class="queue-table">
                    <thead>
                        <tr>
                            <th>Time & Date</th>
                            <th>Patient</th>
                            <th>Dentist</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="time-badge"><?= date('h:i A', strtotime($row['appointment_time'])) ?></span>
                                        <div style="font-size: 11px; color: #64748b; margin-top:4px;"><?= date('M d, Y', strtotime($row['appointment_date'])) ?></div>
                                    </td>
                                    <td><strong><?= htmlspecialchars($row['first_name']." ".$row['last_name']) ?></strong></td>
                                    <td>Dr. <?= htmlspecialchars($row['d_last']) ?></td>
                                    <td><?= htmlspecialchars($row['service_name']) ?></td>
                                    <td><span class="status-pill <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span></td>
                                    <td>
                                        <a href="manage_appointment.php?id=<?= $row['appointment_id'] ?>" class="action-link">Manage</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:30px;">No appointments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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