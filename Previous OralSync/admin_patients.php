<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

// 1. SECURITY CHECK (Allow Admin & Receptionist)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Receptionist')) {
    header("Location: login.php");
    exit();
}

$currentUserName = $_SESSION['username'] ?? 'User';
$isAdmin = ($_SESSION['role'] === 'Admin');

// 2. FETCH PATIENTS & LATEST VISITS
$query = "SELECT p.*, MAX(a.appointment_date) as last_visit 
          FROM patient p 
          LEFT JOIN appointment a ON p.patient_id = a.patient_id 
          GROUP BY p.patient_id
          ORDER BY p.patient_id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Patient Directory</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        /* Layout & Table */
        .queue-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
        .queue-table th { background: #f8fafc; color: #64748b; padding: 15px; text-align: left; font-size: 12px; text-transform: uppercase; border-bottom: 2px solid #edf2f7; }
        .queue-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        .clickable-row { cursor: pointer; transition: background 0.2s; }
        .clickable-row:hover { background-color: #f4faff !important; }

        /* Status Pills */
        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-pill.active { background: #dcfce7; color: #166534; }
        .status-pill.inactive { background: #f1f5f9; color: #64748b; }

        .search-container { padding: 15px 20px; background: #fff; border-bottom: 1px solid #f1f5f9; }
        .p-search-input { width: 100%; max-width: 400px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 25px; outline: none; padding-left: 20px; }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a href="dashboard.php" class="menu-item"><span>📊</span> Overview</a>
                <a href="manage_users.php" class="menu-item"><span>👥</span> Users</a>
                <a href="logs.php" class="menu-item"><span>📄</span> Reports</a>
                <a href="services.php" class="menu-item"><span>🦷</span> Services</a>
                <a href="staff.php" class="menu-item"><span>👨‍⚕️</span> Staff</a>
                 <a href="admin_patients.php" class="menu-item active"><span>👤</span> Patients</a>
                 <a href="admin_appointments.php" class="menu-item"><span>📅</span> Appointments</a>
                 <a href="admin_billing.php" class="menu-item"><span>💰</span> Billing</a>
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="header-left">
                <h1 style="color: #0d3b66; margin:0;">Patient Directory</h1>
                <p style="color: #64748b;">Clinical Records Access</p>
            </div>
            <div id="liveClock" class="clock">00:00:00 AM</div>
        </header>

        <div class="pop-card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <span>Registered Patients (<?= $result->num_rows ?>)</span>
                </div>
            
            <div class="search-container">
                <input type="text" id="pSearch" onkeyup="searchTable()" placeholder="🔍 Search patients by name or ID..." class="p-search-input">
            </div>

            <div style="padding: 10px;">
                <table class="queue-table" id="pTable">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Full Name</th>
                            <th>Contact</th>
                            <th>Last Visit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): 
                                $isActive = ($row['last_visit'] && strtotime($row['last_visit']) > strtotime('-1 year'));
                            ?>
                            <tr class="clickable-row" onclick="window.location='admin_patient_view.php?id=<?= $row['patient_id'] ?>'">
                                <td style="font-family: monospace; font-weight: bold;">
                                    #<?= str_pad($row['patient_id'], 4, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($row['contact_number']) ?></td>
                                <td><?= $row['last_visit'] ? date('M d, Y', strtotime($row['last_visit'])) : '<span style="color:#cbd5e1;">No visits</span>' ?></td>
                                <td>
                                    <span class="status-pill <?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:50px; color:#94a3b8;">No records found in the directory.</td></tr>
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

    function searchTable() {
        let input = document.getElementById("pSearch").value.toUpperCase();
        let rows = document.querySelectorAll("#pTable tbody tr"); 
        rows.forEach(row => {
            let text = row.innerText.toUpperCase();
            row.style.display = text.includes(input) ? "" : "none";
        });
    }
</script>
</body>
</html>