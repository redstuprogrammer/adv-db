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

// 2. FETCH DATA SAFETY CHECK
$data = null; // Initialize as null
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT a.*, p.first_name, p.last_name, s.service_name, d.last_name AS d_last 
                            FROM appointment a 
                            JOIN patient p ON a.patient_id = p.patient_id 
                            JOIN service s ON a.service_id = s.service_id 
                            JOIN dentist d ON a.dentist_id = d.dentist_id
                            WHERE a.appointment_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
}

// If no data was found, don't show the errors, just go back
if (!$data) {
    header("Location: receptionist_appoinment.php?error=not_found");
    exit();
}

// 3. UPDATE LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $appt_id = $_POST['appt_id'];
    
    $update_query = "UPDATE appointment SET status = ? WHERE appointment_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $new_status, $appt_id);
    
    if ($stmt->execute()) {
        header("Location: receptionist_appoinment.php?msg=updated");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Manage Appointment</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        /* Exact UI Clone of your sidebar/topbar */
        .manage-card { background: white; border-radius: 12px; padding: 30px; max-width: 600px; margin: 20px auto; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-top: 5px solid #0d3b66; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .info-item label { display: block; font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px; }
        .info-item p { margin: 5px 0 0; font-size: 15px; color: #0d3b66; font-weight: 600; }
        
        .status-select { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; font-size: 15px; margin-top: 10px; outline: none; background: #fcfcfc; }
        .btn-save { background: #0d3b66; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; margin-top: 20px; font-size: 15px; transition: 0.3s; }
        .btn-save:hover { background: #154c82; }
        .btn-back { display: block; text-align: center; margin-top: 15px; color: #64748b; text-decoration: none; font-size: 13px; font-weight: 500; }
        .btn-back:hover { color: #0d3b66; }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a href="receptionist_dashboard.php" class="menu-item"><span>🏠</span> Front Desk</a>
                <a href="receptionist_appointment.php" class="menu-item active"><span>📅</span> Schedule Manager</a>
                <a href="patients.php" class="menu-item"><span>👤</span> Patient Records</a>
                <a href="billing.php" class="menu-item"><span>💳</span> Billing/Payments</a>
            </nav>
        </div>
        <div class="sidebar-bottom">
            <a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="header-left">
                <h1 style="color: #0d3b66; margin:0;">Manage Appointment</h1>
                <p style="color: #64748b;">Update patient status and scheduling</p>
            </div>
            <div class="header-right">
                <div id="liveClock" class="clock">00:00:00 AM</div>
            </div>
        </header>

        <div class="manage-card">
            <h3 style="color: #0d3b66; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">Appointment Details</h3>
            
            <div class="info-grid">
                <div class="info-item">
                    <label>Patient Name</label>
                    <p><?= htmlspecialchars($data['first_name'] . " " . $data['last_name']) ?></p>
                </div>
                <div class="info-item">
                    <label>Service</label>
                    <p><?= htmlspecialchars($data['service_name']) ?></p>
                </div>
                <div class="info-item">
                    <label>Assigned Dentist</label>
                    <p>Dr. <?= htmlspecialchars($data['d_last']) ?></p>
                </div>
                <div class="info-item">
                    <label>Schedule</label>
                    <p><?= date('M d, Y', strtotime($data['appointment_date'])) ?> | <?= date('h:i A', strtotime($data['appointment_time'])) ?></p>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="appt_id" value="<?= $data['appointment_id'] ?>">
                
                <div class="form-group">
                    <label style="font-weight: bold; color: #0d3b66; font-size: 14px;">Update Status:</label>
                    <select name="status" class="status-select">
                        <option value="Pending" <?= ($data['status'] == 'Pending' || $data['status'] == 'pending') ? 'selected' : '' ?>>Pending (Awaiting Arrival)</option>
                        <option value="Completed" <?= ($data['status'] == 'Completed' || $data['status'] == 'completed') ? 'selected' : '' ?>>Completed</option>
                        <option value="Cancelled" <?= ($data['status'] == 'Cancelled' || $data['status'] == 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <button type="submit" name="update_status" class="btn-save">Confirm Update</button>
                <a href="receptionist_appoinment.php" class="btn-back">← Back to Schedule</a>
            </form>
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