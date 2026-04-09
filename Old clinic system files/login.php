<?php
session_start();
include "db.php";
$error = "";

// 1. COOKIE CHECK: Auto-fill if 'Remember Me' was previously checked
$saved_user = isset($_COOKIE['remember_user']) ? $_COOKIE['remember_user'] : "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim(mysqli_real_escape_string($conn, $_POST['username']));
    $password = $_POST['password'];
    $remember = isset($_POST['remember']); // Check if checkbox is ticked

    $stmt = $conn->prepare("SELECT user_id, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $username;

            // 2. HANDLE REMEMBER ME: Save cookie for 30 days or delete if unchecked
            if ($remember) {
                setcookie("remember_user", $username, time() + (86400 * 30), "/"); 
            } else {
                setcookie("remember_user", "", time() - 3600, "/");
            }

            // LOG LOGIN MOVEMENT
            logActivity($conn, "Login", "User logged in successfully");

            switch($user['role']) {
                case 'Admin': header("Location: dashboard.php"); break;
                case 'Receptionist': header("Location: receptionist_dashboard.php"); break;
                case 'Dentist': header("Location: dentist_dashboard.php"); break;
            }
            exit();
        } else { $error = "Invalid password."; }
    } else { $error = "User not found."; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OralSync | Login</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        body { background: #f4faff; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: 'Segoe UI', sans-serif; }
        .login-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); width: 100%; max-width: 400px; text-align: center; }
        .form-group { text-align: left; margin-bottom: 20px; position: relative; }
        .form-group label { display: block; font-weight: 600; color: #0d3b66; margin-bottom: 8px; font-size: 14px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 25px; box-sizing: border-box; outline: none; transition: 0.3s; }
        
        /* Show Password Icon Style */
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 38px;
            cursor: pointer;
            color: #64748b;
            font-size: 12px;
            font-weight: bold;
        }

        .btn-login { background: #0d3b66; color: white; border: none; width: 100%; padding: 12px; border-radius: 25px; font-weight: bold; cursor: pointer; font-size: 16px; margin-top: 10px; transition: 0.3s; }
        .btn-login:hover { background: #164a7d; }
        .error-box { background: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; font-size: 13px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="login-card">
    <img src="oral logo.png" alt="OralSync" style="width: 100px; margin-bottom: 20px;">
    <h2>Welcome Back!</h2>
    <p>Please enter your details</p>

    <?php if($error): ?><div class="error-box"><?= $error ?></div><?php endif; ?>

    <form action="login.php" method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($saved_user) ?>" placeholder="Enter username" required>
        </div>
        
        <div class="form-group">
            <label>Password</label>
            <input type="password" id="passwordField" name="password" placeholder="Enter password" required>
            <span class="toggle-password" onclick="togglePassword()">SHOW</span>
        </div>
        
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; font-size: 13px; color: #666;">
            <input type="checkbox" name="remember" id="remember" <?= $saved_user ? 'checked' : '' ?>> 
            <label for="remember" style="margin:0; font-weight: normal; cursor: pointer;">Remember me?</label>
        </div>
        
        <button type="submit" class="btn-login">LOG IN</button>
    </form>

    <div style="margin-top: 25px; font-size: 13px; color: #666;">
        Don't have an account? <a href="signup.php" style="color: #ff6b6b; font-weight: bold; text-decoration: none;">Sign up</a>
    </div>
</div>



<script>
    // Toggle Password Visibility
    function togglePassword() {
        const passwordField = document.getElementById('passwordField');
        const toggleBtn = document.querySelector('.toggle-password');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleBtn.textContent = 'HIDE';
        } else {
            passwordField.type = 'password';
            toggleBtn.textContent = 'SHOW';
        }
    }
</script>

</body>
</html>