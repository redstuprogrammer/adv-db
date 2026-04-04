<?php
session_start();
include "db.php";

$search = $_POST['search'] ?? '';
$whereClause = "";

if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $whereClause = " WHERE (username LIKE '%$search%' 
                     OR email LIKE '%$search%' 
                     OR role LIKE '%$search%') ";
}

$query = "SELECT * FROM users $whereClause ORDER BY role ASC";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $roleClass = 'role-' . strtolower($row['role']);
        $current_user_id = $_SESSION['user_id'] ?? 0;
        
        echo "<tr>
                <td><strong>".htmlspecialchars($row['username'])."</strong></td>
                <td>".htmlspecialchars($row['email'])."</td>
                <td><span class='role-badge $roleClass'>".$row['role']."</span></td>
                <td>".date('M d, Y', strtotime($row['date_created']))."</td>
                <td>
                    <a href='edit_user.php?id=".$row['user_id']."' class='action-btn edit-btn'>Edit Role</a>";
        
        if($row['user_id'] != $current_user_id) {
            echo "<a href='manage_users.php?delete=".$row['user_id']."' 
                     class='action-btn delete-btn' 
                     onclick='return confirm(\"Are you sure?\")'>Delete</a>";
        } else {
            echo "<span style='color: #bbb; font-size: 12px; font-style: italic;'>Current User</span>";
        }
        
        echo "</td></tr>";
    }
} else {
    echo "<tr><td colspan='5' style='text-align: center; padding: 50px; color: #999;'>No results found.</td></tr>";
}
?>