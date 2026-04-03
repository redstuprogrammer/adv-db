<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Manila');

// 1. UPDATED SECURITY CHECK (Allows Admin and Receptionist)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Receptionist')) {
    header("Location: login.php");
    exit();
}

$isAdmin = ($_SESSION['role'] === 'Admin');

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    // Fetch patient info
    $sql = "SELECT *, CONCAT(first_name, ' ', last_name) AS full_name FROM patient WHERE patient_id = '$id'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $p = $result->fetch_assoc();
        $birthDate = new DateTime($p['birthdate']);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y . " yrs old";
    } else {
        header("Location: admin_patients.php"); exit();
    }
} else {
    header("Location: admin_patients.php"); exit();
}

// 2. CALCULATE BALANCE
$balance_sql = "
    SELECT 
        (SELECT IFNULL(SUM(s.price), 0) FROM appointment a JOIN service s ON a.service_id = s.service_id WHERE a.patient_id = '$id') 
        - 
        (SELECT IFNULL(SUM(p.amount), 0) FROM payment p JOIN appointment a ON p.appointment_id = a.appointment_id WHERE a.patient_id = '$id') 
    AS total_balance";

$balance_res = $conn->query($balance_sql);
$balance_row = $balance_res->fetch_assoc();
$balance = $balance_row['total_balance'] ?? 0.00;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Patient Profile (Admin)</title>
    <link rel="stylesheet" href="style1.css"> 
    <style>
        body { background-color: #f4f7f9; }
        .profile-container { padding: 30px 45px; }
        .dashboard-grid { display: grid; grid-template-columns: 1fr 320px; gap: 25px; }
        .profile-pop-card {
            background: #fff; padding: 30px; border-radius: 12px;
            display: flex; gap: 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px; align-items: center; border-top: 5px solid #0d3b66;
        }
        .info-details h2 { font-size: 1.8rem; color: #0d3b66; margin-bottom: 10px; }
        .info-details p { margin: 5px 0; font-size: 14px; color: #475569; }
        .info-details b { color: #0d3b66; width: 100px; display: inline-block; }
        .panel { background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 25px; }
        .panel-header { background: #0d3b66; color: #fff; padding: 15px 25px; font-weight: 600; display: flex; justify-content: space-between; }
        .appt-pill {
            display: flex; align-items: center; justify-content: space-between;
            padding: 15px 20px; background: #fff; border: 1px solid #f1f5f9;
            border-radius: 10px; margin-bottom: 12px;
        }
        .balance-box {
            padding: 20px; border-radius: 12px; text-align: center; margin-bottom: 20px;
            background: <?= $balance > 0 ? '#fff1f2' : '#f0fdf4' ?>;
            color: <?= $balance > 0 ? '#be123c' : '#15803d' ?>;
            border: 1px solid <?= $balance > 0 ? '#fecaca' : '#bbf7d0' ?>;
        }
        .action-btn {
            display: block; width: 100%; padding: 12px; margin-bottom: 10px;
            border-radius: 8px; text-align: center; text-decoration: none; 
            font-weight: 600; font-size: 14px; transition: 0.2s; box-sizing: border-box;
        }
        .btn-primary { background: #0d3b66; color: white; }
        .btn-outline { background: #f8fafc; color: #0d3b66; border: 1px solid #0d3b66; }
        .btn-danger { background: #fff1f2; color: #be123c; border: 1px solid #fecaca; }
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
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="header-left">
                <h1 style="color: #0d3b66; margin:0;">Patient Master Profile</h1>
                <p style="color: #64748b;">Clinical & Financial Oversight</p>
            </div>
            <div id="liveClock" class="clock">00:00:00 AM</div>
        </header>

        <div class="profile-container">
            <div class="profile-pop-card">
                <div class="info-details" style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <h2><?= htmlspecialchars($p['full_name']) ?></h2>
                        <span style="background: #e2e8f0; padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; color: #475569;">
                            ID: #<?= str_pad($p['patient_id'], 4, '0', STR_PAD_LEFT) ?>
                        </span>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-top: 10px;">
                        <p><b>Age/Sex:</b> <?= $age ?> | <?= $p['gender'] ?></p>
                        <p><b>Contact:</b> <?= htmlspecialchars($p['contact_number']) ?></p>
                        <p><b>Birthdate:</b> <?= date('M d, Y', strtotime($p['birthdate'])) ?></p>
                        <p><b>Address:</b> <?= htmlspecialchars($p['address']) ?></p>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="main-display">
                    <div class="panel">
                        <div class="panel-header">Appointment History</div>
                        <div style="padding: 20px;">
                            <?php
                            $appt_query = "SELECT a.*, s.service_name FROM appointment a 
                                           JOIN service s ON a.service_id = s.service_id 
                                           WHERE a.patient_id = '$id' ORDER BY a.appointment_date DESC";
                            $appt_res = $conn->query($appt_query);

                            if ($appt_res->num_rows > 0):
                                while($row = $appt_res->fetch_assoc()):
                            ?>
                            <div class="appt-pill">
                                <div>
                                    <div style="font-weight: 700; color: #0d3b66;"><?= date('M d, Y', strtotime($row['appointment_date'])) ?></div>
                                    <div style="font-size: 13px; color: #64748b;"><?= htmlspecialchars($row['service_name']) ?></div>
                                </div>
                                <span style="font-size: 11px; font-weight: bold; color: #94a3b8; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 4px;">
                                    <?= strtoupper($row['status']) ?>
                                </span>
                            </div>
                            <?php endwhile; else: ?>
                                <p style="text-align: center; color: #94a3b8; padding: 20px;">No visit history found.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-header">Clinical Observations</div>
                        <div style="padding: 20px;">
                            <p style="font-size: 14px; color: #334155; background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #cbd5e1;">
                                <strong>Initial Note:</strong><br>
                                <?= !empty($p['note']) ? htmlspecialchars($p['note']) : 'No clinical notes recorded.' ?>
                            </p>
                        </div>
                    </div>
                </div>

                <aside>
                    <div class="balance-box">
                        <small style="font-weight: bold; text-transform: uppercase;">Outstanding Balance</small>
                        <div style="font-size: 28px; font-weight: 800; margin: 10px 0;">₱ <?= number_format($balance, 2) ?></div>
                        <small style="opacity: 0.8;"><?= $balance > 0 ? 'Action Required' : 'Account Cleared' ?></small>
                    </div>

                    <div class="panel" style="padding: 20px;">
                        <h4 style="margin-top: 0; color: #0d3b66; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 15px;">Admin Controls</h4>
                        <a href="billing.php?patient_id=<?= $id ?>" class="action-btn btn-primary">₱ Manage Billing</a>
                        <a href="appointments.php?patient_id=<?= $id ?>" class="action-btn btn-outline">📅 Book Appointment</a>
                        <a href="edit_patient.php?id=<?= $id ?>" class="action-btn btn-outline">✏️ Edit Demographics</a>
                        
                        <?php if($isAdmin): ?>
                        <hr style="border: 0; border-top: 1px solid #f1f5f9; margin: 15px 0;">
                        <a href="delete_patient.php?id=<?= $id ?>" 
                           onclick="return confirm('CRITICAL: This will erase all history for this patient. This action is logged.')" 
                           class="action-btn btn-danger">🗑️ Erase Record</a>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</div>



<script>
    function updateClock() {
        document.getElementById('liveClock').textContent = new Date().toLocaleTimeString('en-US', { hour12: true });
    }
    setInterval(updateClock, 1000); 
    updateClock();
</script>

</body>
</html>