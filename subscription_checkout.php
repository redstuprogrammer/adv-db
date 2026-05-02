<?php
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

// Role Check Implementation - Ensure user is logged in
$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('admin');

require_once __DIR__ . '/includes/connect.php';
require_once 'includes/subscription_tiers.php';
require_once __DIR__ . '/includes/date_clock.php';

$tenantName = $sessionManager->getTenantData()['tenant_name'] ?? 'OralSync Clinic';
$tenantId = $sessionManager->getTenantId();
$tenantSlug = $sessionManager->getCurrentTenantSlug();

$tiers = getAllTiers();
$selectedPlan = $_GET['plan'] ?? '';
if (!isset($tiers[$selectedPlan]) && $selectedPlan !== 'trial') {
    $selectedPlan = '';
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Clinic Subscription</title>
    <link rel="stylesheet" href="tenant_style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .checkout-container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 40px;
            border-radius: 16px;
            border: 1px solid var(--tenant-border);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }
        .checkout-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .checkout-header h2 {
            font-size: 24px;
            font-weight: 800;
            color: var(--tenant-accent);
            margin: 0 0 12px 0;
        }
        .checkout-header p {
            font-size: 15px;
            color: var(--tenant-muted);
            margin: 0;
            line-height: 1.6;
        }
        .plan-selection {
            margin-bottom: 32px;
        }
        .plan-label {
            display: block;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1e293b;
            font-size: 14px;
        }
        .plan-select {
            width: 100%;
            padding: 16px;
            border: 2px solid var(--tenant-border);
            border-radius: 12px;
            background: #fff;
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            transition: all 0.2s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 20px;
        }
        .plan-select:focus {
            outline: none;
            border-color: var(--tenant-accent);
            box-shadow: 0 0 0 4px rgba(13, 59, 102, 0.1);
        }
        .btn-pay {
            background: var(--tenant-accent);
            color: white;
            border: none;
            width: 100%;
            padding: 18px;
            border-radius: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(13, 59, 102, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(13, 59, 102, 0.3);
            filter: brightness(1.1);
        }
        .btn-pay:active {
            transform: translateY(0);
        }
        .secure-badge {
            margin-top: 24px;
            text-align: center;
            font-size: 13px;
            color: var(--tenant-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .secure-icon {
            color: #10b981;
        }
        
        /* Plan Cards Styling */
        .plans-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .plan-card {
            border: 2px solid var(--tenant-border);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .plan-card:hover {
            border-color: var(--tenant-accent);
            background: rgba(13, 59, 102, 0.02);
        }
        
        .plan-card.selected {
            border-color: var(--tenant-accent);
            background: rgba(13, 59, 102, 0.05);
            box-shadow: 0 0 0 4px rgba(13, 59, 102, 0.1);
        }
        
        .plan-card.selected::after {
            content: '✓';
            position: absolute;
            top: -10px;
            right: -10px;
            background: var(--tenant-accent);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
        }
        
        .plan-info h3 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .plan-info p {
            margin: 0;
            font-size: 14px;
            color: var(--tenant-muted);
        }
        
        .plan-price {
            font-weight: 800;
            font-size: 18px;
            color: var(--tenant-accent);
        }

        .checkout-container {
            max-width: 650px;
        }
    </style>
</head>
<body>

<div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <div class="tenant-main-content">
        <div class="tenant-header-bar">
            <div class="tenant-header-title">
                Subscription Management
            </div>
            <?php renderDateClock(); ?>
        </div>

        <div class="checkout-container">
            <div class="checkout-header">
                <h2>Clinic Subscription Renewal</h2>
                <p>Choose a plan that fits your clinic's growing needs. Secure payments powered by PayMongo.</p>
            </div>

            <form action="subscription_gateway.php" method="POST" id="checkout-form">
                <input type="hidden" name="tier_key" id="tier_key_input" value="<?php echo h($selectedPlan); ?>" required>
                
                <div class="plan-selection">
                    <label class="plan-label">Select Subscription Plan</label>
                    <div class="plans-grid">
                        <?php 
                        // We include trial in the display if requested, but subscription_gateway might need handling
                        $allDisplayTiers = getAllTiers();
                        foreach ($allDisplayTiers as $key => $tier): 
                            $isSelected = ($selectedPlan === $key);
                        ?>
                            <div class="plan-card <?php echo $isSelected ? 'selected' : ''; ?>" 
                                 onclick="selectPlan('<?php echo $key; ?>')"
                                 data-tier="<?php echo $key; ?>">
                                <div class="plan-info">
                                    <h3><?php echo h($tier['name']); ?></h3>
                                    <p><?php echo h($tier['description']); ?></p>
                                </div>
                                <div class="plan-price">
                                    <?php echo $tier['price_max'] > 0 ? '₱' . number_format($tier['price_max'], 2) : 'Free'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <input type="hidden" name="tenant_id" value="<?php echo $tenantId; ?>">
                <input type="hidden" name="source" value="web">
                
                <button type="submit" class="btn-pay">
                    <span>💳</span>
                    Proceed to Payment
                </button>
            </form>

            <div class="secure-badge">
                <span class="secure-icon">🔒</span>
                Securely processed by PayMongo
            </div>
        </div>
    </div>
</div>

<script>
    <?php printDateClockScript(); ?>

    function selectPlan(tierKey) {
        // Update hidden input
        document.getElementById('tier_key_input').value = tierKey;
        
        // Update UI
        document.querySelectorAll('.plan-card').forEach(card => {
            card.classList.remove('selected');
            if (card.getAttribute('data-tier') === tierKey) {
                card.classList.add('selected');
            }
        });
    }

    // Form validation
    document.getElementById('checkout-form').addEventListener('submit', function(e) {
        const tierKey = document.getElementById('tier_key_input').value;
        if (!tierKey) {
            e.preventDefault();
            alert('Please select a subscription plan.');
        }
    });
</script>

</body>
</html>