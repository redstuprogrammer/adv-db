<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

// 1. SECURITY CHECK (Management Oversight)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Staff')) {
    header("Location: login.php");
    exit();
}

$isAdmin = ($_SESSION['role'] === 'Admin');

/* =========================================
    2. HANDLE STATUS/NOTES UPDATE ONLY
========================================= */
if (isset($_POST['update_appointment'])) {
    $id      = $_POST['update_id'];
    $status  = $_POST['new_status'];
    $notes   = mysqli_real_escape_string($conn, $_POST['new_notes']);

    $updateSql = "UPDATE appointment SET status = '$status', notes = '$notes' WHERE appointment_id = '$id'";
    
    if ($conn->query($updateSql) === TRUE) {
        // LOG ACTIVITY for Audit Trail
        $logNote = "Admin updated appointment #$id to $status.";
        $conn->query("INSERT INTO admin_logs (activity_type, action_details) VALUES ('Appointment Update', '$logNote')");
        
        echo "<script>alert('Record updated successfully!'); window.location.href='appointments.php';</script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Appointment Management</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        .content-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-top: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .data-table th { background: #f8fafc; color: #64748b; padding: 14px; text-align: left; font-size: 12px; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        .data-table td { padding: 14px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        
        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .pending { background: #fffbeb; color: #92400e; }
        .completed { background: #f0fdf4; color: #166534; }
        .cancelled { background: #fef2f2; color: #991b1b; }

        .edit-btn { background: #0d3b66; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .table-filter-input { padding: 12px 20px; border-radius: 25px; border: 1px solid #e2e8f0; width: 100%; max-width: 400px; }

        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 15px; width: 400px; position: relative; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; color: #64748b; font-size: 11px; text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }
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
                <a href="admin_patients.php" class="menu-item "><span>👤</span> Patients</a>
                <a href="admin_appointments.php" class="menu-item active"><span>📅</span> Appointments</a>
                <a href="admin_billing.php" class="menu-item"><span>💰</span> Billing</a>
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="header-left">
                <h1 style="color: #0d3b66; margin:0;">Schedule Monitor</h1>
                <p style="color: #64748b;">Review and update clinical appointment statuses</p>
            </div>
            <div id="liveClock" class="clock">00:00:00 AM</div>
        </header>

        <section style="padding: 20px 0;">
            <input type="text" id="tableSearch" class="table-filter-input" placeholder="🔍 Search by patient, dentist, or treatment..." onkeyup="filterTable()">
        </section>

        <div class="content-card">
            <table class="data-table" id="apptTable">
                <thead>
                    <tr>
                        <th>Schedule</th>
                        <th>Patient</th>
                        <th>Dentist</th>
                        <th>Treatment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $fetch = "SELECT a.appointment_id, p.first_name, p.last_name, s.service_name, 
                                     d.last_name AS d_last, a.appointment_date, a.appointment_time, a.status, a.notes 
                              FROM appointment a
                              JOIN patient p ON a.patient_id = p.patient_id
                              JOIN service s ON a.service_id = s.service_id
                              JOIN dentist d ON a.dentist_id = d.dentist_id
                              ORDER BY a.appointment_date DESC";
                    $result = $conn->query($fetch);
                    if($result && $result->num_rows > 0):
                        while($row = $result->fetch_assoc()): 
                            $fullName = $row['first_name']." ".$row['last_name'];
                    ?>
                    <tr>
                        <td>
                            <strong><?= date('M d, Y', strtotime($row['appointment_date'])) ?></strong>
                            <div style="font-size: 12px; color: #94a3b8;"><?= date('h:i A', strtotime($row['appointment_time'])) ?></div>
                        </td>
                        <td><?= htmlspecialchars($fullName) ?></td>
                        <td>Dr. <?= htmlspecialchars($row['d_last']) ?></td>
                        <td><?= htmlspecialchars($row['service_name']) ?></td>
                        <td><span class="status-pill <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span></td>
                        <td>
                            <button class="edit-btn" onclick="openEditModal('<?= $row['appointment_id'] ?>', '<?= addslashes($fullName) ?>', '<?= $row['status'] ?>', '<?= addslashes($row['notes']) ?>')">Manage</button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" style="text-align:center; padding: 40px; color:#94a3b8;">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-top:0; color:#0d3b66;">Update Treatment Status</h3>
        <form action="appointments.php" method="POST">
            <input type="hidden" name="update_id" id="edit_id">
            <div class="form-group">
                <label>Patient</label>
                <input type="text" id="edit_name_display" disabled style="background:#f1f5f9; border:none; font-weight:bold; color:#475569;">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="new_status" id="edit_status">
                    <option value="Pending">Pending</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>
            <div class="form-group">
                <label>Clinical Notes</label>
                <textarea name="new_notes" id="edit_notes" rows="4" placeholder="Enter findings or procedure notes..."></textarea>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" onclick="closeModal()" style="flex:1; padding:12px; border-radius:8px; border:1px solid #cbd5e1; cursor:pointer;">Cancel</button>
                <button type="submit" name="update_appointment" style="flex:2; padding:12px; border-radius:8px; background:#0d3b66; color:white; border:none; cursor:pointer; font-weight:bold;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function filterTable() {
        const query = document.getElementById('tableSearch').value.toLowerCase();
        const rows = document.querySelectorAll('#apptTable tbody tr');
        rows.forEach(row => { row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none'; });
    }

    function openEditModal(id, name, status, notes) {
        document.getElementById("edit_id").value = id;
        document.getElementById("edit_name_display").value = name;
        document.getElementById("edit_status").value = status;
        document.getElementById("edit_notes").value = notes;
        document.getElementById("editModal").style.display = "flex";
    }

    function closeModal() { document.getElementById("editModal").style.display = "none"; }

    setInterval(() => {
        document.getElementById('liveClock').textContent = new Date().toLocaleTimeString('en-US', { hour12: true });
    }, 1000);

    window.onclick = function(e) { if (e.target.className === 'modal') closeModal(); }
</script>
</body>
</html>