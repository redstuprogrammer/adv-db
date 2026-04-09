<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Manila');

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Receptionist' && $_SESSION['role'] !== 'Staff')) {
    header("Location: login.php");
    exit();
}

$patient = null;

// 2. FETCH EXISTING DATA
if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    // We select everything from the patient table for this specific ID
    $query = "SELECT * FROM patient WHERE patient_id = '$id'";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $patient = $result->fetch_assoc();
    } else {
        header("Location: patients.php?error=notfound");
        exit();
    }
}

// 3. UPDATE LOGIC
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_patient'])) {
    $id = mysqli_real_escape_string($conn, $_POST['patient_id']);
    $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $note = mysqli_real_escape_string($conn, $_POST['note']);

    $sql = "UPDATE patient SET 
            first_name = '$fname', 
            last_name = '$lname', 
            contact_number = '$contact', 
            email = '$email', 
            dob = '$dob', 
            gender = '$gender', 
            address = '$address', 
            medical_note = '$note' 
            WHERE patient_id = '$id'";

    if ($conn->query($sql) === TRUE) {
        header("Location: patients.php?msg=updated");
        exit();
    } else {
        $error = "Update failed: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Edit Patient</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .edit-container { background: white; border-radius: 12px; padding: 40px; max-width: 800px; margin: 50px auto; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #0d3b66; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; box-sizing: border-box; background-color: #fff; }
        .form-control:focus { outline: none; border-color: #0d3b66; box-shadow: 0 0 0 2px rgba(13, 59, 102, 0.1); }
        .btn-update { background: #0d3b66; color: white; border: none; padding: 12px 30px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 15px; transition: 0.3s; }
        .btn-update:hover { background: #154c82; }
        .btn-cancel { background: #64748b; color: white; text-decoration: none; padding: 12px 25px; border-radius: 8px; font-size: 14px; font-weight: bold; display: inline-block; transition: 0.3s; }
        .btn-cancel:hover { background: #475569; }
        .header-title { color: #0d3b66; margin-top: 0; margin-bottom: 30px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; }
    </style>
</head>
<body>

<div class="edit-container">
    <h2 class="header-title">Edit Patient Profile: <?= htmlspecialchars($patient['first_name'] . " " . $patient['last_name']) ?></h2>

    <?php if(isset($error)): ?>
        <div style="background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca;">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <input type="hidden" name="patient_id" value="<?= $patient['patient_id'] ?>">
        
        <div class="form-grid">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($patient['first_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($patient['last_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($patient['contact_number'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($patient['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Birthdate</label>
                <input type="date" name="dob" class="form-control" value="<?= $patient['dob'] ?? '' ?>" required>
            </div>
            <div class="form-group">
                <label>Gender</label>
                <select name="gender" class="form-control">
                    <option value="Male" <?= (isset($patient['gender']) && $patient['gender'] == 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= (isset($patient['gender']) && $patient['gender'] == 'Female') ? 'selected' : '' ?>>Female</option>
                </select>
            </div>
            <div class="form-group full-width">
                <label>Address</label>
                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($patient['address'] ?? '') ?>">
            </div>
            <div class="form-group full-width">
                <label>Medical Note</label>
                <textarea name="note" class="form-control" rows="4"><?= htmlspecialchars($patient['medical_note'] ?? '') ?></textarea>
            </div>
        </div>

        <div style="margin-top: 40px; display: flex; gap: 15px;">
            <button type="submit" name="update_patient" class="btn-update">Save Changes</button>
            <a href="patients.php" class="btn-cancel">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>