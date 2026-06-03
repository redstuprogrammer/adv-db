<?php
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_tier_helper.php';

echo "<h2>All Tenants in Database</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Tenant ID</th><th>Company Name</th><th>subscription_tier</th><th>created_at</th><th>Effective Tier</th><th>Max File Size</th></tr>";

$stmt = $conn->prepare('SELECT tenant_id, company_name, subscription_tier, created_at FROM tenants LIMIT 20');
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $tenantId = $row['tenant_id'];
        $effectiveTier = getTenantEffectiveTier($tenantId, $conn);
        $maxFileSize = getTenantEffectiveMaxFileSize($tenantId, $conn);
        
        $createdAt = $row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : 'N/A';
        $tier = $row['subscription_tier'] ? $row['subscription_tier'] : 'NULL';
        
        echo "<tr>";
        echo "<td>{$row['tenant_id']}</td>";
        echo "<td>{$row['company_name']}</td>";
        echo "<td>$tier</td>";
        echo "<td>$createdAt</td>";
        echo "<td><strong>$effectiveTier</strong></td>";
        echo "<td><strong>${maxFileSize}MB</strong></td>";
        echo "</tr>";
    }
    $stmt->close();
} else {
    echo "Error: " . $conn->error;
}
echo "</table>";
?>
