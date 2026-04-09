<?php
session_start();
include "db.php";

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// 2. FETCH INTEGRATED DATA (Users + Staff Details)
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Using a JOIN to get authentication info and clinical info in one go
    $sql = "SELECT u.*, s.specialization, s.license_number, s.phone_number, s.schedule_days, s.bio 
            FROM users u 
            LEFT JOIN staff_details s ON u.user_id = s.user_id 
            WHERE u.user_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = $result->fetch_assoc();

    if (!$staff) {
        die("Staff member not found.");
    }
} else {
    header("Location: dentists.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | <?php echo htmlspecialchars($staff['username']); ?> Profile</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        :root {
            --primary: #0d3b66;
            --bg-light: #f8fafc;
            --border: #e2e8f0;
            --text-main: #334155;
            --text-muted: #64748b;
            --accent: #2ecc71;
        }

        body { background-color: var(--bg-light); color: var(--text-main); font-family: 'Segoe UI', sans-serif; }

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

        .profile-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 25px;
            margin-top: 20px;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        /* Sidebar Info */
        .profile-avatar {
            width: 100px; height: 100px; background: #f1f5f9; color: var(--primary);
            border-radius: 50%; display: flex; align-items: center; 
            justify-content: center; font-weight: 800; font-size: 35px;
            margin: 0 auto 15px; border: 3px solid var(--border);
        }

        .name-tag { text-align: center; margin-bottom: 25px; }
        .name-tag h2 { margin: 0; font-size: 22px; color: var(--primary); }
        .name-tag span { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }

        .info-block { margin-bottom: 20px; }
        .info-block label { display: block; font-size: 10px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px; }
        .info-block p { margin: 0; font-size: 14px; font-weight: 500; }

        /* Main Content Info */
        .section-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 2px solid var(--bg-light);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .section-header h3 { margin: 0; color: var(--primary); font-size: 18px; }

        .clinical-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .status-badge {
            background: #dcfce7;
            color: #166534;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .back-btn {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 15px;
        }
        .back-btn:hover { color: var(--primary); }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box"><img src="oral logo.png" alt="OralSync" class="main-logo"></div>
            <nav class="menu">
                <a href="admin_dashboard.php" class="menu-item"><span>📊</span> Overview</a>
                <a href="manage_users.php" class="menu-item"><span>👥</span> Users</a>
                <a href="logs.php" class="menu-item"><span>📄</span> Reports</a>
                <a href="services.php" class="menu-item"><span>🦷</span> Services</a>
                <a href="staff.php" class="menu-item active"><span>👨‍⚕️</span> Staff</a>
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <h1 class="header-title">Staff Professional Profile</h1>
            <div class="header-profile">
                <span>Welcome, <strong>Admin</strong> | </span>
                <div class="profile-icon-circle">👤</div>
            </div>
        </header>

        <a href="staff.php" class="back-btn">← Return to Directory</a>

        <div class="profile-container">
            <div class="card">
                <div class="profile-avatar"><?php echo substr($staff['username'], 0, 1); ?></div>
                <div class="name-tag">
                    <h2><?php echo ($staff['role'] == 'Dentist' ? 'Dr. ' : '') . htmlspecialchars($staff['username']); ?></h2>
                    <span><?php echo htmlspecialchars($staff['role']); ?></span>
                </div>

                <div class="info-block">
                    <label>Email Address</label>
                    <p><?php echo htmlspecialchars($staff['email']); ?></p>
                </div>
                <div class="info-block">
                    <label>Phone Number</label>
                    <p><?php echo htmlspecialchars($staff['phone_number'] ?? 'Not Provided'); ?></p>
                </div>
                <div class="info-block">
                    <label>Account Status</label>
                    <span class="status-badge">Active</span>
                </div>
            </div>

            <div class="card">
                <div class="section-header">
                    <h3>Clinical Credentials</h3>
                    <a href="edit_staff_details.php?id=<?php echo $staff['user_id']; ?>" style="font-size: 12px; color: var(--primary); font-weight: 700; text-decoration: none;">Update Details</a>
                </div>

                <div class="clinical-grid">
                    <div class="info-block">
                        <label>Primary Specialization</label>
                        <p><?php echo htmlspecialchars($staff['specialization'] ?? 'General Practitioner'); ?></p>
                    </div>
                    <div class="info-block">
                        <label>License Number</label>
                        <p><?php echo htmlspecialchars($staff['license_number'] ?? 'Pending Verification'); ?></p>
                    </div>
                    <div class="info-block">
                        <label>Assigned Schedule</label>
                        <p><?php echo htmlspecialchars($staff['schedule_days'] ?? 'To be assigned'); ?></p>
                    </div>
                    <div class="info-block">
                        <label>Joined System</label>
                        <p><?php echo date('F d, Y', strtotime($staff['date_created'])); ?></p>
                    </div>
                </div>

                <div class="info-block" style="margin-top: 20px;">
                    <label>Professional Biography</label>
                    <p style="line-height: 1.6; color: var(--text-main);">
                        <?php echo nl2br(htmlspecialchars($staff['bio'] ?? 'No biography provided yet. Update the profile to add professional background and qualifications.')); ?>
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>