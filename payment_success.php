<?php
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/security_headers.php';

$session_id = $_GET['session_id'] ?? '';

// We can check the payment status here if needed, 
// but the webhook handles the actual database update.
// This page is mostly for user confirmation.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful | OralSync</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
        body {
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }
        .success-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            text-align: center;
            max-width: 450px;
            width: 90%;
        }
        .success-icon {
            font-size: 64px;
            margin-bottom: 20px;
            display: block;
        }
        h1 {
            color: #0d3b66;
            font-weight: 900;
            margin-bottom: 10px;
        }
        p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .btn-return {
            display: inline-block;
            background: #0d3b66;
            color: white;
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            transition: transform 0.2s;
        }
        .btn-return:hover {
            transform: translateY(-2px);
            background: #154c82;
        }
    </style>
</head>
<body>
    <div class="success-card">
        <span class="success-icon">✅</span>
        <h1>Payment Successful!</h1>
        <p>Thank you for your payment. Your subscription has been updated and your account is now active.</p>
        <p style="font-size: 12px; color: #94a3b8;">Session ID: <?php echo htmlspecialchars($session_id); ?></p>
        <a href="dashboard.php" class="btn-return">Go to Dashboard</a>
    </div>
</body>
</html>
