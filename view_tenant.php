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

// Fetch tenant documents
$docs_result = mysqli_query($conn, "SELECT * FROM tenant_documents WHERE tenant_id = $tenant_id ORDER BY id DESC");
$documents = [];
while ($doc = mysqli_fetch_assoc($docs_result)) {
    $documents[] = $doc;
}
?>
</xai:function_call name="edit_file">

<xai:function_call name="edit_file">
<parameter name="path">c:/xampp/htdocs/adv db/view_tenant.php

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

            <?php if (!empty($tenant['homepage_url'])): ?>
            <div class="sa-card" style="margin-top: 20px;">
                <div class="sa-card-header">
                    <div class="sa-card-title">Clinic Homepage</div>
                </div>
                <div style="padding: 20px;">
                    <a href="<?php echo htmlspecialchars($tenant['homepage_url']); ?>" target="_blank" class="sa-btn sa-btn-success" style="font-size: 1rem;">
                        🌐 Visit <?php echo htmlspecialchars($tenant['company_name']); ?> Website
                    </a>
                    <div style="margin-top: 12px; font-family: monospace; color: #64748b; word-break: break-all;">
                        <?php echo htmlspecialchars($tenant['homepage_url']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="sa-card" style="margin-top: 20px;">
                <div class="sa-card-header">
                    <div class="sa-card-title">Clinic Documents (<?php echo count($documents); ?>)</div>
                </div>
                <div style="padding: 20px;">
                    <?php if (empty($documents)): ?>
                        <div style="text-align: center; color: #64748b; padding: 40px;">
                            <div style="font-size: 4rem; margin-bottom: 12px;">📁</div>
                            <div style="font-weight: 500; margin-bottom: 4px;">No documents uploaded</div>
                            <div style="font-size: 0.9rem;">Documents are automatically added during clinic registration</div>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px;">
                            <?php foreach ($documents as $doc): ?>
                                <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; background: #f8fafc; transition: all 0.2s;">
                                    <div style="font-weight: 600; color: #0d3b66; margin-bottom: 8px; font-size: 0.95rem; word-break: break-word; min-height: 2.2em; display: flex; align-items: center;">
                                        <?php echo htmlspecialchars($doc['document_name']); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 12px;">
                                        <?php echo number_format($doc['file_size'] / 1024 / 1024, 1); ?> MB
                                    </div>
                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" 
                                       class="sa-btn sa-btn-success" 
                                       style="width: 100%; justify-content: center; font-size: 0.85rem;">
                                        👁️ View Document
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
