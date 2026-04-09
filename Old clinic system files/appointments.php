<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

// 1. SECURITY CHECK (Allow Admin or Staff)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Staff')) {
    header("Location: login.php");
    exit();
}

$todayDate = date('Y-m-d'); 

/* =========================================
   2. HANDLE NEW APPOINTMENT SUBMISSION
========================================= */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patient_name']) && !isset($_POST['update_appointment'])) {
    $full_name   = mysqli_real_escape_string($conn, $_POST['patient_name']);
    $dentist_id  = mysqli_real_escape_string($conn, $_POST['dentist_id']);
    $service_id  = mysqli_real_escape_string($conn, $_POST['service_id']);
    $appt_date   = $_POST['appt_date'];
    $appt_time   = $_POST['appt_time'];
    $notes       = mysqli_real_escape_string($conn, $_POST['notes']);

    // Prevent Past Dates
    if ($appt_date < $todayDate) {
        echo "<script>alert('Error: You cannot schedule an appointment for a past date.'); window.history.back();</script>";
        exit();
    }

    // Find Patient ID
    $p_query = "SELECT patient_id FROM patient WHERE CONCAT(first_name, ' ', last_name) = '$full_name' LIMIT 1";
    $p_res = $conn->query($p_query);

    if ($p_res && $p_res->num_rows > 0) {
        $patient_id = $p_res->fetch_assoc()['patient_id'];
        
        $sql = "INSERT INTO appointment (patient_id, dentist_id, appointment_date, appointment_time, service_id, status, notes) 
                VALUES ('$patient_id', '$dentist_id', '$appt_date', '$appt_time', '$service_id', 'Pending', '$notes')";

        if ($conn->query($sql) === TRUE) {
            echo "<script>alert('Appointment created successfully!'); window.location.href='appointments.php';</script>";
            exit();
        }
    } else {
        echo "<script>alert('Error: Patient not found! Please select from the dropdown.');</script>";
    }
}

/* =========================================
   3. HANDLE STATUS/NOTES UPDATE
========================================= */
if (isset($_POST['update_appointment'])) {
    $id      = $_POST['update_id'];
    $status  = $_POST['new_status'];
    $notes   = mysqli_real_escape_string($conn, $_POST['new_notes']);

    $updateSql = "UPDATE appointment SET status = '$status', notes = '$notes' WHERE appointment_id = '$id'";
    
    if ($conn->query($updateSql) === TRUE) {
        echo "<script>alert('Appointment updated!'); window.location.href='appointments.php';</script>";
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
        .data-table th { background: #0d3b66; color: white; padding: 14px; text-align: left; font-size: 13px; text-transform: uppercase; }
        .data-table td { padding: 14px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        
        .status-pill { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .pending { background: #fef9c3; color: #854d0e; }
        .completed { background: #dcfce7; color: #166534; }
        .cancelled { background: #fee2e2; color: #991b1b; }

        .edit-btn { background: #10b981; color: white; border: none; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .add-btn-main { background: #0d3b66; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; }

        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 15px; width: 450px; position: relative; }
        .close-x { position: absolute; right: 20px; top: 15px; cursor: pointer; font-size: 24px; color: #94a3b8; }
        
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; color: #64748b; font-size: 12px; text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; font-size: 14px; }

        #searchDropdown { display: none; position: absolute; width: 100%; background: white; border: 1px solid #e2e8f0; border-top: none; z-index: 100; max-height: 200px; overflow-y: auto; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-radius: 0 0 8px 8px; }
        .search-item { padding: 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
        .search-item:hover { background: #f8fafc; color: #0d3b66; font-weight: 600; }
        
        .table-filter-input { padding: 10px 20px; border-radius: 25px; border: 1px solid #e2e8f0; width: 300px; }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a href="admin_dashboard.php" class="menu-item"><span>📊</span> Dashboard</a>
                <a href="appointments.php" class="menu-item active"><span>📅</span> Appointment</a>
                <a href="patients.php" class="menu-item"><span>👤</span> Patient</a>
                <a href="dentists.php" class="menu-item"><span>👨‍⚕️</span> Dentist</a>
                <a href="logs.php" class="menu-item"><span>📄</span> Report</a>
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1 style="color: #0d3b66; font-size: 24px; font-weight: 800;">Appointments</h1>
            <div class="header-right">
                <span class="admin-label">Welcome, Admin | 👤</span>
                <div id="liveClock" class="clock">00:00:00 AM</div>
            </div>
        </header>

        <section style="display: flex; justify-content: space-between; align-items: center; padding: 20px 0;">
            <input type="text" id="tableSearch" class="table-filter-input" placeholder="Search by patient, doctor or service..." onkeyup="filterMainTable()">
            <button class="add-btn-main" onclick="toggleAddModal(true)">+ New Appointment</button>
        </section>

        <div class="content-card">
            <table class="data-table" id="apptTable">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Patient Name</th>
                        <th>Dentist Assigned</th>
                        <th>Treatment</th>
                        <th>Date & Time</th>
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
                    $count = 1;
                    if($result && $result->num_rows > 0):
                        while($row = $result->fetch_assoc()): 
                            $fullName = $row['first_name']." ".$row['last_name'];
                    ?>
                    <tr>
                        <td><?= $count++ ?></td>
                        <td><strong><?= htmlspecialchars($fullName) ?></strong></td>
                        <td>Dr. <?= htmlspecialchars($row['d_last']) ?></td>
                        <td><?= htmlspecialchars($row['service_name']) ?></td>
                        <td><?= date('M d, Y', strtotime($row['appointment_date'])) ?> | <?= date('h:i A', strtotime($row['appointment_time'])) ?></td>
                        <td><span class="status-pill <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span></td>
                        <td>
                            <button class="edit-btn" onclick="openEditModal('<?= $row['appointment_id'] ?>', '<?= addslashes($fullName) ?>', '<?= $row['status'] ?>', '<?= addslashes($row['notes']) ?>')">Edit Status</button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7" style="text-align:center; padding: 30px;">No appointments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close-x" onclick="toggleAddModal(false)">&times;</span>
        <h3 style="margin-top:0; color:#0d3b66;">New Appointment</h3>
        <form action="appointments.php" method="POST" id="newAppointmentForm">
            <div class="form-group" style="position: relative;">
                <label>Name of Patient</label>
                <input type="text" name="patient_name" id="modalSearchInput" placeholder="Type patient name..." required autocomplete="off" onkeyup="handleAutocomplete()">
                <div id="searchDropdown"></div>
            </div>

            <div class="form-group">
                <label>Assigned Dentist</label>
                <select name="dentist_id" required>
                    <option value="" disabled selected>Select Dentist</option>
                    <?php
                    $dRes = $conn->query("SELECT dentist_id, first_name, last_name FROM dentist");
                    while($d = $dRes->fetch_assoc()) echo "<option value='".$d['dentist_id']."'>Dr. ".$d['first_name']." ".$d['last_name']."</option>";
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Procedure/Service</label>
                <select name="service_id" required>
                    <?php
                    $sRes = $conn->query("SELECT service_id, service_name FROM service");
                    while($s = $sRes->fetch_assoc()) echo "<option value='".$s['service_id']."'>".$s['service_name']."</option>";
                    ?>
                </select>
            </div>

            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;"><label>Date</label><input type="date" name="appt_date" min="<?= $todayDate ?>" value="<?= $todayDate ?>" required></div>
                <div class="form-group" style="flex:1;"><label>Time</label><input type="time" name="appt_time" required></div>
            </div>

            <div class="form-group"><label>Notes</label><textarea name="notes" rows="2" placeholder="Tooth number, specific concerns..."></textarea></div>
            
            <button type="submit" id="modalSaveBtn" class="add-btn-main" style="width:100%;" disabled>Schedule Appointment</button>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-x" onclick="toggleEditModal(false)">&times;</span>
        <h3 style="margin-top:0; color:#0d3b66;">Update Progress</h3>
        <form action="appointments.php" method="POST">
            <input type="hidden" name="update_id" id="edit_id">
            <div class="form-group"><label>Patient</label><input type="text" id="edit_name_display" disabled style="background:#f8fafc; border:none; font-weight:bold;"></div>
            <div class="form-group">
                <label>Status</label>
                <select name="new_status" id="edit_status">
                    <option value="Pending">Pending</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>
            <div class="form-group"><label>Clinical Notes</label><textarea name="new_notes" id="edit_notes" rows="3"></textarea></div>
            <button type="submit" name="update_appointment" class="add-btn-main" style="width:100%; background:#10b981;">Save Changes</button>
        </form>
    </div>
</div>

<script>
    // Data for Autocomplete
    const patientList = <?php 
        $all_p = $conn->query("SELECT first_name, last_name FROM patient");
        $data = [];
        while($p = $all_p->fetch_assoc()) { $data[] = $p['first_name']." ".$p['last_name']; }
        echo json_encode($data); 
    ?>;

    function handleAutocomplete() {
        const input = document.getElementById('modalSearchInput').value.toLowerCase();
        const dropdown = document.getElementById('searchDropdown');
        const saveBtn = document.getElementById('modalSaveBtn');
        dropdown.innerHTML = ""; 

        if (input.length > 0) {
            const matches = patientList.filter(name => name.toLowerCase().includes(input));
            if (matches.length > 0) {
                dropdown.style.display = "block";
                matches.forEach(name => {
                    const item = document.createElement('div');
                    item.className = "search-item";
                    item.innerText = name;
                    item.onclick = function() {
                        document.getElementById('modalSearchInput').value = name;
                        dropdown.style.display = "none";
                        saveBtn.disabled = false;
                        saveBtn.style.opacity = "1";
                    };
                    dropdown.appendChild(item);
                });
            } else { dropdown.style.display = "none"; saveBtn.disabled = true; saveBtn.style.opacity = "0.5"; }
        } else { dropdown.style.display = "none"; }
    }

    function filterMainTable() {
        const query = document.getElementById('tableSearch').value.toLowerCase();
        const rows = document.querySelectorAll('#apptTable tbody tr');
        rows.forEach(row => { row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none'; });
    }

    function toggleAddModal(show) { document.getElementById("addModal").style.display = show ? "flex" : "none"; }
    function toggleEditModal(show) { document.getElementById("editModal").style.display = show ? "flex" : "none"; }

    function openEditModal(id, name, status, notes) {
        document.getElementById("edit_id").value = id;
        document.getElementById("edit_name_display").value = name;
        document.getElementById("edit_status").value = status;
        document.getElementById("edit_notes").value = notes;
        toggleEditModal(true);
    }

    setInterval(() => {
        document.getElementById('liveClock').textContent = new Date().toLocaleTimeString('en-US', { hour12: true });
    }, 1000);

    window.onclick = function(event) {
        if (event.target.className === 'modal') { toggleAddModal(false); toggleEditModal(false); }
    }
</script>
</body>
</html>