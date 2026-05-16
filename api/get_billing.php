<?php
// ============================================================
// FILE TYPE: API ENDPOINT — deploy to server
// PATH on server: /api/get_billing.php
// ============================================================
// GET  ?patient_id=X  → fetch all billing records for a patient
// POST               → record a direct CASH payment against a bill
//
// Works with the NEW `billing` table schema:
//   billing_id, tenant_id, appointment_id, patient_id,
//   service_id, total_amount, amount_paid, payment_status,
//   billing_date, paymongo_session_id, reference_number, mode
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../connect.php';

// ─────────────────────────────────────────────────────────────
//  GET  →  fetch billing records for a patient
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $patient_id = $_GET['patient_id'] ?? '';

    if (empty($patient_id) || !is_numeric($patient_id)) {
        echo json_encode(['success' => false, 'message' => 'A valid patient_id is required.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT
            b.billing_id,
            b.tenant_id,
            b.appointment_id,
            b.patient_id,
            b.service_id,
            b.total_amount,
            b.amount_paid,
            b.payment_status,
            b.billing_date,
            b.paymongo_session_id,
            b.reference_number,
            b.mode,
            a.appointment_date,
            a.procedure_name,
            s.service_name
        FROM billing b
        JOIN appointment a ON b.appointment_id = a.appointment_id
        LEFT JOIN service s ON b.service_id = s.service_id
        WHERE b.patient_id = ?
        ORDER BY b.billing_date DESC
    ");

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $billings = [];
    while ($row = $result->fetch_assoc()) {
        $billings[] = $row;
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success'  => true,
        'message'  => 'Billings fetched successfully.',
        'billings' => $billings,
    ]);

// ─────────────────────────────────────────────────────────────
//  POST  →  record a direct CASH payment
// ─────────────────────────────────────────────────────────────
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = json_decode(file_get_contents('php://input'), true);

    $billing_id       = $input['billing_id']       ?? null;
    $amount_paid      = $input['amount_paid']      ?? null;
    $mode             = ucfirst(strtolower($input['mode'] ?? 'Cash'));
    $reference_number = $input['reference_number'] ?? null;

    if (!$billing_id || $amount_paid === null || !$reference_number) {
        echo json_encode(['success' => false, 'message' => 'billing_id, amount_paid, and reference_number are required.']);
        exit;
    }

    // Fetch current billing row
    $chk = $conn->prepare("SELECT total_amount, amount_paid FROM billing WHERE billing_id = ? LIMIT 1");
    $chk->bind_param("i", $billing_id);
    $chk->execute();
    $bill = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$bill) {
        echo json_encode(['success' => false, 'message' => 'Billing record not found.']);
        $conn->close(); exit;
    }

    // Compute new paid total and derive status
    $new_amount_paid = (float) $bill['amount_paid'] + (float) $amount_paid;
    $total           = (float) $bill['total_amount'];

    if ($new_amount_paid >= $total) {
        $new_status      = 'paid';
        $new_amount_paid = $total;
    } elseif ($new_amount_paid > 0) {
        $new_status = 'partial';
    } else {
        $new_status = 'unpaid';
    }

    $upd = $conn->prepare("
        UPDATE billing
        SET amount_paid = ?, payment_status = ?, mode = ?, reference_number = ?
        WHERE billing_id = ?
    ");
    $upd->bind_param("dsssi", $new_amount_paid, $new_status, $mode, $reference_number, $billing_id);

    if (!$upd->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to update billing: ' . $upd->error]);
        $upd->close(); $conn->close(); exit;
    }
    $upd->close();
    $conn->close();

    echo json_encode([
        'success'          => true,
        'message'          => 'Cash payment recorded successfully.',
        'billing_id'       => $billing_id,
        'new_amount_paid'  => $new_amount_paid,
        'payment_status'   => $new_status,
        'reference_number' => $reference_number,
    ]);

} else {
    echo json_encode(['success' => false, 'message' => 'Only GET and POST requests are allowed.']);
}
?>