<?php
ob_start(); 
header('Content-Type: application/json');
require_once 'connect.php'; 

$response = [
    'success' => false, 
    'message' => 'An unexpected error occurred.'
];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $clinic = mysqli_real_escape_string($conn, $_POST['clinicName']);
        $owner  = mysqli_real_escape_string($conn, $_POST['ownerName']);
        $email  = mysqli_real_escape_string($conn, $_POST['email']);
        $phone  = mysqli_real_escape_string($conn, $_POST['phone']);
        $addr   = mysqli_real_escape_string($conn, $_POST['address']);
        $city   = mysqli_real_escape_string($conn, $_POST['city']);
        $prov   = mysqli_real_escape_string($conn, $_POST['province']);
        
        // 1. Generate Auto-Password
        $temp_password = substr(bin2hex(random_bytes(4)), 0, 8);
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // 2. Duplicate Check
        $checkQuery = "SELECT company_name, contact_email FROM tenants WHERE company_name = ? OR contact_email = ? LIMIT 1";
        $stmtCheck = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($stmtCheck, "ss", $clinic, $email);
        mysqli_stmt_execute($stmtCheck);
        $resultCheck = mysqli_stmt_get_result($stmtCheck);

        if ($row = mysqli_fetch_assoc($resultCheck)) {
            throw new Exception("Clinic name or email already exists.");
        }

        // 3. Generate Slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $clinic))) . '-' . substr(uniqid(), -4);

        // 4. Updated Insert: Removed must_change_password column and the '1' value
        $sql = "INSERT INTO tenants (company_name, owner_name, contact_email, password, phone, address, city, province, subdomain_slug, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";
        
        $stmt = mysqli_prepare($conn, $sql);
        // Ensure bind_param matches the number of '?' in the SQL above (9 total)
        mysqli_stmt_bind_param($stmt, "sssssssss", $clinic, $owner, $email, $hashed_password, $phone, $addr, $city, $prov, $slug);

        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($conn);
            
            if (function_exists('logActivity')) {
                logActivity($conn, 'Registration', "Registered: $clinic", $new_id);
            }
            
            $response = [
                'success' => true, 
                'message' => 'Clinic registered successfully!',
                'temp_password' => $temp_password, 
                'slug' => $slug
            ];
        } else {
            throw new Exception("Database error: " . mysqli_error($conn));
        }
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
exit;