<?php
session_start();

// 1. Clear all session variables
$_SESSION = array();

// 2. Destroy the session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// 3. Destroy the session itself
session_destroy();

// 4. Redirect to login with a logout message
header("Location: login.php?message=logged_out");
exit();
?>