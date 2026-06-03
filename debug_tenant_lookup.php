<?php
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_tier_helper.php';

$names = ['Backrooms', 'SwissWhite'];

echo "<h2>Lookup tenants: Backrooms & SwissWhite</h2>";
echo "<table border='1' cellpadding='8'><tr><th>Tenant ID</th><th>Company Name</th><th>subscription_tier</th><th>created_at</th><th>Effective Tier</th><th>Max File Size</th></tr>";

foreach ($names as $n) {
    $stmt = $conn->prepare('SELECT tenant_id, company_name, subscription_tier, created_at FROM tenants WHERE company_name = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $n);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if ($row) {
            $tenantId = $row['tenant_id'];
            $effectiveTier = getTenantEffectiveTier($tenantId, $conn);
            $maxFileSize = getTenantEffectiveMaxFileSize($tenantId, $conn);
            $createdAt = $row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : 'N/A';
            $tier = $row['subscription_tier'] ? $row['subscription_tier'] : 'NULL';
            echo "<tr><td>{$row['tenant_id']}</td><td>{$row['company_name']}</td><td>$tier</td><td>$createdAt</td><td><strong>$effectiveTier</strong></td><td><strong>${maxFileSize}MB</strong></td></tr>";
        } else {
            echo "<tr><td colspan='6'>No tenant found with company_name = '$n'</td></tr>";
        }
    } else {
        echo "<tr><td colspan='6'>DB error: " . $conn->error . "</td></tr>";
    }
}

echo "</table>";
?>