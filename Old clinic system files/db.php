<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "oralsync";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// TEST MODE: Set role via URL parameter (?role=dentist, ?role=admin, ?role=receptionist)
if (isset($_GET['role'])) {
    $testRoles = ['dentist', 'admin', 'receptionist', 'staff', 'patient'];
    if (in_array(strtolower($_GET['role']), $testRoles)) {
        $_SESSION['role'] = ucfirst($_GET['role']);
        $_SESSION['user_id'] = 1; // Default test user ID
        $_SESSION['username'] = 'TestUser_' . $_SESSION['role'];
    }
}
function logActivity($conn, $activity_type, $details) {
    // Check session carefully
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'System';
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Unknown';
    
    $u = mysqli_real_escape_string($conn, $username);
    $r = mysqli_real_escape_string($conn, $role);
    $d = mysqli_real_escape_string($conn, $details);
    $t = mysqli_real_escape_string($conn, $activity_type);
    
    // Ensure column order matches your table structure
    $query = "INSERT INTO admin_logs (log_date, log_time, activity_type, action_details, username, user_role) 
              VALUES (CURDATE(), CURTIME(), '$t', '$d', '$u', '$r')";
    
    return $conn->query($query);
}
?>
