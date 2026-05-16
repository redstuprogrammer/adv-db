<?php
session_start();
include "db.php"; 

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim(mysqli_real_escape_string($conn, $_POST['username']));
    $email = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password']; // New field

    // 1. Validation: Ensure no empty inputs
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } 
    // 2. New Validation: Password Match
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    }
    // 3. Validation: Proper email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Validation: Prevent duplicate usernames
        $checkUser = $conn->prepare("SELECT username FROM users WHERE username = ?");
        $checkUser->bind_param("s", $username);
        $checkUser->execute();
        
        if ($checkUser->get_result()->num_rows > 0) {
            $error = "Username already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'Dentist';
            
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);

            if ($stmt->execute()) {
                // LOG THE MOVEMENT
                $_SESSION['username'] = $username; 
                $_SESSION['role'] = $role;
                logActivity($conn, "Registration", "New Dentist account registered: $username");
                
                session_unset(); 
                session_destroy();

                $success = "Dentist account created! <a href='login.php' style='color:#0d3b66;'>Login here</a>";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Dentist Registration</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        body { background: #f4faff; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .signup-card { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 400px; text-align: center; margin: 20px; }
        .form-group { text-align: left; margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; color: #0d3b66; margin-bottom: 8px; font-size: 14px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 25px; box-sizing: border-box; outline: none; transition: 0.3s; }
        .form-group input:focus { border-color: #0d3b66; box-shadow: 0 0 5px rgba(13,59,102,0.2); }
        .btn-signup { background: #0d3b66; color: white; border: none; width: 100%; padding: 13px; border-radius: 25px; font-weight: bold; cursor: pointer; margin-top: 10px; transition: 0.3s; }
        .btn-signup:hover { background: #164a7d; transform: translateY(-1px); }
        .alert { padding: 10px; border-radius: 5px; font-size: 13px; margin-bottom: 15px; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
<div class="signup-card">
    <img src="oral logo.png" alt="OralSync" style="width: 80px; margin-bottom: 15px;">
    <h2 style="color: #0d3b66; margin: 0;">Dentist Signup</h2>
    <p style="color: #666; font-size: 14px; margin-bottom: 25px;">Create your professional account</p>

    <?php if($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

    <form action="signup.php" method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="Enter username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" required>
        </div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="dentist@oralsync.com" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-signup">CREATE ACCOUNT</button>
    </form>
    <p style="margin-top: 20px; font-size: 13px; color: #666;">Already have an account? <a href="login.php" style="color: #ff6b6b; font-weight: bold; text-decoration: none;">Log in</a></p>
</div>
</body>
</html>