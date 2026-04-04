<?php
session_start();
include "db.php";

// 1. SECURITY CHECK: Only Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// 2. LIVE SEARCH HANDLER (Responds to AJAX)
if (isset($_POST['action']) && $_POST['action'] == 'fetch_users') {
    $search = mysqli_real_escape_string($conn, $_POST['query']);
    $whereClause = "";

    if (!empty($search)) {
        $whereClause = " WHERE (username LIKE '%$search%' 
                         OR email LIKE '%$search%' 
                         OR role LIKE '%$search%') ";
    }

    $query = "SELECT * FROM users $whereClause ORDER BY role ASC";
    $result = $conn->query($query);
    $output = '';

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $roleClass = 'role-' . strtolower($row['role']);
            $current_id = $_SESSION['user_id'] ?? 0;
            $date = date('M d, Y', strtotime($row['date_created']));

            $output .= "<tr>
                <td class='user-cell'>
                    <div class='avatar-small'>".substr($row['username'], 0, 1)."</div>
                    <strong>".htmlspecialchars($row['username'])."</strong>
                </td>
                <td>".htmlspecialchars($row['email'])."</td>
                <td><span class='role-badge $roleClass'>".$row['role']."</span></td>
                <td>$date</td>
                <td class='actions-cell'>";
            
            $output .= "<a href='edit_user.php?id=".$row['user_id']."' class='action-link edit'>Edit</a>";
            
            if($row['user_id'] != $current_id) {
                $output .= "<a href='manage_users.php?delete=".$row['user_id']."' 
                               class='action-link delete' 
                               onclick='return confirm(\"Remove access for this user?\")'>Revoke</a>";
            } else {
                $output .= "<span class='self-label'>Active Session</span>";
            }
            $output .= "</td></tr>";
        }
    } else {
        $output = "<tr><td colspan='5' class='empty-state'>No matching staff members found.</td></tr>";
    }
    echo $output;
    exit; 
}

// 3. DELETE LOGIC
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id != $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE user_id = $id");
        header("Location: manage_users.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | User Management</title>
    <link rel="stylesheet" href="style1.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary: #0d3b66;
            --bg-light: #f8fafc;
            --border: #e2e8f0;
            --text-main: #334155;
            --text-muted: #64748b;
        }

        body { background-color: var(--bg-light); color: var(--text-main); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        /* HEADER ALIGNMENT (Matching your image) */
        .top-bar {
            display: flex;
            justify-content: space-between; /* Pushes items to far ends */
            align-items: center;
            padding: 20px 0;
            margin-bottom: 10px;
        }

        .header-title {
            color: var(--primary);
            font-size: 24px;
            font-weight: 800;
            margin: 0;
        }

        .header-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-muted);
            font-size: 14px;
        }

        .profile-icon-circle {
            width: 35px;
            height: 35px;
            background: #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        /* TABLE CONTAINER */
        .management-card { 
            background: #fff; 
            border-radius: 12px; 
            border: 1px solid var(--border);
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 30px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-wrapper { position: relative; width: 350px; }
        .search-wrapper input {
            width: 100%; padding: 10px 15px 10px 40px; border-radius: 8px;
            border: 1px solid var(--border); outline: none; font-size: 14px;
        }
        .search-icon { position: absolute; left: 14px; top: 11px; color: var(--text-muted); }

        .btn-add {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* TABLE ELEMENTS */
        .user-table { width: 100%; border-collapse: collapse; }
        .user-table th { 
            background: #fcfcfd; 
            color: var(--text-muted); 
            padding: 15px 30px; 
            text-align: left; 
            font-size: 11px; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }
        .user-table td { padding: 16px 30px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }

        .user-cell { display: flex; align-items: center; gap: 12px; }
        .avatar-small {
            width: 30px; height: 30px; background: #f1f5f9; color: var(--primary);
            border-radius: 6px; display: flex; align-items: center; 
            justify-content: center; font-weight: 800; font-size: 12px;
        }

        .role-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .role-admin { background: #fee2e2; color: #991b1b; }
        .role-dentist { background: #e0f2fe; color: #075985; }
        .role-receptionist { background: #dcfce7; color: #166534; }

        .action-link { text-decoration: none; font-weight: 700; font-size: 13px; margin-right: 15px; }
        .action-link.edit { color: var(--primary); }
        .action-link.delete { color: #e11d48; }

        #loader { font-size: 12px; color: var(--primary); display: none; }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box" style="position: relative;">
                <img src="oral%20logo.png" alt="OralSync" class="main-logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                <span style="display:none; font-weight: 900; color: #0d3b66;">OralSync</span>
            </div>
            <nav class="menu">
                <a href="dashboard.php" class="menu-item"><span>📊</span> Overview</a>
                <a href="manage_users.php" class="menu-item active"><span>👥</span> Users</a>
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
            <h1 class="header-title">User Access Management</h1>
            <div class="header-profile">
                <span>Welcome, <strong>Admin</strong> | </span>
                <div class="profile-icon-circle">👤</div>
            </div>
        </header>

        <div class="management-card">
            <div class="card-header">
                <div class="search-wrapper">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="live_search" placeholder="Search staff by name or role..." autocomplete="off">
                </div>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <span id="loader">Processing...</span>
                    <a href="add_user.php" class="btn-add">+ ADD NEW STAFF</a>
                </div>
            </div>

            <table class="user-table">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Email Address</th>
                        <th>Access Level</th>
                        <th>Joined Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="user_table_body">
                    </tbody>
            </table>
        </div>
    </main>
</div>

<script>
$(document).ready(function() {
    function fetchUsers(query = '') {
        $('#loader').show();
        $.ajax({
            url: "manage_users.php", 
            method: "POST",
            data: { action: 'fetch_users', query: query },
            success: function(data) {
                $('#user_table_body').html(data);
                $('#loader').hide();
            }
        });
    }
    fetchUsers();
    $('#live_search').on('keyup', function() {
        fetchUsers($(this).val());
    });
});
</script>
</body>
</html>