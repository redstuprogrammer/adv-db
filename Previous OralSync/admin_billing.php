<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

// 1. SECURITY CHECK (Strict Admin Access)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$currentUserName = $_SESSION['username'] ?? 'Admin';

/* =========================================
   2. DATA FETCHING (Comprehensive Billing List)
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
            a.appointment_id,
            a.appointment_date
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
    <title>OralSync | Financial Audit (Admin)</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        /* UI Elements */
        .content-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-top: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .data-table th { background: #f8fafc; color: #64748b; padding: 14px; text-align: left; font-size: 12px; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        .data-table td { padding: 14px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        
        /* Status Pills */
        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .paid { background: #dcfce7; color: #166534; }
        .installment { background: #fef9c3; color: #854d0e; }

        /* Buttons */
        .btn-action { text-decoration: none; padding: 8px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; border: 1px solid #0d3b66; color: #0d3b66; background: #f8fafc; }
        .btn-action:hover { background: #0d3b66; color: #fff; }

        .search-bar { padding: 12px 20px; border: 1px solid #e2e8f0; border-radius: 25px; width: 350px; outline: none; }
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
                <a href="admin_patients.php" class="menu-item"><span>👤</span> Patients</a>
                <a href="admin_appointments.php" class="menu-item"><span>📅</span> Appointments</a>
                <a href="admin_billing.php" class="menu-item active"><span>💰</span> Billing</a>
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="header-left">
                <h1 style="color: #0d3b66; margin:0;">Transaction Audit</h1>
                <p style="color: #64748b;">Review clinic revenue and payment history</p>
            </div>
            <div id="liveClock" class="clock" style="font-weight:bold; color:#0d3b66;">00:00:00 AM</div>
        </header>

        <section style="padding: 20px 0;">
            <input type="text" id="tableSearch" placeholder="🔍 Search patient, invoice, or status..." onkeyup="filterMainTable()" class="search-bar">
        </section>

        <div class="content-card">
            <table class="data-table" id="paymentTable">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Patient Name</th>
                        <th>Treatment</th>
                        <th>Amount</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td style="font-family: monospace; font-weight: bold; color: #64748b;">#<?= str_pad($row['payment_id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td><strong><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']) ?></strong></td>
                            <td>
                                <div style="font-weight: 500;"><?= htmlspecialchars($row['service_name']) ?></div>
                                <div style="font-size: 11px; color: #94a3b8;"><?= date('M d, Y', strtotime($row['appointment_date'])) ?></div>
                            </td>
                            <td style="font-weight:700; color: #0d3b66;">₱<?= number_format($row['amount'], 2) ?></td>
                            <td style="font-size: 13px;"><?= $row['mode'] ?></td>
                            <td><span class="status-pill <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span></td>
                            <td style="text-align: right;">
                                <a href="print_invoice.php?id=<?= $row['payment_id'] ?>" class="btn-action" target="_blank">View PDF</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding: 40px; color: #94a3b8;">No financial records found in the database.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
    function updateClock() {
        document.getElementById('liveClock').textContent = new Date().toLocaleTimeString('en-US', { hour12: true });
    }
    setInterval(updateClock, 1000); updateClock();

    function filterMainTable() {
        let q = document.getElementById('tableSearch').value.toLowerCase();
        let rows = document.querySelectorAll('#paymentTable tbody tr');
        rows.forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    }
</script>
</body>
</html>