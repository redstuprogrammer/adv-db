<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Dentist') {
    header("Location: login.php");
    exit();
}

// Get the Dentist ID from session
$dentist_id = $_SESSION['user_id']; 

// 2. DATA FETCHING: Filtered by the logged-in Dentist
// We use INNER JOIN to ensure only patients with appointments with THIS dentist appear
$query = "SELECT p.*, MAX(a.appointment_date) as last_visit 
          FROM patient p 
          INNER JOIN appointment a ON p.patient_id = a.patient_id 
          WHERE a.dentist_id = '$dentist_id'
          GROUP BY p.patient_id
          ORDER BY p.last_name ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | My Patients</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        .panel-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 30px; margin-top: 20px; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th { background: #0d3b66; color: #fff; padding: 15px; text-align: left; font-size: 14px; }
        .data-table td { padding: 15px; border-bottom: 1px solid #eee; color: #333; font-size: 14px; }
        .clickable-row { cursor: pointer; transition: background 0.2s; }
        .clickable-row:hover { background-color: #f4faff !important; }
        .status-pill { padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-pill.active { background: #d4edda; color: #155724; }
        .status-pill.inactive { background: #f1f5f9; color: #64748b; }
        .search-box { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; width: 320px; outline: none; }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a href="dentist_dashboard.php" class="menu-item"><span>📊</span> Dashboard</a>
                <a href="dentist_appointments.php" class="menu-item"><span>📅</span> Appointment</a>
                <a href="dentist_patients.php" class="menu-item active"><span>👤</span> My Patients</a>
            </nav>
        </div>
        <div class="sidebar-bottom">
            <a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="header-left">
                <h2 style="color: #0d3b66; margin:0;">My Patient Directory</h2>
                <p style="color: #64748b; font-size: 14px;">Viewing patients specifically assigned to your clinical schedule.</p>
            </div>
            <div style="display: flex; align-items: center;">
                <div id="liveClock" class="clock"></div>
                <div class="admin-profile" style="margin-left: 20px; font-weight: 600; color: #0d3b66;">Dr. <?= htmlspecialchars($_SESSION['username']) ?> | 👨‍⚕️</div>
            </div>
        </header>

        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <h3 style="color: #0d3b66; margin:0;">Personal Patient List</h3>
                    <span style="font-size: 12px; color: #64748b;">Showing only patients you have treated or have scheduled</span>
                </div>
                <div class="header-controls">
                    <input type="text" id="pSearch" onkeyup="searchTable()" placeholder="🔍 Search your patients..." class="search-box">
                </div>
            </div>

            <table class="data-table" id="pTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient Name</th>
                        <th>Contact</th>
                        <th>Your Last Session</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 1;
                    if ($result->num_rows > 0):
                        while($row = $result->fetch_assoc()): 
                            $isActive = ($row['last_visit'] && strtotime($row['last_visit']) > strtotime('-1 year'));
                    ?>
                    <tr class="clickable-row" onclick="window.location='dentist_patient_view.php?id=<?= $row['patient_id'] ?>'">
                        <td style="color: #64748b;">#<?= str_pad($count++, 3, '0', STR_PAD_LEFT) ?></td>
                        <td style="font-weight: 600; color: #0d3b66;"><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']) ?></td>
                        <td><?= htmlspecialchars($row['contact_number']) ?></td>
                        <td><?= $row['last_visit'] ? date('M d, Y', strtotime($row['last_visit'])) : 'No record' ?></td>
                        <td>
                            <span class="status-pill <?= $isActive ? 'active' : 'inactive' ?>">
                                <?= $isActive ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: #94a3b8;">
                            <div style="font-size: 40px; margin-bottom: 10px;">📂</div>
                            You don't have any patients scheduled yet.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
    function updateClock() {
        const now = new Date();
        document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true 
        });
    }
    setInterval(updateClock, 1000);
    updateClock();

    function searchTable() {
        let input = document.getElementById("pSearch").value.toUpperCase();
        let rows = document.getElementById("pTable").getElementsByTagName("tr");
        for (let i = 1; i < rows.length; i++) {
            let name = rows[i].getElementsByTagName("td")[1].textContent.toUpperCase();
            rows[i].style.display = name.includes(input) ? "" : "none";
        }
    }
</script>
</body>
</html>