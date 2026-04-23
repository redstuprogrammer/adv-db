<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Dentist') {
    header("Location: login.php");
    exit();
}

$loggedInDentistId = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$today = date('Y-m-d');

// Query Logic
$query = "SELECT a.appointment_id, p.patient_id, p.first_name, p.last_name, s.service_name, 
                 a.appointment_date, a.appointment_time, a.status 
          FROM appointment a
          JOIN patient p ON a.patient_id = p.patient_id
          JOIN service s ON a.service_id = s.service_id
          WHERE a.dentist_id = '$loggedInDentistId'";

if ($filter == 'today') $query .= " AND a.appointment_date = '$today'";
elseif ($filter == 'upcoming') $query .= " AND a.appointment_date > '$today'";

$query .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OralSync | My Appointments</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        /* Repairing the Layout Layout */
        .main-content {
            padding: 30px;
            background: #f8fafc;
            min-height: 100vh;
        }

        /* Search and Filter Row */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
        }

        .search-box {
            padding: 12px 20px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            width: 100%;
            max-width: 400px;
            font-size: 14px;
        }

        .filter-tabs {
            display: flex;
            background: #e2e8f0;
            padding: 5px;
            border-radius: 12px;
            gap: 5px;
        }

        .tab {
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            color: #475569;
            font-weight: 600;
            font-size: 14px;
            transition: 0.3s;
        }

        .tab.active {
            background: #0d3b66;
            color: white;
        }

        /* Appointment Card Design */
        .appt-list-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .appt-card {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            align-items: center;
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            border-left: 6px solid #0d3b66;
            transition: transform 0.2s;
        }

        .appt-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.08); }

        .time-badge {
            text-align: center;
            border-right: 1px solid #e2e8f0;
            padding-right: 15px;
        }

        .time-badge h4 { margin: 0; color: #0d3b66; font-size: 16px; }
        .time-badge p { margin: 0; font-size: 12px; color: #64748b; }

        .patient-details { padding-left: 20px; }
        .patient-details h3 { margin: 0 0 5px 0; color: #1e293b; font-size: 18px; }
        .service-tag { 
            background: #f1f5f9; 
            color: #475569; 
            padding: 3px 10px; 
            border-radius: 5px; 
            font-size: 12px; 
            font-weight: 600; 
        }

        .status-indicator {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 8px;
            display: block;
        }

        .btn-treatment {
            background: #0d3b66;
            color: white !important;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            display: inline-block;
        }

        .hidden-card { display: none !important; }

        /* Status Colors */
        .status-pending { color: #b45309; }
        .status-completed { color: #15803d; }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a href="dentist_dashboard.php" class="menu-item"><span>📊</span> Dashboard</a>
                <a href="dentist_appointments.php" class="menu-item active"><span>📅</span> My Appointments</a>
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
                <h1 style="color: #0d3b66; margin:0;">Clinical Schedule</h1>
                <p style="color: #64748b; font-size: 14px;">Manage your patients and treatments for today.</p>
            </div>
            <div id="liveClock" class="clock">00:00:00 AM</div>
        </header>

        <div class="action-bar">
            <input type="text" id="apptSearch" class="search-box" placeholder="🔍 Search patient name..." onkeyup="filterAppointments()">
            
            <div class="filter-tabs">
                <a href="?filter=all" class="tab <?= $filter == 'all' ? 'active' : '' ?>">All</a>
                <a href="?filter=today" class="tab <?= $filter == 'today' ? 'active' : '' ?>">Today</a>
                <a href="?filter=upcoming" class="tab <?= $filter == 'upcoming' ? 'active' : '' ?>">Upcoming</a>
            </div>
        </div>

        <div class="appt-list-container" id="apptList">
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="appt-card" data-name="<?= strtolower($row['first_name'] . ' ' . $row['last_name']) ?>">
                        <div class="time-badge">
                            <h4><?= date('h:i A', strtotime($row['appointment_time'])) ?></h4>
                            <p><?= date('M d, Y', strtotime($row['appointment_date'])) ?></p>
                        </div>
                        
                        <div class="patient-details">
                            <h3><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']) ?></h3>
                            <span class="service-tag"><?= htmlspecialchars($row['service_name']) ?></span>
                            <span class="status-indicator status-<?= strtolower($row['status']) ?>">
                                ● <?= $row['status'] ?>
                            </span>
                        </div>
                        
                        <div class="appt-actions">
                            <a href="clinical_record.php?id=<?= $row['patient_id'] ?>&appt=<?= $row['appointment_id'] ?>" class="btn-treatment">
                                Open Clinical Log
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align:center; padding: 60px; background:white; border-radius:15px; color:#94a3b8;">
                    <p>No appointments found for this selection.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    function filterAppointments() {
        let input = document.getElementById('apptSearch').value.toLowerCase();
        let cards = document.getElementsByClassName('appt-card');

        for (let i = 0; i < cards.length; i++) {
            let name = cards[i].getAttribute('data-name');
            if (name.includes(input)) {
                cards[i].classList.remove('hidden-card');
            } else {
                cards[i].classList.add('hidden-card');
            }
        }
    }

    function updateClock() {
        document.getElementById('liveClock').textContent = new Date().toLocaleTimeString('en-US', { hour12: true });
    }
    setInterval(updateClock, 1000); updateClock();
</script>

</body>
</html>