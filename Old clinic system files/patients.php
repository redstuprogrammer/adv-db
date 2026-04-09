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

// FETCH PATIENTS & LATEST VISITS
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
    <title>OralSync | Patient Records</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        /* Table Styling */
        .queue-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
        .queue-table th { background: #f8fafc; color: #64748b; padding: 15px; text-align: left; font-size: 12px; text-transform: uppercase; }
        .queue-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .clickable-row { cursor: pointer; transition: background 0.2s; }
        .clickable-row:hover { background-color: #f4faff !important; }

        /* Status Pills */
        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-pill.active { background: #dcfce7; color: #166534; }
        .status-pill.inactive { background: #f1f5f9; color: #64748b; }

        /* Refined Modal Layout */
        .modal-overlay { 
            display: none; position: fixed; z-index: 9999; 
            left: 0; top: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.6); align-items: center; justify-content: center; 
        }
        .modal-container { 
            background: white; width: 600px; border-radius: 12px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.3); overflow: hidden;
            animation: slideUp 0.3s ease-out;
        }
        .modal-header-accent { background: #0d3b66; height: 8px; width: 100%; }
        .modal-body { padding: 25px 35px; }
        .modal-body h3 { text-align: center; color: #0d3b66; margin-bottom: 5px; }
        
        /* Form Grid */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #333; }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; outline: none; box-sizing: border-box;
        }

        .modal-footer { padding: 0 35px 30px; display: flex; justify-content: flex-end; gap: 10px; }
        .btn-cancel { background: #eee; color: #333; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        .btn-create { background: #0d3b66; color: white; border: none; padding: 10px 30px; border-radius: 5px; cursor: pointer; font-weight: bold; }

        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a href="receptionist_dashboard.php" class="menu-item"><span>🏠</span> Front Desk</a>
                <a href="receptionist_appoinment.php" class="menu-item"><span>📅</span> Schedule Manager</a>
                <a href="patients.php" class="menu-item active"><span>👤</span> Patient Records</a>
                <a href="receptionist_billing.php" class="menu-item"><span>💳</span> Billing/Payments</a>
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="header-left">
                <h1 style="color: #0d3b66; margin:0;">Patient Directory</h1>
                <p style="color: #64748b;">Managing records for OralSync Dental Clinic</p>
            </div>
            <div id="liveClock" class="clock">00:00:00 AM</div>
        </header>

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'registered'): ?>
            <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
                ✅ New patient registered successfully!
            </div>
        <?php endif; ?>

        <div class="pop-card">
            <div class="card-header">
                <span>All Registered Patients</span>
                <button class="add-btn" onclick="showModal()" style="border:none; cursor:pointer;">+</button>
            </div>
            
            <div style="padding: 15px 20px; background: #fff; border-bottom: 1px solid #f1f5f9;">
                <input type="text" id="pSearch" onkeyup="searchTable()" placeholder="Search by name or contact..." 
                       style="width: 320px; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; outline: none;">
            </div>

            <div style="padding: 10px;">
                <table class="queue-table" id="pTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient Name</th>
                            <th>Contact</th>
                            <th>Last Visit</th>
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
                        <tr class="clickable-row" onclick="window.location='patient_view.php?id=<?= $row['patient_id'] ?>'">
                            <td style="color: #64748b;">#<?= str_pad($row['patient_id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td><strong><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['contact_number']) ?></td>
                            <td><?= $row['last_visit'] ? date('M d, Y', strtotime($row['last_visit'])) : '<span style="color:#999;">No visits yet</span>' ?></td>
                            <td>
                                <span class="status-pill <?= $isActive ? 'active' : 'inactive' ?>">
                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px;">No patient records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<div class="modal-overlay" id="patientModal">
    <div class="modal-container">
        <div class="modal-header-accent"></div>
        <div class="modal-body">
            <h3>Register New Patient</h3>
            <form id="patientForm" action="process_patient.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="tel" name="contact" placeholder="09xxxxxxxxx" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label>Birthdate</label>
                        <input type="date" name="dob" required>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Home Address</label>
                        <input type="text" name="address" placeholder="Barangay, City/Municipality">
                    </div>
                    <div class="form-group full-width">
                        <label>Initial Medical Note</label>
                        <textarea name="note" rows="2" placeholder="Allergies, existing conditions, or reason for visit..."></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="hideModal()">Discard</button>
            <button type="submit" form="patientForm" class="btn-create">Save Patient</button>
        </div>
    </div>
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

    function showModal() { document.getElementById('patientModal').style.display = 'flex'; }
    function hideModal() { document.getElementById('patientModal').style.display = 'none'; }
    window.onclick = function(e) { if (e.target.className == 'modal-overlay') hideModal(); }
</script>
</body>
</html>