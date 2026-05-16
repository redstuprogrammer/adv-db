<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

// 0. SECURITY CHECK: Only allow Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Check for date filters
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';

// 1. Fetch Activity Logs - Capturing every system movement
$logQuery = "SELECT * FROM admin_logs ORDER BY log_id DESC";
$logResult = $conn->query($logQuery);

// 2. Fetch Revenue Report (Fixed Query)
$revCondition = "";
if (!empty($start_date) && !empty($end_date)) {
    // We use COALESCE to handle cases where appointment date might be missing
    $revCondition = " WHERE a.appointment_date BETWEEN '$start_date' AND '$end_date' ";
}

$revQuery = "SELECT 
                p.first_name, 
                p.last_name, 
                py.service, 
                py.amount, 
                a.appointment_date as payment_date, 
                py.status
             FROM payment py
             LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
             LEFT JOIN patient p ON a.patient_id = p.patient_id
             $revCondition
             ORDER BY a.appointment_date ASC"; // ASC is better for Graphing
$revResult = $conn->query($revQuery);

// Data preparation for the Chart
$chartLabels = [];
$chartData = [];
$totalRevenue = 0;

$tempResult = $conn->query($revQuery);
while($row = $tempResult->fetch_assoc()) {
    $dateLabel = (!empty($row['payment_date'])) ? date('M d', strtotime($row['payment_date'])) : 'No Date';
    $chartLabels[] = $dateLabel;
    $chartData[] = $row['amount'];
    $totalRevenue += $row['amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Analytics & Audit Trail</title>
    <link rel="stylesheet" href="style1.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        .panel-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 30px; margin-top: 20px; }
        .tab-container { display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .tab-btn { padding: 10px 20px; border: none; background: #f0f0f0; border-radius: 8px; cursor: pointer; font-weight: 600; color: #666; transition: 0.3s; }
        .tab-btn.active { background: #0d3b66; color: #fff; }
        .filter-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px; gap: 10px; border: 1px solid #eee; }
        .report-section { display: none; }
        .report-section.active { display: block; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th { background: #0d3b66; color: #fff; padding: 12px; text-align: left; font-size: 14px; }
        .data-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; color: #333; }
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-created { background: #e3f2fd; color: #0d47a1; }
        .badge-updated { background: #fff3e0; color: #e65100; }
        .badge-deleted { background: #ffebee; color: #c62828; }
        .revenue-total { background: #f4faff; padding: 20px; border-radius: 8px; text-align: right; border-left: 5px solid #0d3b66; margin-top: 20px; }
        .chart-container { position: relative; height: 300px; width: 100%; margin-bottom: 30px; }
        .btn-export { background: #2e7d32; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: 600; }
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
                <a href="logs.php" class="menu-item active"><span>📄</span> Reports</a>
                <a href="services.php" class="menu-item"><span>🦷</span> Services</a>
                <a href="staff.php" class="menu-item"><span>👨‍⚕️</span> Staff</a>
                <a href="admin_patients.php" class="menu-item"><span>👤</span> Patients</a>
                 <a href="admin_appointments.php" class="menu-item"><span>📅</span> Appointments</a>
                 <a href="admin_billing.php" class="menu-item"><span>💰</span> Billing</a>
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div class="header-left">
                <h1 style="color: #0d3b66; font-size: 24px; font-weight: 800; margin: 0;">Clinic Analytics</h1>
                <p style="color: #64748b; font-size: 13px;">Real-time revenue tracking and system logs</p>
            </div>
            <div class="header-right">
                <div id="liveClock" style="font-weight: 700; color: #0d3b66; font-size: 14px;"></div>
            </div>
        </header>

        <div class="panel-card">
            <div class="tab-container">
                <button class="tab-btn active" onclick="switchTab('logs', this)">Activity Audit Trail</button>
                <button class="tab-btn" onclick="switchTab('revenue', this)">Revenue Performance</button>
            </div>

            <div id="logs" class="report-section active">
                <div class="filter-bar">
                    <select id="logTypeFilter" onchange="filterLogs()" style="padding: 8px; border-radius: 5px;">
                        <option value="all">All Movements</option>
                        <option value="created">Created</option>
                        <option value="updated">Updated</option>
                        <option value="deleted">Deleted</option>
                    </select>
                    <input type="text" id="logSearch" placeholder="Search logs..." onkeyup="filterLogs()" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
                </div>
                <table class="data-table" id="logTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Time</th>
                            <th>Date</th>
                            <th>Activity</th>
                            <th>Movement Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $logResult->fetch_assoc()): 
                            $act = strtolower($row['activity_type']);
                            $typeAttr = (strpos($act, 'create') !== false || strpos($act, 'reg') !== false) ? 'created' : 
                                        ((strpos($act, 'delete') !== false) ? 'deleted' : 'updated');
                        ?>
                        <tr data-type="<?= $typeAttr ?>">
                            <td>#<?= $row['log_id'] ?></td>
                            <td><?= date('h:i A', strtotime($row['log_time'])) ?></td>
                            <td><?= $row['log_date'] ?></td>
                            <td><span class="badge badge-<?= $typeAttr ?>"><?= $row['activity_type'] ?></span></td>
                            <td><?= htmlspecialchars($row['action_details']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div id="revenue" class="report-section">
                <div class="filter-bar">
                    <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                        <input type="date" name="start_date" value="<?= $start_date ?>">
                        <span>to</span>
                        <input type="date" name="end_date" value="<?= $end_date ?>">
                        <button type="submit" class="tab-btn active">Filter Range</button>
                    </form>
                    <button onclick="exportToPDF()" class="btn-export">📥 Export Report</button>
                </div>

                <div class="chart-container">
                    <canvas id="revGraph"></canvas>
                </div>

                <div id="print-area">
                    <h3 style="color: #0d3b66; margin-bottom: 10px;">Transaction Details</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Patient Name</th>
                                <th>Service Rendered</th>
                                <th>Amount Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $revResult->data_seek(0);
                            while($rev = $revResult->fetch_assoc()): ?>
                            <tr>
                                <td><?= $rev['payment_date'] ?? 'N/A' ?></td>
                                <td><strong><?= htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?></strong></td>
                                <td><?= htmlspecialchars($rev['service']) ?></td>
                                <td style="font-weight: bold; color: #2e7d32;">₱<?= number_format($rev['amount'], 2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <div class="revenue-total">
                        <span style="color: #666;">Total Period Revenue:</span><br>
                        <strong style="font-size: 28px; color: #0d3b66;">₱<?= number_format($totalRevenue, 2) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>



<script>
function switchTab(tabId, btn) {
    document.querySelectorAll('.report-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
}

// Chart.js Graph
const ctx = document.getElementById('revGraph').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Collection (₱)',
            data: <?= json_encode($chartData) ?>,
            borderColor: '#0d3b66',
            backgroundColor: 'rgba(13, 59, 102, 0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});

function exportToPDF() {
    const element = document.getElementById('print-area');
    html2pdf().from(element).save('OralSync_Revenue_Report.pdf');
}

function filterLogs() {
    const type = document.getElementById('logTypeFilter').value;
    const query = document.getElementById('logSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#logTable tbody tr');
    rows.forEach(row => {
        const rowType = row.getAttribute('data-type');
        const text = row.innerText.toLowerCase();
        row.style.display = (type === 'all' || rowType === type) && text.includes(query) ? '' : 'none';
    });
}

setInterval(() => {
    document.getElementById('liveClock').textContent = new Date().toLocaleTimeString();
}, 1000);
</script>
</body>
</html>