<?php
session_start();
include "db.php";

// 1. SECURITY CHECK: Only Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$msg = "";
$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    header("Location: manage_users.php");
    exit();
}

// 2. FETCH EXISTING USER DATA
$stmt = $conn->prepare("SELECT username, email, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User not found.");
}

// 3. HANDLE UPDATE REQUEST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_role = $_POST['role'];
    $new_email = trim($_POST['email']);

    $update = $conn->prepare("UPDATE users SET role = ?, email = ? WHERE user_id = ?");
    $update->bind_param("ssi", $new_role, $new_email, $user_id);

    if ($update->execute()) {
        $msg = "<div class='alert success'>User updated successfully! <a href='manage_users.php'>Back to list</a></div>";
        // Update local variable to reflect change in the form
        $user['role'] = $new_role;
        $user['email'] = $new_email;
    } else {
        $msg = "<div class='alert error'>Error updating user.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User | OralSync</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        .edit-container {
            max-width: 500px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #0d3b66; margin-bottom: 8px; }
        .form-group input { 
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 25px; outline: none; 
            background: #f9f9f9; color: #555;
        }

        /* Role Selector cards */
        .role-selector { display: flex; gap: 10px; margin-top: 10px; }
        .role-card { flex: 1; position: relative; }
        .role-card input { position: absolute; opacity: 0; }
        .role-label {
            display: block; padding: 15px 10px; border: 2px solid #eee; border-radius: 12px;
            text-align: center; cursor: pointer; transition: 0.3s; font-weight: 600; font-size: 13px;
        }
        .role-card input:checked + .role-label {
            border-color: #0d3b66; background: #f0f7ff; color: #0d3b66;
        }

        .btn-save {
            background: #0d3b66; color: white; border: none; width: 100%; padding: 13px;
            border-radius: 25px; font-weight: bold; cursor: pointer; margin-top: 20px;
        }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="edit-container">
    <h2 style="color: #0d3b66; text-align: center;">Modify User Permissions</h2>
    <p style="text-align: center; color: #666; font-size: 14px;">Updating account: <strong><?= htmlspecialchars($user['username']) ?></strong></p>
    
    <?= $msg ?>

    <form method="POST">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>

        <div class="form-group">
            <label>Access Level (Role)</label>
            <div class="role-selector">
                <label class="role-card">
                    <input type="radio" name="role" value="Admin" <?= $user['role'] == 'Admin' ? 'checked' : '' ?>>
                    <span class="role-label">Admin</span>
                </label>
                <label class="role-card">
                    <input type="radio" name="role" value="Receptionist" <?= $user['role'] == 'Receptionist' ? 'checked' : '' ?>>
                    <span class="role-label">Reception</span>
                </label>
                <label class="role-card">
                    <input type="radio" name="role" value="Dentist" <?= $user['role'] == 'Dentist' ? 'checked' : '' ?>>
                    <span class="role-label">Dentist</span>
                </label>
            </div>
        </div>

        <button type="submit" class="btn-save">UPDATE PERMISSIONS</button>
        <div style="text-align: center; margin-top: 15px;">
            <a href="manage_users.php" style="color: #999; text-decoration: none; font-size: 13px;">Cancel and go back</a>
        </div>
    </form>
</div>

</body>
</html>