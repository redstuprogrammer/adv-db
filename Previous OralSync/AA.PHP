<?php
session_start();
include "db.php";

// Security Check: Only allow Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Fetch Totals for Statistics Cards
$totalPatients = $conn->query("SELECT COUNT(*) as count FROM patient")->fetch_assoc()['count'];
$totalAppointments = $conn->query("SELECT COUNT(*) as count FROM appointment")->fetch_assoc()['count'];
$totalRevenue = $conn->query("SELECT SUM(amount) as total FROM payment")->fetch_assoc()['total'];
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];

// Fetch Recent Activity Logs for the Admin overview
$recentLogs = $conn->query("SELECT * FROM admin_logs ORDER BY log_id DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Admin Dashboard</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border-left: 5px solid #0d3b66;
            transition: transform 0.3s;
        }

        .stat-card:hover { transform: translateY(-5px); }
        .stat-card h3 { font-size: 14px; color: #666; margin: 0; }
        .stat-card p { font-size: 24px; font-weight: bold; color: #0d3b66; margin: 10px 0 0 0; }

        .dashboard-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .panel {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .panel h2 { font-size: 18px; color: #0d3b66; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }

        .log-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f9f9f9;
            font-size: 13px;
        }

        .log-item span.time { color: #999; font-size: 11px; }

        .btn-action {
            background: #0d3b66;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a href="admin_dashboard.php" class="menu-item active"><span>📊</span> Admin Overview</a>
                <a href="manage_users.php" class="menu-item"><span>👥</span> User Management</a>
                <a href="logs.php" class="menu-item"><span>📄</span> System Reports</a>
                <a href="services.php" class="menu-item"><span>🦷</span> Services & Pricing</a>
                <a href="dentists.php" class="menu-item"><span>👨‍⚕️</span> Manage Staff</a>
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1 style="color: #0d3b66;">Admin Command Center</h1>
            <div class="header-right">
                <span class="admin-label">Welcome, Admin | 👤</span>
            </div>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Patients</h3>
                <p><?php echo $totalPatients; ?></p>
            </div>
            <div class="stat-card" style="border-left-color: #ff6b6b;">
                <h3>Appointments</h3>
                <p><?php echo $totalAppointments; ?></p>
            </div>
            <div class="stat-card" style="border-left-color: #2ecc71;">
                <h3>Total Revenue</h3>
                <p>₱<?php echo number_format($totalRevenue, 2); ?></p>
            </div>
            <div class="stat-card" style="border-left-color: #f1c40f;">
                <h3>System Users</h3>
                <p><?php echo $totalUsers; ?></p>
            </div>
        </div>

        <div class="dashboard-layout">
            <div class="panel">
                <h2>Quick Management</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div style="padding: 20px; border: 1px dashed #ccc; border-radius: 10px; text-align: center;">
                        <p style="font-size: 14px; color: #666;">Need to adjust dental service prices?</p>
                        <a href="services.php" class="btn-action">Update Price List</a>
                    </div>
                    <div style="padding: 20px; border: 1px dashed #ccc; border-radius: 10px; text-align: center;">
                        <p style="font-size: 14px; color: #666;">New staff member joined?</p>
                        <a href="manage_users.php" class="btn-action">Add System User</a>
                    </div>
                </div>
            </div>

            <div class="panel">
                <h2>Recent Activity</h2>
                <?php while($log = $recentLogs->fetch_assoc()): ?>
                    <div class="log-item">
                        <div>
                            <strong><?php echo $log['activity_type']; ?></strong><br>
                            <span style="color: #666;"><?php echo substr($log['action_details'], 0, 30); ?>...</span>
                        </div>
                        <span class="time"><?php echo date('h:i A', strtotime($log['log_time'])); ?></span>
                    </div>
                <?php endwhile; ?>
                <a href="logs.php" style="display: block; text-align: center; margin-top: 15px; font-size: 12px; color: #0d3b66;">View All Logs</a>
            </div>
        </div>
    </main>
</div>

</body>
</html>