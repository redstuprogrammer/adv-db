<?php
include "db.php";

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify token and expiration
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE reset_token = ? AND token_expire > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Invalid or expired token.");
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $token = $_POST['token'];

    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expire = NULL WHERE reset_token = ?");
    $stmt->bind_param("ss", $new_pass, $token);
    
    if ($stmt->execute()) {
        header("Location: login.php?reset=success");
    }
}
?>

<form method="POST">
    <input type="hidden" name="token" value="<?= $_GET['token'] ?>">
    <input type="password" name="password" placeholder="New Password" required>
    <button type="submit">Update Password</button>
</form>