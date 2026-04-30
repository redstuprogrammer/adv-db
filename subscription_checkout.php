<?php
require_once __DIR__ . '/includes/connect.php';
require_once 'includes/subscription_tiers.php';

// In a real app, this would come from session
$tenantId = 1; 

$tiers = getAllTiers();
// Remove 'trial' from the list of purchasable plans
unset($tiers['trial']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OralSync | Clinic Subscription</title>
    <link rel="stylesheet" href="tenant_style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0d3b66;
            --primary-hover: #0a2d4f;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }
        body { 
            background-color: var(--bg); 
            font-family: 'Inter', -apple-system, sans-serif;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .pay-container { 
            width: 100%;
            max-width: 450px; 
            background: var(--card-bg); 
            padding: 40px; 
            border-radius: 16px; 
            border: 1px solid var(--border); 
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.05); 
        }
        .logo {
            text-align: center;
            margin-bottom: 24px;
        }
        .logo h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
            letter-spacing: -0.5px;
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
        }
        .header h2 {
            font-size: 20px;
            margin: 0 0 8px 0;
            color: var(--text-main);
        }
        .header p {
            font-size: 14px;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.5;
        }
        .form-group { margin-bottom: 24px; }
        label { 
            display: block; 
            font-weight: 600; 
            margin-bottom: 10px; 
            color: var(--text-main);
            font-size: 14px;
        }
        select { 
            width: 100%; 
            padding: 14px; 
            border: 1px solid var(--border); 
            border-radius: 10px; 
            background: #fff; 
            font-size: 15px; 
            color: var(--text-main);
            transition: border-color 0.2s, box-shadow 0.2s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 18px;
        }
        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.1);
        }
        .btn-submit { 
            background: var(--primary); 
            color: white; 
            border: none; 
            width: 100%; 
            padding: 16px; 
            border-radius: 10px; 
            font-weight: 700; 
            cursor: pointer; 
            transition: all 0.2s; 
            font-size: 16px; 
            box-shadow: 0 4px 6px -1px rgba(13, 59, 102, 0.2);
        }
        .btn-submit:hover { 
            background: var(--primary-hover); 
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(13, 59, 102, 0.3);
        }
        .btn-submit:active {
            transform: translateY(0);
        }
        .footer-note {
            margin-top: 24px;
            text-align: center;
            font-size: 12px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

<div class="pay-container">
    <div class="logo">
        <h1>OralSync</h1>
    </div>
    <div class="header">
        <h2>Clinic Subscription</h2>
        <p>Renew your professional access and continue managing your dental clinic with ease.</p>
    </div>

    <form action="subscription_gateway.php" method="POST">
        <div class="form-group">
            <label for="tier_key">Subscription Plan</label>
            <select name="tier_key" id="tier_key" required>
                <option value="" disabled selected>-- Select your plan --</option>
                <?php foreach ($tiers as $key => $tier): ?>
                    <option value="<?php echo $key; ?>" data-price="<?php echo $tier['price_max']; ?>">
                        <?php echo htmlspecialchars($tier['name']); ?> 
                        (₱<?php echo number_format($tier['price_max'], 2); ?> / month)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <input type="hidden" name="tenant_id" value="<?php echo $tenantId; ?>">
        <input type="hidden" name="source" value="web">
        
        <button type="submit" class="btn-submit">Proceed to Payment</button>
    </form>

    <div class="footer-note">
        Payments are securely processed by PayMongo.
    </div>
</div>

</body>
</html>