<?php
session_start();
include "db.php";

// Check if test mode is requested
$test_role = isset($_GET['role']) ? $_GET['role'] : null;
if ($test_role) {
    // Set session for test role
    $_SESSION['user_id'] = 1; // Default user ID
    $_SESSION['username'] = 'Test User';
    $_SESSION['role'] = $test_role;

    // Redirect to appropriate dashboard based on role
    switch($test_role) {
        case 'Admin':
            header("Location: dashboard.php");
            break;
        case 'Dentist':
            header("Location: dentist_dashboard.php");
            break;
        case 'Receptionist':
            header("Location: receptionist_dashboard.php");
            break;
        case 'Patient':
            header("Location: patient_view.php");
            break;
        default:
            header("Location: dashboard.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OralSync - Test All User Roles</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .test-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .role-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 10px 0;
            text-align: center;
            transition: all 0.3s ease;
        }
        .role-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transform: translateY(-2px);
        }
        .role-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .role-card p {
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        .test-btn {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s ease;
        }
        .test-btn:hover {
            background: #2980b9;
        }
        .admin-btn { background: #e74c3c; }
        .admin-btn:hover { background: #c0392b; }
        .dentist-btn { background: #27ae60; }
        .dentist-btn:hover { background: #229954; }
        .receptionist-btn { background: #f39c12; }
        .receptionist-btn:hover { background: #e67e22; }
        .patient-btn { background: #9b59b6; }
        .patient-btn:hover { background: #8e44ad; }
        .stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .stats h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🦷 OralSync - Test All User Roles</h1>
        <p>Welcome to the OralSync testing interface! Click any button below to test the system as different user types. The system has been populated with realistic sample data.</p>

        <div class="stats">
            <h3>📊 Current Database Statistics</h3>
            <?php
            // Get statistics
            $user_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
            $patient_count = $conn->query("SELECT COUNT(*) as count FROM patient")->fetch_assoc()['count'];
            $appointment_count = $conn->query("SELECT COUNT(*) as count FROM appointment")->fetch_assoc()['count'];
            $payment_total = $conn->query("SELECT SUM(amount) as total FROM payment WHERE status = 'Paid'")->fetch_assoc()['total'];
            ?>
            <div class="stat-item"><span>👥 Total Users:</span><span><?php echo $user_count; ?></span></div>
            <div class="stat-item"><span>🧑‍⚕️ Patients:</span><span><?php echo $patient_count; ?></span></div>
            <div class="stat-item"><span>📅 Appointments:</span><span><?php echo $appointment_count; ?></span></div>
            <div class="stat-item"><span>💰 Total Payments:</span><span>₱<?php echo number_format($payment_total, 2); ?></span></div>
        </div>

        <h2>🎭 Test User Roles</h2>

        <div class="role-card">
            <h3>👑 Administrator</h3>
            <p>Full system access - manage users, view statistics, oversee all operations</p>
            <a href="?role=Admin" class="test-btn admin-btn">Test as Admin</a>
        </div>

        <div class="role-card">
            <h3>🦷 Dentist</h3>
            <p>Access patient records, clinical notes, treatment history, appointment schedules</p>
            <a href="?role=Dentist" class="test-btn dentist-btn">Test as Dentist</a>
        </div>

        <div class="role-card">
            <h3>📋 Receptionist</h3>
            <p>Manage appointments, patient registration, billing, front desk operations</p>
            <a href="?role=Receptionist" class="test-btn receptionist-btn">Test as Receptionist</a>
        </div>

        <div class="role-card">
            <h3>👤 Patient</h3>
            <p>View personal appointments, medical history, treatment records</p>
            <a href="?role=Patient" class="test-btn patient-btn">Test as Patient</a>
        </div>

        <h2>📋 Sample Data Included</h2>
        <ul>
            <li><strong>Users:</strong> Multiple dentists, receptionists, admins, and patients</li>
            <li><strong>Patients:</strong> 8 diverse patients with complete medical histories</li>
            <li><strong>Appointments:</strong> Scheduled and completed appointments across different services</li>
            <li><strong>Payments:</strong> Various payment records with different statuses</li>
            <li><strong>Clinical Notes:</strong> Detailed treatment notes and procedures</li>
            <li><strong>Dental Charts:</strong> Tooth condition tracking and treatment planning</li>
            <li><strong>Treatment Records:</strong> Complete procedure documentation</li>
        </ul>

        <p><strong>Note:</strong> All test sessions bypass normal authentication. Use the role buttons above to instantly access any part of the system.</p>

        <p><a href="index.html" style="color: #3498db;">← Back to Login Page</a></p>
    </div>
</body>
</html>

<?php $conn->close(); ?>
        <h2>👩‍💼 Receptionist Pages</h2>
        <ul class="page-list">
            <li><a href="receptionist_dashboard.php?role=receptionist">Dashboard</a></li>
            <li><a href="receptionist_appoinment.php?role=receptionist">Appointments</a></li>
            <li><a href="receptionist_billing.php?role=receptionist">Billing</a></li>
            <li><a href="receptionist_calendar.php?role=receptionist">Calendar</a></li>
            <li><a href="patients.php?role=receptionist">Patients</a></li>
        </ul>
    </div>

    <div class="role-section">
        <h2>🎫 Patient Pages</h2>
        <ul class="page-list">
            <li><a href="appointments.php?role=patient">My Appointments</a></li>
            <li><a href="calendar.php?role=patient">Calendar</a></li>
            <li><a href="patient_view.php?role=patient">Profile</a></li>
        </ul>
    </div>

    <div class="role-section">
        <h2>🛠️ Other Pages</h2>
        <ul class="page-list">
            <li><a href="services.php">Services</a></li>
            <li><a href="logs.php?role=admin">Activity Logs</a></li>
        </ul>
    </div>

</div>

</body>
</html>
