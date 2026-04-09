<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Manila');

// Ensure only Dentist can see this
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Dentist') {
    header("Location: login.php"); exit();
}

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    // Added 'medical_history' to the query (ensure this exists in your 'patient' table)
    $sql = "SELECT *, CONCAT(first_name, ' ', last_name) AS full_name FROM patient WHERE patient_id = '$id'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $p = $result->fetch_assoc();
        $birthDate = new DateTime($p['birthdate']);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y . " yrs old";
    } else {
        header("Location: dentist_dashboard.php"); exit();
    }
} else {
    header("Location: dentist_dashboard.php"); exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clinical Profile | <?= htmlspecialchars($p['full_name']) ?></title>
    <link rel="stylesheet" href="style1.css"> 
    <style>
        body { background-color: #f4f7f9; }
        .profile-container { padding: 30px 45px; }
        
        /* Two-Column Clinical Layout */
        .clinical-grid { display: grid; grid-template-columns: 1.2fr 1.8fr; gap: 25px; }

        .profile-pop-card {
            background: #fff; padding: 30px; border-radius: 12px;
            display: flex; gap: 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px; border-left: 6px solid #0d3b66;
        }

        /* Medical Alert: Red if danger exists, Grey if clean */
        .medical-alert {
            background: <?= !empty($p['medical_history']) ? '#fff5f5' : '#f8fafc' ?>;
            border: 1px solid <?= !empty($p['medical_history']) ? '#feb2b2' : '#e2e8f0' ?>;
            color: <?= !empty($p['medical_history']) ? '#c53030' : '#64748b' ?>;
            padding: 12px 15px; border-radius: 8px; font-size: 13px; font-weight: 600;
        }

        .appointments-panel, .notes-panel {
            background: #fff; border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); height: fit-content;
        }

        .panel-header-blue {
            background: #0d3b66; color: #fff; padding: 15px 25px;
            font-weight: 600; display: flex; justify-content: space-between; align-items: center;
        }

        .appt-pill {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 20px; background: #fff; border: 1px solid #f0f0f0;
            border-radius: 10px; margin-bottom: 10px; transition: 0.2s;
        }
        .appt-pill:hover { border-color: #0d3b66; background: #fcfdfe; }

        .indicator { width: 4px; height: 30px; background: #0d3b66; border-radius: 2px; margin-right: 15px; }

        .note-item { border-bottom: 1px solid #f1f5f9; padding: 15px 20px; }
        .note-item:last-child { border: none; }
        .note-date { font-size: 11px; color: #94a3b8; font-weight: 800; text-transform: uppercase; }
        .note-text { font-size: 14px; color: #334155; margin-top: 8px; line-height: 1.6; white-space: pre-wrap; }
        
        .btn-add-note { 
            background: #fff; color: #0d3b66; padding: 5px 12px; 
            border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a href="dentist_dashboard.php" class="menu-item"><span>📊</span> Dashboard</a>
                <a href="dentist_appointments.php" class="menu-item"><span>📅</span> My Schedule</a>
                <a href="patient.php" class="menu-item active"><span>👤</span> Patient Records</a>
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div id="liveClock" class="clock"></div>
            <div class="admin-profile" style="margin-left: 20px; font-weight: 600; color: #0d3b66;">
                Dr. <?= htmlspecialchars($_SESSION['username'] ?? 'Dentist') ?> | 👨‍⚕️
            </div>
        </header>

        <div class="profile-container">
            <div class="profile-pop-card">
                <div class="profile-pic-container">
                    <div style="font-size: 40px; color: #0d3b66; font-weight: bold;">
                        <?= strtoupper($p['first_name'][0] . $p['last_name'][0]) ?>
                    </div>
                </div>
                <div class="info-details" style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <h2><?= htmlspecialchars($p['full_name']) ?></h2>
                        <span style="font-size: 12px; background: #e0f2fe; color: #0369a1; padding: 2px 10px; border-radius: 10px; font-weight: bold;">PID-<?= $id ?></span>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <p><b>Age:</b> <?= $age ?></p>
                        <p><b>Gender:</b> <?= htmlspecialchars($p['gender'] ?? 'Not Set') ?></p>
                        <p><b>Contact:</b> <?= htmlspecialchars($p['contact_number']) ?></p>
                        <p><b>Birthday:</b> <?= date('M d, Y', strtotime($p['birthdate'])) ?></p>
                    </div>
                </div>
                <div style="width: 280px;">
                    <div class="medical-alert">
                        <span style="font-size: 16px;">⚠️</span> MEDICAL ALERT:<br>
                        <p style="font-weight: normal; font-size: 12px; margin: 5px 0 0 0;">
                            <?= !empty($p['medical_history']) ? htmlspecialchars($p['medical_history']) : 'No significant medical history or drug allergies reported.' ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="clinical-grid">
                <div class="appointments-panel">
                    <div class="panel-header-blue">Past Visits</div>
                    <div class="appt-list" style="padding: 20px;">
                        <?php
                        $appt_query = "SELECT a.*, s.service_name FROM appointment a 
                                       JOIN service s ON a.service_id = s.service_id 
                                       WHERE a.patient_id = '$id' ORDER BY a.appointment_date DESC";
                        $appt_res = $conn->query($appt_query);

                        if ($appt_res->num_rows > 0):
                            while($row = $appt_res->fetch_assoc()):
                        ?>
                        <div class="appt-pill">
                            <div style="display: flex; align-items: center;">
                                <div class="indicator"></div>
                                <div>
                                    <div style="font-weight: 700; color: #0d3b66; font-size: 13px;">
                                        <?= date('M d, Y', strtotime($row['appointment_date'])) ?>
                                    </div>
                                    <div style="font-size: 12px; color: #64748b;">
                                        <?= htmlspecialchars($row['service_name']) ?>
                                    </div>
                                </div>
                            </div>
                            <a href="clinical_record.php?id=<?= $id ?>" style="text-decoration:none; font-size: 11px; color: #0d3b66; font-weight: 800;">DETAILS ➔</a>
                        </div>
                        <?php endwhile; else: ?>
                            <p style="text-align: center; color: #94a3b8; font-size: 13px; padding: 20px;">No visit history found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="notes-panel">
                    <div class="panel-header-blue">
                        <span>Clinical SOAP Notes</span>
                        <a href="clinical_record.php?id=<?= $id ?>" class="btn-add-note">+ ADD NEW NOTE</a>
                    </div>
                    <div class="notes-list">
                        <?php
                        // Fetching clinical notes and joining with dentist name
                        $notes_q = "SELECT cn.*, d.last_name as dentist_name FROM clinical_notes cn 
                                   JOIN dentist d ON cn.dentist_id = d.dentist_id
                                   WHERE cn.patient_id = '$id' ORDER BY cn.created_at DESC";
                        $notes_res = $conn->query($notes_q);

                        if ($notes_res && $notes_res->num_rows > 0):
                            while($note = $notes_res->fetch_assoc()):
                        ?>
                        <div class="note-item">
                            <div style="display:flex; justify-content:space-between;">
                                <div class="note-date"><?= date('F d, Y | h:i A', strtotime($note['created_at'])) ?></div>
                                <div style="font-size: 11px; color: #0d3b66; font-weight: bold;">Dr. <?= $note['dentist_name'] ?></div>
                            </div>
                            <div style="margin: 8px 0;">
                                <span style="background: #f0f7ff; color: #0d3b66; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase;">
                                    <?= htmlspecialchars($note['service_rendered'] ?? 'Procedure') ?>
                                </span>
                            </div>
                            <div class="note-text"><?= htmlspecialchars($note['treatment_notes']) ?></div>
                        </div>
                        <?php endwhile; else: ?>
                            <div style="text-align: center; padding: 50px; color: #94a3b8;">
                                <p style="font-size: 14px;">No treatment notes recorded yet.</p>
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
        document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true 
        });
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>

</body>
</html>