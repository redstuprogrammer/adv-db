<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// 2. FETCH SERVICES GROUPED BY CLINIC CATEGORY
$query = "SELECT * FROM service 
          ORDER BY FIELD(category, 'Preventive', 'Pediatric', 'Prosthodontics', 'Cosmetic', 'Orthodontics', 'Surgery', 'Restorative', 'Others'), 
          service_name ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Service Catalog</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        :root { --primary: #0d3b66; --text-main: #1e293b; --text-muted: #64748b; --bg: #f8fafc; }

        .service-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
            gap: 20px; 
            padding-bottom: 40px;
        }

        .category-header {
            grid-column: 1 / -1;
            margin: 40px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .category-header h2 {
            color: var(--primary);
            font-size: 1.25rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .service-card { 
            background: white; 
            border: 1px solid #e2e8f0; 
            border-radius: 12px; 
            padding: 20px; 
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 140px; /* Reduced since price is gone */
        }

        .service-card:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 10px 20px rgba(13, 59, 102, 0.08); 
            border-color: var(--primary); 
        }

        .service-name { font-size: 16px; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
        .service-desc { font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 15px; }

        .card-footer { 
            display: flex; 
            justify-content: flex-end; /* Aligned to right since price is gone */
            align-items: center; 
            margin-top: auto; 
            padding-top: 15px; 
            border-top: 1px solid #f1f5f9; 
        }

        .action-btns a { text-decoration: none; font-size: 12px; font-weight: 600; margin-left: 10px; }
        .edit-link { color: #059669; }
        .del-link { color: #ef4444; }

        .btn-add { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; }

        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.75); align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 450px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 700; font-size: 11px; color: var(--text-muted); text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; }
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
                <a href="services.php" class="menu-item active"><span>🦷</span> Services</a>
                <a href="staff.php" class="menu-item"><span>👨‍⚕️</span> Staff</a>
                <a href="admin_patients.php" class="menu-item"><span>👤</span> Patients</a>
                <a href="admin_appointments.php" class="menu-item"><span>📅</span> Appointments</a>
                <a href="admin_billing.php" class="menu-item"><span>💰</span> Billing</a>
            </nav>
        </div>
        <div class="sidebar-bottom"><a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a></div>
    </aside>

    <main class="main-content">
        <header class="top-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div class="header-left">
                <h1 style="color: var(--primary); margin:0;">Service Catalog</h1>
                <p style="color: var(--text-muted);">Manage clinic procedures and categories</p>
            </div>
            <button class="btn-add" onclick="openAddModal()">+ Add New Service</button>
        </header>

        <div class="service-grid">
            <?php 
            $current_cat = "";
            if ($result->num_rows > 0): 
                while($row = $result->fetch_assoc()): 
                    if ($row['category'] !== $current_cat): 
                        $current_cat = $row['category'];
            ?>
                <div class="category-header">
                    <h2><?= htmlspecialchars($current_cat ?: 'Uncategorized') ?></h2>
                </div>
            <?php endif; ?>

            <div class="service-card">
                <div>
                    <div class="service-name"><?= htmlspecialchars($row['service_name']) ?></div>
                    <div class="service-desc"><?= htmlspecialchars($row['description']) ?></div>
                </div>
                <div class="card-footer">
                    <div class="action-btns">
                        <a href="#" class="edit-link" onclick='openEditModal(<?= json_encode($row) ?>)'>Edit</a>
                        <a href="process_service.php?delete=<?= $row['service_id'] ?>" class="del-link" onclick="return confirm('Remove this service?')">Delete</a>
                    </div>
                </div>
            </div>
            <?php 
                endwhile; 
            else: 
            ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 100px; color: var(--text-muted);">
                    <p>No services found. Click "+ Add New Service" to begin.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<div id="serviceModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle" style="color: var(--primary); margin-top:0; margin-bottom:20px;">Service Details</h3>
        <form action="process_service.php" method="POST">
            <input type="hidden" name="service_id" id="form_service_id">
            
            <div class="form-group">
                <label>Category</label>
                <select name="category" id="form_category" required>
                    <option value="Preventive">Preventive</option>
                    <option value="Pediatric">Pediatric</option>
                    <option value="Prosthodontics">Prosthodontics</option>
                    <option value="Cosmetic">Cosmetic</option>
                    <option value="Orthodontics">Orthodontics</option>
                    <option value="Surgery">Surgery</option>
                    <option value="Restorative">Restorative</option>
                    <option value="Others">Others</option>
                </select>
            </div>

            <div class="form-group">
                <label>Service Name</label>
                <input type="text" name="service_name" id="form_name" required>
            </div>

            <input type="hidden" name="price" id="form_price" value="0.00">

            <div class="form-group">
                <label>Full Description</label>
                <textarea name="description" id="form_desc" rows="4"></textarea>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 25px;">
                <button type="button" onclick="closeModal()" style="flex:1; padding:12px; border:none; border-radius:8px; cursor:pointer; background:#f1f5f9; color:var(--text-muted); font-weight:600;">Cancel</button>
                <button type="submit" class="btn-add" style="flex:2;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('serviceModal');

    function openAddModal() {
        document.getElementById('modalTitle').innerText = "Add New Service";
        document.getElementById('form_service_id').value = "";
        document.getElementById('form_name').value = "";
        document.getElementById('form_desc').value = "";
        modal.style.display = "flex";
    }

    function openEditModal(data) {
        document.getElementById('modalTitle').innerText = "Edit Service";
        document.getElementById('form_service_id').value = data.service_id;
        document.getElementById('form_category').value = data.category;
        document.getElementById('form_name').value = data.service_name;
        document.getElementById('form_desc').value = data.description;
        modal.style.display = "flex";
    }

    function closeModal() { modal.style.display = "none"; }
    window.onclick = function(e) { if (e.target == modal) closeModal(); }
</script>
</body>
</html>