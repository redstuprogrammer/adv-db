<?php
session_start();

// Check if user is superadmin
if (empty($_SESSION['superadmin_authed'])) {
    http_response_code(403);
    die('Unauthorized. Please log in as super admin first.');
}

require_once __DIR__ . '/includes/connect.php';

$message = '';
$message_type = 'info';
$records_added = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_data'])) {
    $seed_type = $_POST['seed_data'];
    
    try {
        if ($seed_type === 'tenant_activities') {
            // Get all tenants
            $tenants = [];
            $result = mysqli_query($conn, "SELECT tenant_id, company_name FROM tenants");
            while ($row = mysqli_fetch_assoc($result)) {
                $tenants[] = $row;
            }
            
            if (empty($tenants)) {
                throw new Exception("No tenants found. Please register clinics first.");
            }
            
            // Sample activities
            $activities = [
                ['type' => 'Patient Created', 'desc' => 'New patient record added'],
                ['type' => 'Appointment Scheduled', 'desc' => 'Appointment scheduled for upcoming date'],
                ['type' => 'Payment Received', 'desc' => 'Payment received from patient'],
                ['type' => 'Clinical Notes Added', 'desc' => 'Clinical notes updated for patient'],
                ['type' => 'Staff Member Added', 'desc' => 'New staff member onboarded'],
            ];
            
            // Clear existing sample data (optional)
            // mysqli_query($conn, "DELETE FROM tenant_activity_logs WHERE activity_type IN ('Patient Created', 'Appointment Scheduled', 'Payment Received', 'Clinical Notes Added', 'Staff Member Added')");
            
            // Insert sample data for each tenant
            foreach ($tenants as $tenant) {
                for ($i = 0; $i < 5; $i++) {
                    $activity = $activities[array_rand($activities)];
                    $date = date('Y-m-d H:i:s', strtotime("-" . rand(0, 30) . " days"));
                    
                    $sql = "INSERT INTO tenant_activity_logs (tenant_id, activity_type, activity_description, activity_count, log_date) 
                            VALUES (?, ?, ?, 1, ?)";
                    
                    $stmt = mysqli_prepare($conn, $sql);
                    $tenant_id = $tenant['tenant_id'];
                    mysqli_stmt_bind_param($stmt, "isss", $tenant_id, $activity['type'], $activity['desc'], $date);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $records_added++;
                    }
                }
            }
            
            $message = "✓ Successfully added {$records_added} tenant activity records";
            $message_type = 'success';
            
        } elseif ($seed_type === 'sales_data') {
            // Get all tenants
            $tenants = [];
            $result = mysqli_query($conn, "SELECT tenant_id, company_name, subscription_tier FROM tenants");
            while ($row = mysqli_fetch_assoc($result)) {
                $tenants[] = $row;
            }
            
            if (empty($tenants)) {
                throw new Exception("No tenants found. Please register clinics first.");
            }
            
            // Pricing for tiers
            $tier_prices = [
                'startup' => 124.00,
                'professional' => 249.00,
                'enterprise' => 499.00
            ];
            
            // Clear existing sample data (optional)
            // mysqli_query($conn, "DELETE FROM tenant_subscription_revenue");
            
            // Insert sales data for each tenant
            foreach ($tenants as $tenant) {
                $tier = $tenant['subscription_tier'] ?? 'startup';
                $amount = $tier_prices[$tier] ?? 50.00;
                
                for ($i = 0; $i < 12; $i++) {
                    $month_ago = date('Y-m-d', strtotime("-" . (12 - $i) . " months", strtotime('first day of this month')));
                    $period_start = $month_ago;
                    $period_end = date('Y-m-d', strtotime('last day of month', strtotime($month_ago)));
                    $payment_date = $period_end;
                    
                    $sql = "INSERT INTO tenant_subscription_revenue (tenant_id, subscription_tier, amount, billing_period_start, billing_period_end, status, payment_date) 
                            VALUES (?, ?, ?, ?, ?, 'paid', ?)";
                    
                    $stmt = mysqli_prepare($conn, $sql);
                    $tenant_id = $tenant['tenant_id'];
                    mysqli_stmt_bind_param($stmt, "issss", $tenant_id, $tier, $amount, $period_start, $period_end, $payment_date);
                    
                    if (@mysqli_stmt_execute($stmt)) {
                        $records_added++;
                    }
                }
            }
            
            $message = "✓ Successfully added {$records_added} sales revenue records";
            $message_type = 'success';
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OralSync | Seed Sample Data</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        .seed-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .seed-container h1 {
            color: #0d3b66;
            margin-bottom: 10px;
        }
        
        .seed-subtitle {
            color: #64748b;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .seed-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #f8fafc;
        }
        
        .seed-card h3 {
            color: #0d3b66;
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .seed-card p {
            color: #64748b;
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .seed-button {
            background: #22c55e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 15px;
        }
        
        .seed-button:hover {
            background: #16a34a;
        }
        
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .info-box {
            background: #f0f9ff;
            border: 1px solid #bfdbfe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 13px;
            color: #1e40af;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="seed-container">
        <h1>📊 Seed Sample Data</h1>
        <p class="seed-subtitle">Populate reports tables with sample data for testing and demonstration</p>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="seed-card">
                <h3>Tenant Activities</h3>
                <p>Generate sample tenant activity records (Patient Created, Appointments, Payments, etc.). Creates 5 records per registered clinic, backdated over the last 30 days.</p>
                <button type="submit" name="seed_data" value="tenant_activities" class="seed-button">
                    ✓ Seed Tenant Activities
                </button>
            </div>
            
            <div class="seed-card">
                <h3>Sales Revenue Data</h3>
                <p>Generate sample subscription revenue records. Creates 12 months of payment history for each clinic based on their subscription tier (Startup: $124, Professional: $249, Enterprise: $499).</p>
                <button type="submit" name="seed_data" value="sales_data" class="seed-button">
                    ✓ Seed Sales Data
                </button>
            </div>
        </form>
        
        <div class="info-box">
            <strong>💡 About Sample Data:</strong><br>
            • Each button adds sample records to reports tables<br>
            • Safe to run multiple times (creates new records)<br>
            • Data is randomly distributed across registered clinics<br>
            • Dates are backdated to the last 30 days (activities) or last 12 months (sales)<br>
            • After seeding, visit Reports pages to view the data
        </div>
        
        <div class="info-box" style="background: #fef3c7; border-color: #fcd34d; border-left-color: #f59e0b; color: #92400e; margin-top: 15px;">
            <strong>⚠️ Requirements:</strong><br>
            • At least one clinic must be registered first<br>
            • Clinics are in your tenant list in Dashboard<br>
            • Tables will be created automatically by migrations
        </div>
    </div>
</body>
</html>
