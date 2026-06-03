<?php
/**
 * Migration: Set ToothFairy subscription to expire soon
 *
 * Purpose: Update the tenants table for the ToothFairy tenant so its
 * subscription end date falls within the next 7 days and triggers the
 * expiry warning logic in subscription.php.
 *
 * Run once via CLI: php db_migrations/migrate_tenants_toothfairy_expiry_test.php
 *
 * Notes:
 * - This migration updates subscription_start_date and subscription_duration.
 * - It does not change the subscription tier or tenant status.
 */

define('ROOT_PATH', realpath(__DIR__ . '/../') . '/');
require_once ROOT_PATH . 'includes/connect.php'; // provides $conn (mysqli)

$tenantIdentifiers = [
    'company_name' => 'ToothFairy',
    'username' => 'toothfairy',
    'subdomain_slug' => 'toothfairy-73d1'
];

$stmt = $conn->prepare(
    'SELECT tenant_id, company_name, username, subdomain_slug, status, subscription_tier, subscription_start_date, subscription_duration
     FROM tenants
     WHERE company_name = ? OR username = ? OR subdomain_slug = ?
     LIMIT 1'
);

if (!$stmt) {
    exit("ERROR: Could not prepare tenant lookup statement: {$conn->error}\n");
}

$stmt->bind_param(
    'sss',
    $tenantIdentifiers['company_name'],
    $tenantIdentifiers['username'],
    $tenantIdentifiers['subdomain_slug']
);
$stmt->execute();
$result = $stmt->get_result();
$tenant = $result->fetch_assoc();
$stmt->close();

if (!$tenant) {
    exit("ERROR: ToothFairy tenant row not found in tenants table.\n");
}

$tenantId = (int)$tenant['tenant_id'];
$currentStart = $tenant['subscription_start_date'];
$currentDuration = (int)$tenant['subscription_duration'];
$currentEnd = $currentStart ? date('Y-m-d H:i:s', strtotime('+' . $currentDuration . ' months', strtotime($currentStart))) : 'N/A';

echo "Found tenant: {$tenant['company_name']} (ID: {$tenantId}, username: {$tenant['username']}, slug: {$tenant['subdomain_slug']})\n";
echo "Current subscription_start_date: {$currentStart}\n";
echo "Current subscription_duration: {$currentDuration}\n";
echo "Current calculated end date: {$currentEnd}\n";

$daysAhead = 5;
$durationMonths = 1;
$targetEndDate = date('Y-m-d 00:00:00', strtotime("+{$daysAhead} days"));
$targetStartDate = date('Y-m-d 00:00:00', strtotime("-{$durationMonths} month", strtotime($targetEndDate)));

$newEndDate = date('Y-m-d H:i:s', strtotime('+' . $durationMonths . ' months', strtotime($targetStartDate)));

if ($currentStart === $targetStartDate && $currentDuration === $durationMonths) {
    echo "✔ Tenant already has the target expiry test values. End date remains {$newEndDate}.\n";
    exit(0);
}

$updateStmt = $conn->prepare(
    'UPDATE tenants
     SET subscription_start_date = ?, subscription_duration = ?
     WHERE tenant_id = ?'
);

if (!$updateStmt) {
    exit("ERROR: Could not prepare update statement: {$conn->error}\n");
}

$updateStmt->bind_param('sii', $targetStartDate, $durationMonths, $tenantId);

if ($updateStmt->execute()) {
    echo "✔ Updated ToothFairy tenant subscription test values.\n";
    echo "New subscription_start_date: {$targetStartDate}\n";
    echo "New subscription_duration: {$durationMonths}\n";
    echo "New calculated end date: {$newEndDate}\n";
    echo "This should trigger expiry warning within {$daysAhead} days.\n";
} else {
    exit("ERROR: Failed to update ToothFairy tenant: {$updateStmt->error}\n");
}

$updateStmt->close();
$conn->close();
