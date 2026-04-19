<?php
require_once 'includes/connect.php';

$plainPassword = 'admin123'; // Change this to your desired password
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($conn, "UPDATE super_admins SET password_hash = ? WHERE username = ?");
mysqli_stmt_bind_param($stmt, "ss", $hashedPassword, 'admin');
if (mysqli_stmt_execute($stmt)) {
    echo "Password updated successfully.";
} else {
    echo "Error updating password: " . mysqli_error($conn);
}
mysqli_stmt_close($stmt);
?>