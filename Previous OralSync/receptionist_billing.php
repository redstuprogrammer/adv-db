<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

// 1. SECURITY CHECK (Adjust role names as per your database)
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

/* =========================================
   2. DATA FETCHING (Billing List)
========================================= */
$query = "SELECT 
            py.payment_id, 
            p.patient_id,
            p.first_name, 
            p.last_name, 
            COALESCE(s.service_name, py.service) AS service_name, 
            py.amount, 
            py.mode, 
            py.status,
            a.appointment_id
          FROM payment py
          LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
          LEFT JOIN patient p ON a.patient_id = p.patient_id
          LEFT JOIN service s ON a.service_id = s.service_id
          ORDER BY py.payment_id DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Billing Management</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        /* UI Elements */
        .content-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-top: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .data-table th { background: #0d3b66; color: white; padding: 12px; text-align: left; font-size: 13px; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        
        /* Status Pills */
        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .paid { background: #dcfce7; color: #166534; }
        .installment { background: #fef9c3; color: #854d0e; }

        /* Buttons */
        .btn-action { text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; border: 1px solid transparent; }
        .btn-print { background: #f8fafc; color: #0d3b66; border-color: #0d3b66; }
        .btn-print:hover { background: #0d3b66; color: #fff; }
        .btn-edit { background: #ecfdf5; color: #059669; border-color: #059669; margin-left: 5px; }
        .btn-edit:hover { background: #059669; color: #fff; }
        .add-btn-main { background: #0d3b66; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; }

        /* Modal Logic */
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal-content { background: white; padding: 30px; border-radius: 15px; width: 450px; position: relative; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .close-x { position: absolute; right: 20px; top: 15px; cursor: pointer; font-size: 24px; color: #64748b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #0d3b66; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }
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
                <a href="patients.php" class="menu-item"><span>👤</span> Patient Records</a>
                <a href="receptionist_billing.php" class="menu-item active"><span>💳</span> Billing/Payments</a>
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="header-left">
                <h1 style="color: #0d3b66; margin:0;">Billing & Payments</h1>
                <p style="color: #64748b;">Manage invoices and transaction records</p>
            </div>
            <div id="liveClock" class="clock" style="font-weight:bold; color:#0d3b66;">00:00:00 AM</div>
        </header>

        <section style="display: flex; justify-content: space-between; align-items: center; padding: 20px 0;">
            <input type="text" id="tableSearch" placeholder="Search patient or service..." onkeyup="filterMainTable()" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px; width: 300px;">
            <button class="add-btn-main" onclick="toggleAddModal(true)">+ Create Invoice</button>
        </section>

        <?php if(isset($_GET['msg'])): ?>
            <div style="background:#dcfce7; color:#15803d; padding:15px; border-radius:8px; margin-bottom:20px; font-weight:600;">
                ✅ Action processed successfully!
            </div>
        <?php endif; ?>

        <div class="content-card">
            <table class="data-table" id="paymentTable">
                <thead>
                    <tr>
                        <th>Inv #</th>
                        <th>Patient Name</th>
                        <th>Service</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= str_pad($row['payment_id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td><strong><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['service_name']) ?></td>
                            <td style="font-weight:bold;">₱<?= number_format($row['amount'], 2) ?></td>
                            <td><span class="status-pill <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span></td>
                            <td>
                                <a href="print_invoice.php?id=<?= $row['payment_id'] ?>" class="btn-action btn-print" target="_blank">Print</a>
                                <button class="btn-action btn-edit" onclick='openEditModal(<?= json_encode($row) ?>)'>Edit</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="close-x" onclick="toggleAddModal(false)">&times;</span>
        <h3 id="modalTitle" style="color: #0d3b66; margin:0 0 20px 0;">Create Invoice</h3>
        
        <form action="process_payment.php" method="POST" id="paymentForm">
            <input type="hidden" name="payment_id" id="payment_id">
            
            <div class="form-group">
                <label>Patient</label>
                <select name="patient_id" id="patient_dropdown" onchange="loadPatientServices(this.value)" required>
                    <option value="">-- Select Patient --</option>
                    <?php 
                    $patients = $conn->query("SELECT patient_id, first_name, last_name FROM patient ORDER BY last_name ASC");
                    while($p = $patients->fetch_assoc()) {
                        echo "<option value='".$p['patient_id']."'>".$p['first_name']." ".$p['last_name']."</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Related Appointment</label>
                <select name="appointment_id" id="service_dropdown" required>
                    <option value="">-- Choose Patient First --</option>
                </select>
            </div>

            <div class="form-group">
                <label>Total Amount (₱)</label>
                <input type="number" name="amount" id="amount_input" step="0.01" required>
            </div>

            <div class="form-group">
                <label>Payment Mode</label>
                <select name="mode" id="mode">
                    <option value="Cash">Cash</option>
                    <option value="GCash">GCash</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" id="status">
                    <option value="Paid">Fully Paid</option>
                    <option value="Installment">Installment</option>
                </select>
            </div>

            <button type="submit" class="add-btn-main" style="width:100%; margin-top:10px;">Save Transaction</button>
        </form>
    </div>
</div>

<script>
    // 1. Live Clock
    function updateClock() {
        document.getElementById('liveClock').textContent = new Date().toLocaleTimeString('en-US', { hour12: true });
    }
    setInterval(updateClock, 1000); updateClock();

    // 2. Modal Toggle
    function toggleAddModal(show) {
        const modal = document.getElementById("paymentModal");
        if(show) {
            document.getElementById('paymentForm').reset();
            document.getElementById('payment_id').value = "";
            document.getElementById('modalTitle').innerText = "Create New Invoice";
            modal.style.display = "flex";
        } else {
            modal.style.display = "none";
        }
    }

    // 3. Edit Modal Trigger
    function openEditModal(data) {
        document.getElementById('modalTitle').innerText = "Edit Invoice #" + data.payment_id;
        document.getElementById('payment_id').value = data.payment_id;
        document.getElementById('patient_dropdown').value = data.patient_id;
        document.getElementById('amount_input').value = data.amount;
        document.getElementById('mode').value = data.mode;
        document.getElementById('status').value = data.status;
        
        // Load services and pre-select the current one
        loadPatientServices(data.patient_id, data.appointment_id);
        document.getElementById("paymentModal").style.display = "flex";
    }

    // 4. Dynamic Service Loading
    function loadPatientServices(patientId, selectedApptId = null) {
        const serviceSelect = document.getElementById('service_dropdown');
        if (!patientId) return;

        fetch('get_patient_services.php?patient_id=' + patientId)
            .then(res => res.json())
            .then(data => {
                serviceSelect.innerHTML = '<option value="">-- Select Appointment --</option>';
                data.forEach(item => {
                    let opt = document.createElement('option');
                    opt.value = item.appointment_id;
                    opt.textContent = item.service_name + " (" + item.appointment_date + ")";
                    if(selectedApptId && item.appointment_id == selectedApptId) opt.selected = true;
                    serviceSelect.appendChild(opt);
                });
            });
    }

    // 5. Search Filter
    function filterMainTable() {
        let q = document.getElementById('tableSearch').value.toLowerCase();
        let rows = document.querySelectorAll('#paymentTable tbody tr');
        rows.forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    }

    // Close modal if clicking outside
    window.onclick = function(e) {
        if (e.target.className === 'modal') toggleAddModal(false);
    }
</script>
</body>
</html>