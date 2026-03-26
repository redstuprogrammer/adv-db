<!-- TRIAL STATUS AWARENESS CARD - Optional Dashboard Enhancement -->
<!-- Add this snippet to superadmin_dash.php before the Activity Trend section to show expiring trials -->

<div class="sa-card" style="border-left: 3px solid #f59e0b;">
    <div class="sa-card-header">
        <div>
            <div class="sa-card-title">Trial Expiration Alerts</div>
            <div class="sa-card-subtitle">Tenants on trial ending soon</div>
        </div>
    </div>
    <div style="padding: 20px;">
        <?php
        try {
            // Get all trial tenants expiring within 7 days
            $stmt = $pdo->query("
                SELECT 
                    t.tenant_id,
                    t.company_name,
                    DATEDIFF(COALESCE(t.trial_end_date, DATE_ADD(t.created_at, INTERVAL 14 DAY)), CURDATE()) as days_until_expiry
                FROM tenants t
                WHERE t.subscription_tier = 'trial'
                  AND COALESCE(t.trial_end_date, DATE_ADD(t.created_at, INTERVAL 14 DAY)) > CURDATE()
                  AND DATEDIFF(COALESCE(t.trial_end_date, DATE_ADD(t.created_at, INTERVAL 14 DAY)), CURDATE()) <= 7
                ORDER BY COALESCE(t.trial_end_date, DATE_ADD(t.created_at, INTERVAL 14 DAY)) ASC
                LIMIT 10
            ");
            
            $trials = [];
            while ($row = $stmt->fetch()) {
                $trials[] = $row;
            }
            
            if (count($trials) === 0) {
                echo "<p style='text-align: center; color: var(--sa-muted);'>No trials expiring soon. All good! ✓</p>";
            } else {
                echo "<div style='display: grid; gap: 10px;'>";
                foreach ($trials as $trial) {
                    $daysLeft = $trial['days_until_expiry'];
                    $color = $daysLeft <= 1 ? '#ef4444' : ($daysLeft <= 3 ? '#f59e0b' : '#fbbf24');
                    echo "
                    <div style='display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f9fafb; border-left: 3px solid {$color}; border-radius: 4px;'>
                        <div>
                            <div style='font-weight: 600;'>" . htmlspecialchars($trial['company_name']) . "</div>
                            <div style='font-size: 12px; color: var(--sa-muted);'>Expires in " . $daysLeft . " day" . ($daysLeft !== 1 ? 's' : '') . "</div>
                        </div>
                        <button class='sa-btn sa-btn-outline' style='font-size: 12px;' onclick=\"window.location.href='view_tenant.php?tenant_id=" . htmlspecialchars($trial['tenant_id']) . "'\">
                            Renew
                        </button>
                    </div>";
                }
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<p style='color: var(--sa-muted);'>Error loading trial status: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>
</div>
<!-- END TRIAL STATUS AWARENESS CARD -->
