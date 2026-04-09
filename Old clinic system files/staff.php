<?php
session_start();
include "db.php";

// 1. SECURITY CHECK: Only Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// 2. FETCH STAFF DATA
// Assuming your 'users' table or a separate 'staff' table holds these clinical details
$query = "SELECT * FROM users WHERE role IN ('Dentist', 'Receptionist') ORDER BY username ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Staff Directory</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        :root {
            --primary: #0d3b66;
            --bg-light: #f8fafc;
            --border: #e2e8f0;
            --text-main: #334155;
            --text-muted: #64748b;
        }

        body { background-color: var(--bg-light); color: var(--text-main); font-family: 'Segoe UI', sans-serif; }

        /* HEADER ALIGNMENT (Matching your image) */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            margin-bottom: 10px;
        }

        .header-title { color: var(--primary); font-size: 24px; font-weight: 800; margin: 0; }

        .header-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-muted);
            font-size: 14px;
        }

        .profile-icon-circle {
            width: 35px; height: 35px; background: #e2e8f0; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 16px;
        }

        /* GRID LAYOUT FOR STAFF CARDS */
        .staff-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .staff-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 25px;
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .staff-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }

        .staff-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .staff-avatar {
            width: 60px; height: 60px; background: #f1f5f9; color: var(--primary);
            border-radius: 50%; display: flex; align-items: center; 
            justify-content: center; font-weight: 800; font-size: 20px;
            border: 2px solid var(--border);
        }

        .staff-info h3 { margin: 0; font-size: 18px; color: var(--primary); }
        .staff-info p { margin: 2px 0 0; font-size: 13px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }

        .staff-details {
            border-top: 1px solid #f1f5f9;
            padding-top: 15px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .detail-item span { color: var(--text-muted); font-size: 16px; }

        .btn-view {
            display: block;
            text-align: center;
            background: #f8fafc;
            color: var(--primary);
            text-decoration: none;
            padding: 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 15px;
            border: 1px solid var(--border);
            transition: 0.2s;
        }

        .btn-view:hover { background: var(--primary); color: #fff; }
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
                <a href="dentists.php" class="menu-item active"><span>👨‍⚕️</span> Staff</a>
                <a href="admin_patients.php" class="menu-item"><span>👤</span> Patients</a>
                 <a href="admin_appointments.php" class="menu-item"><span>📅</span> Appointments</a>
                 <a href="admin_billing.php" class="menu-item"><span>💰</span> Billing</a>
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1 class="header-title">Staff Directory</h1>
            <div class="header-profile">
                <span>Welcome, <strong>Admin</strong> | </span>
                <div class="profile-icon-circle">👤</div>
            </div>
        </header>

        <div class="staff-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while($staff = $result->fetch_assoc()): ?>
                    <div class="staff-card">
                        <div class="staff-header">
                            <div class="staff-avatar"><?php echo substr($staff['username'], 0, 1); ?></div>
                            <div class="staff-info">
                                <h3>Dr. <?php echo htmlspecialchars($staff['username']); ?></h3>
                                <p><?php echo htmlspecialchars($staff['role']); ?></p>
                            </div>
                        </div>
                        <div class="staff-details">
                            <div class="detail-item">
                                <span>📧</span> <?php echo htmlspecialchars($staff['email']); ?>
                            </div>
                            <div class="detail-item">
                                <span>📅</span> Active since <?php echo date('M Y', strtotime($staff['date_created'])); ?>
                            </div>
                        </div>
                        <a href="view_staff_profile.php?id=<?php echo $staff['user_id']; ?>" class="btn-view">VIEW FULL PROFILE</a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="grid-column: 1/-1; text-align: center; padding: 50px; color: var(--text-muted);">No staff members found.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>