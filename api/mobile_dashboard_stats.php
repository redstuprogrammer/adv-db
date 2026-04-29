<?php
/**
 * Mobile Dashboard Stats API
 * Returns summary statistics for the mobile app dashboard
 * Gated by subscription tier (Mobile Dashboard feature)
 */

require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/session_utils.php';
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../tenant_tier_helper.php';

$sessionManager = SessionManager::getInstance();
// Require any valid tenant user (Dentist, Admin, Receptionist)
$sessionManager->requireTenantUser();

$tenantId = $sessionManager->getTenantId();

// --- TIER ENFORCEMENT GATE ---
requireMobileAccess($tenantId, $conn);
// -----------------------------

header('Content-Type: application/json');

try {
    $today = date('Y-m-d');
    
    // Fetch some basic stats for the mobile dashboard
    $stats = [
        'success' => true,
        'today_appointments' => 0,
        'pending_payments' => 0,
        'total_patients' => 0
    ];

    // 1. Today's Appointments
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointment WHERE tenant_id = ? AND appointment_date = ?");
    $stmt->bind_param('is', $tenantId, $today);
    $stmt->execute();
    $stats['today_appointments'] = (int)$stmt->get_result()->fetch_assoc()['total'];

    // 2. Pending Payments
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM payment WHERE tenant_id = ? AND status != 'Paid'");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $stats['pending_payments'] = (int)$stmt->get_result()->fetch_assoc()['total'];

    // 3. Total Patients
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM patient WHERE tenant_id = ?");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $stats['total_patients'] = (int)$stmt->get_result()->fetch_assoc()['total'];

    echo json_encode($stats);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
