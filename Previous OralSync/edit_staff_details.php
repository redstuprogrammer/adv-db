<?php
session_start();
include "db.php";

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_GET['id'];
$message = "";

// 2. HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $specialization = mysqli_real_escape_string($conn, $_POST['specialization']);
    $license_number = mysqli_real_escape_string($conn, $_POST['license_number']);
    $phone_number   = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $schedule_days  = mysqli_real_escape_string($conn, $_POST['schedule_days']);
    $bio            = mysqli_real_escape_string($conn, $_POST['bio']);

    // Check if record exists in staff_details
    $check = $conn->query("SELECT * FROM staff_details WHERE user_id = $user_id");
    
    if ($check->num_rows > 0) {
        $sql = "UPDATE staff_details SET 
                specialization='$specialization', 
                license_number='$license_number', 
                phone_number='$phone_number', 
                schedule_days='$schedule_days', 
                bio='$bio' 
                WHERE user_id=$user_id";
    } else {
        $sql = "INSERT INTO staff_details (user_id, specialization, license_number, phone_number, schedule_days, bio) 
                VALUES ($user_id, '$specialization', '$license_number', '$phone_number', '$schedule_days', '$bio')";
    }

    if ($conn->query($sql)) {
        header("Location: view_staff_profile.php?id=$user_id&success=1");
        exit();
    } else {
        $message = "Error updating records: " . $conn->error;
    }
}

// 3. FETCH CURRENT DATA
$sql = "SELECT u.username, s.* FROM users u LEFT JOIN staff_details s ON u.user_id = s.user_id WHERE u.user_id = $user_id";
$res = $conn->query($sql);
$data = $res->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Professional Profile | OralSync</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        :root { --primary: #0d3b66; --border: #e2e8f0; }
        body { background-color: #f8fafc; font-family: 'Segoe UI', sans-serif; }
        
        .form-card {
            background: #fff;
            max-width: 700px;
            margin: 40px auto;
            padding: 40px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .form-header { border-bottom: 1px solid var(--border); margin-bottom: 30px; padding-bottom: 10px; }
        .form-header h2 { color: var(--primary); margin: 0; font-size: 22px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group.full { grid-column: span 2; }

        label { display: block; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 8px; }
        input, select, textarea {
            width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px;
            font-size: 14px; outline: none; transition: 0.2s;
        }
        input:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(13,59,102,0.05); }

        .btn-save {
            background: var(--primary); color: #fff; border: none; padding: 14px 30px;
            border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; font-size: 14px;
        }
        .btn-cancel { display: block; text-align: center; margin-top: 15px; color: #64748b; text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>

<div class="form-card">
    <div class="form-header">
        <h2>Edit Professional Details</h2>
        <p style="font-size: 14px; color: #64748b;">Updating profile for: <strong><?php echo htmlspecialchars($data['username']); ?></strong></p>
    </div>

    <?php if($message) echo "<p style='color:red;'>$message</p>"; ?>

    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>Specialization</label>
                <input type="text" name="specialization" placeholder="e.g. Orthodontics" value="<?php echo htmlspecialchars($data['specialization'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>License Number</label>
                <input type="text" name="license_number" placeholder="PRC-1234567" value="<?php echo htmlspecialchars($data['license_number'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Contact Phone</label>
                <input type="text" name="phone_number" placeholder="0917-XXX-XXXX" value="<?php echo htmlspecialchars($data['phone_number'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Duty Days</label>
                <input type="text" name="schedule_days" placeholder="Mon, Tue, Fri" value="<?php echo htmlspecialchars($data['schedule_days'] ?? ''); ?>">
            </div>
            <div class="form-group full">
                <label>Professional Biography</label>
                <textarea name="bio" rows="5" placeholder="Enter background, education, and experience..."><?php echo htmlspecialchars($data['bio'] ?? ''); ?></textarea>
            </div>
        </div>

        <button type="submit" class="btn-save">SAVE PROFESSIONAL PROFILE</button>
        <a href="view_staff_profile.php?id=<?php echo $user_id; ?>" class="btn-cancel">Cancel and Go Back</a>
    </form>
</div>

</body>
</html>