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

// Fetch Recent Activity Logs
$recentLogs = $conn->query("SELECT * FROM admin_logs ORDER BY log_id DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Admin Command Center</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        :root {
            --primary: #0d3b66;
            --bg-light: #f8fafc;
            --border: #e2e8f0;
            --text-main: #334155;
            --text-muted: #64748b;
        }

        body { background-color: var(--bg-light); color: var(--text-main); }

        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: #fff;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .stat-card:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 10px 20px rgba(0,0,0,0.05); 
        }

        .stat-card h3 { 
            font-size: 0.85rem; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            color: var(--text-muted); 
            margin: 0 0 8px 0; 
        }

        .stat-card p { 
            font-size: 1.75rem; 
            font-weight: 800; 
            color: var(--primary); 
            margin: 0; 
        }

        /* Dashboard Layout */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 24px;
            align-items: start;
        }

        .panel {
            background: #fff;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid var(--border);
        }

        .panel h2 { 
            font-size: 1.1rem; 
            color: var(--primary); 
            margin-bottom: 20px; 
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Activity Items */
        .log-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .log-item:last-child { border-bottom: none; }

        .log-info strong { font-size: 0.9rem; color: var(--primary); }
        .log-info span { font-size: 0.8rem; color: var(--text-muted); display: block; }
        .log-time { font-size: 0.75rem; color: #94a3b8; font-weight: 500; }

        /* Quick Management Box */
        .management-box {
            background: #fdfdfd;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 40px 20px;
            text-align: center;
        }

        .btn-action {
            background: var(--primary);
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 700;
            display: inline-block;
            margin-top: 15px;
            transition: opacity 0.2s;
        }

        .btn-action:hover { opacity: 0.9; }

        /* Header UI adjustment */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            margin-bottom: 20px;
        }

    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a href="admin_dashboard.php" class="menu-item active"><span>📊</span> Overview</a>
                <a href="manage_users.php" class="menu-item"><span>👥</span> Users</a>
                <a href="logs.php" class="menu-item"><span>📄</span> Reports</a>
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
            <h1 style="color: var(--primary); font-size: 1.5rem; font-weight: 800;">Admin Command Center</h1>
            <div class="header-right" style="display: flex; align-items: center; gap: 10px;">
                <span style="color: var(--text-muted); font-size: 0.9rem;">Welcome, <strong>Admin</strong></span>
                <div style="width: 35px; height: 35px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center;">👤</div>
            </div>
        </header>

        <div class="stats-grid">
            <div class="stat-card" style="border-top: 4px solid var(--primary);">
                <h3>Total Patients</h3>
                <p><?php echo number_format($totalPatients); ?></p>
            </div>
            <div class="stat-card" style="border-top: 4px solid #f43f5e;">
                <h3>Appointments</h3>
                <p><?php echo number_format($totalAppointments); ?></p>
            </div>
            <div class="stat-card" style="border-top: 4px solid #10b981;">
                <h3>Total Revenue</h3>
                <p>₱<?php echo number_format($totalRevenue, 2); ?></p>
            </div>
            <div class="stat-card" style="border-top: 4px solid #f59e0b;">
                <h3>System Users</h3>
                <p><?php echo $totalUsers; ?></p>
            </div>
        </div>

        <div class="dashboard-layout">
            <div class="panel">
                <h2><span>⚡</span> Quick Management</h2>
                <div class="management-box">
                    <div style="font-size: 3rem; margin-bottom: 15px;">🛡️</div>
                    <h3 style="color: var(--primary); margin-bottom: 8px;">Access Control</h3>
                    <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.5;">
                        Update system permissions, authorize new staff members, or manage user roles.
                    </p>
                    <a href="manage_users.php" class="btn-action">User Management</a>
                </div>
            </div>

            <div class="panel">
                <h2><span>🕒</span> Recent Activity</h2>
                <div class="log-list">
                    <?php if($recentLogs->num_rows > 0): ?>
                        <?php while($log = $recentLogs->fetch_assoc()): ?>
                            <div class="log-item">
                                <div class="log-info">
                                    <strong><?php echo htmlspecialchars($log['activity_type']); ?></strong>
                                    <span><?php echo htmlspecialchars(substr($log['action_details'], 0, 30)); ?>...</span>
                                </div>
                                <div class="log-time"><?php echo date('h:i A', strtotime($log['log_time'])); ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                            <p style="font-size: 0.9rem;">No recent activities found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <a href="logs.php" style="display: block; text-align: center; margin-top: 20px; font-size: 0.75rem; color: var(--primary); font-weight: 700; text-decoration: none; text-transform: uppercase;">View Full Audit Log</a>
            </div>
        </div>
    </main>
</div>

</body>
</html>