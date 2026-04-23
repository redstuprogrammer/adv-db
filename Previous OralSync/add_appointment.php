<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

// 1. SECURITY CHECK (Receptionist/Staff Only)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Receptionist' && $_SESSION['role'] !== 'Staff')) {
    header("Location: login.php");
    exit();
}

$receptionistName = $_SESSION['username'] ?? 'Receptionist';
$error = "";

// 2. FETCH DATA FOR DROPDOWNS
$patients = $conn->query("SELECT patient_id, first_name, last_name FROM patient ORDER BY last_name ASC");
$services = $conn->query("SELECT service_id, service_name FROM service ORDER BY service_name ASC");

// FIXED: Using 'username' because 'full_name' is missing from your users table
$dentists = $conn->query("SELECT user_id, username FROM users WHERE role = 'Dentist' ORDER BY username ASC");

// 3. INSERT LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_appointment'])) {
    $p_id = $_POST['patient_id'];
    $s_id = $_POST['service_id'];
    $d_id = $_POST['dentist_id'];
    $date = $_POST['appt_date'];
    $time = $_POST['appt_time']; 
    $status = "Pending";

    // --- AVAILABILITY CHECK ---
    $check_query = "SELECT appointment_id FROM appointment 
                    WHERE dentist_id = ? 
                    AND appointment_date = ? 
                    AND status != 'Cancelled'
                    AND (
                        (appointment_time <= ? AND DATE_ADD(appointment_time, INTERVAL 29 MINUTE) >= ?) OR
                        (? <= appointment_time AND DATE_ADD(?, INTERVAL 29 MINUTE) >= appointment_time)
                    )";
    
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("isssss", $d_id, $date, $time, $time, $time, $time);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $error = "⚠️ This dentist is already booked or has an overlapping appointment near this time.";
    } else {
        $stmt = $conn->prepare("INSERT INTO appointment (patient_id, service_id, dentist_id, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisss", $p_id, $s_id, $d_id, $date, $time, $status);

        if ($stmt->execute()) {
            header("Location: receptionist_appoinment.php?status=success");
            exit();
        } else {
            $error = "❌ Database Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | New Appointment</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        .form-card { background: white; border-radius: 12px; padding: 35px; max-width: 750px; margin: 20px auto; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-top: 6px solid #0d3b66; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .full-width { grid-column: span 2; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #0d3b66; margin-bottom: 8px; text-transform: uppercase; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; background: #f8fafc; box-sizing: border-box; }
        .error-banner { background: #fef2f2; color: #b91c1c; padding: 15px; border-radius: 8px; border: 1px solid #fecaca; margin-bottom: 25px; font-weight: 600; }
        .btn-submit { background: #0d3b66; color: white; border: none; padding: 15px; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; font-size: 16px; transition: 0.3s; }
        .btn-submit:hover { background: #092a4a; }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a href="receptionist_dashboard.php" class="menu-item"><span>🏠</span> Front Desk</a>
                <a href="receptionist_appoinment.php" class="menu-item active"><span>📅</span> Schedule Manager</a>
                <a href="patients.php" class="menu-item"><span>👤</span> Patient Records</a>
                <a href="receptionist_billing.php" class="menu-item"><span>💳</span> Billing/Payments</a>
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="header-left">
                <h1 style="color: #0d3b66; margin:0;">New Appointment</h1>
                <p style="color: #64748b;">Link patient to dentist account</p>
            </div>
        </header>

        <div class="form-card">
            <?php if(!empty($error)): ?>
                <div class="error-banner"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Select Patient</label>
                        <select name="patient_id" class="form-control" required>
                            <option value="">-- Choose Patient --</option>
                            <?php while($p = $patients->fetch_assoc()): ?>
                                <option value="<?= $p['patient_id'] ?>"><?= htmlspecialchars($p['last_name'] . ", " . $p['first_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Service Type</label>
                        <select name="service_id" class="form-control" required>
                            <option value="">-- Choose Procedure --</option>
                            <?php while($s = $services->fetch_assoc()): ?>
                                <option value="<?= $s['service_id'] ?>"><?= htmlspecialchars($s['service_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Assigned Dentist</label>
                        <select name="dentist_id" class="form-control" required>
                            <option value="">-- Select Dentist --</option>
                            <?php if($dentists->num_rows > 0): ?>
                                <?php while($d = $dentists->fetch_assoc()): ?>
                                    <option value="<?= $d['user_id'] ?>">Dr. <?= htmlspecialchars($d['username']) ?></option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option disabled>No dentists found in users table</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="appt_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Time</label>
                        <input type="time" name="appt_time" class="form-control" required>
                    </div>
                </div>

                <button type="submit" name="add_appointment" class="btn-submit">Confirm Booking</button>
                <a href="receptionist_appoinment.php" style="display:block; text-align:center; margin-top:15px; color:#64748b; text-decoration:none;">← Cancel</a>
            </form>
        </div>
    </main>
</div>
</body>
</html>