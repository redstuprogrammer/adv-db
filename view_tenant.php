<?php
require_once __DIR__ . '/includes/connect.php';

// 1. Get the ID from the URL
$tenant_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($tenant_id === 0) {
    die("Invalid Tenant ID.");
}

// 2. Fetch details
$query = "SELECT * FROM tenants WHERE tenant_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $tenant_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$tenant = mysqli_fetch_assoc($result);

if (!$tenant) {
    die("Clinic not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo $tenant['company_name']; ?> | OralSync Details</title>
    </head>
<body>
    <div class="container">
        <header class="sa-main-header">
            <a href="/superadmin/superadmin_dash.php" class="sa-btn sa-btn-outline">← Back to List</a>
            <h1>Clinic Profile: <?php echo $tenant['company_name']; ?></h1>
        </header>

        <div class="sa-section active-section">
            <div class="sa-card">
                <div class="sa-card-header">
                    <div class="sa-card-title">General Information</div>
                </div>
                
                <div class="sa-form-grid" style="padding: 20px;">
                    <div><strong>Owner:</strong> <?php echo $tenant['owner_name']; ?></div>
                    <div><strong>Email:</strong> <?php echo $tenant['contact_email']; ?></div>
                    <div><strong>Phone:</strong> <?php echo $tenant['phone']; ?></div>
                    <div><strong>Subdomain:</strong> <code><?php echo $tenant['subdomain_slug']; ?>.oralsync.com</code></div>
                    <div><strong>Subscription Plan:</strong> <?php echo ucfirst($tenant['subscription_tier']); ?></div>
                    <div><strong>Status:</strong> 
                        <span class="badge-<?php echo strtolower($tenant['status']); ?>">
                            <?php echo $tenant['status']; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="sa-card" style="margin-top: 20px;">
                <div class="sa-card-header">
                    <div class="sa-card-title">Address & Location</div>
                </div>
                <div style="padding: 20px;">
                    <p><?php echo $tenant['address']; ?>, <?php echo $tenant['city']; ?>, <?php echo $tenant['province']; ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
