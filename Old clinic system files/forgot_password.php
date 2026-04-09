<?php
include "db.php";
use PHPMailer\PHPMailer\PHPMailer; // You will need to install PHPMailer via Composer

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $token = bin2hex(random_bytes(50)); // Generate a secure random token
    $expire = date("Y-m-d H:i:s", strtotime('+1 hour'));

    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, token_expire = ? WHERE email = ?");
    $stmt->bind_param("sss", $token, $expire, $email);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $resetLink = "http://yourdomain.com/reset_password.php?token=" . $token;
        
        // LOG MOVEMENT
        logActivity($conn, "Recovery", "Password reset link requested for: $email");

        // Use mail() or PHPMailer here
        // mail($email, "Password Reset", "Click here: " . $resetLink);
        
        $success = "Recovery link sent! Please check your email.";
    } else {
        $error = "Email address not found.";
    }
}
?>

<div class="login-card">
    <h2>Reset Password</h2>
    <p>Enter your email to receive a link</p>
    <form method="POST">
        <div class="form-group">
            <input type="email" name="email" placeholder="dentist@oralsync.com" required>
        </div>
        <button type="submit" class="btn-login">SEND RESET LINK</button>
    </form>
</div>