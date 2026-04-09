<?php
// ============================================================
// FILE TYPE: API ENDPOINT — deploy to server
// PATH on server: /api/get_billing.php
// ============================================================
// GET  → fetch all payments for a patient (deposits + full bills)
// POST → submit / record a payment (full bill)
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../connect.php';

// ─────────────────────────────────────────────
//  GET  →  fetch billings for a patient
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $patient_id = $_GET['patient_id'] ?? '';

    if (empty($patient_id) || !is_numeric($patient_id)) {
        echo json_encode(['success' => false, 'message' => 'A valid patient_id is required.']);
        exit;
    }

    // Join payments → appointment so we can filter by patient and return appointment_date.
    // Returns ALL payment rows (deposits + full bills) — mobile sorts them by type.
    $stmt = $conn->prepare("
        SELECT
            p.payment_id,
            p.tenant_id,
            p.appointment_id,
            p.amount,
            p.mode,
            p.status,
            p.procedures_json,
            p.source,
            p.reference_number,
            p.payment_date,
            p.payment_type,
            a.appointment_date,
            a.procedure_name
        FROM payment p
        JOIN appointment a ON p.appointment_id = a.appointment_id
        WHERE a.patient_id = ?
        ORDER BY p.payment_date DESC
    ");

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $billings = [];
    while ($row = $result->fetch_assoc()) {
        $billings[] = $row;
    }

    echo json_encode([
        'success'  => true,
        'message'  => 'Billings fetched successfully.',
        'billings' => $billings,
    ]);

    $stmt->close();

// ─────────────────────────────────────────────
//  POST  →  submit / record a full payment
// ─────────────────────────────────────────────
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = json_decode(file_get_contents('php://input'), true);

    $tenant_id        = $input['tenant_id']        ?? null;
    $appointment_id   = $input['appointment_id']   ?? null;
    $amount           = $input['amount']           ?? null;
    $mode             = $input['mode']             ?? 'Cash';
    $status           = $input['status']           ?? 'Paid';
    $procedures_json  = $input['procedures_json']  ?? '[]';
    $reference_number = $input['reference_number'] ?? null;

    if (!$tenant_id || !$appointment_id || $amount === null || !$reference_number) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    // Check if a FULL payment already exists for this appointment
    $check = $conn->prepare("
        SELECT payment_id FROM payment
        WHERE appointment_id = ?
          AND payment_type = 'full'
        LIMIT 1
    ");
    $check->bind_param("i", $appointment_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        // UPDATE the existing full payment row
        $check->bind_result($existing_payment_id);
        $check->fetch();
        $check->close();

        $upd = $conn->prepare("
            UPDATE payment
            SET amount = ?, mode = ?, status = ?, procedures_json = ?,
                reference_number = ?, source = 'mobile', payment_date = CURRENT_TIMESTAMP
            WHERE payment_id = ?
        ");
        $upd->bind_param("dssssi", $amount, $mode, $status, $procedures_json, $reference_number, $existing_payment_id);
        $upd->execute();
        $upd->close();

        echo json_encode([
            'success'          => true,
            'message'          => 'Payment updated successfully.',
            'payment_id'       => $existing_payment_id,
            'reference_number' => $reference_number,
        ]);

    } else {
        $check->close();

        // INSERT new full payment row (payment_type = 'full' is the default)
        $ins = $conn->prepare("
            INSERT INTO payment
                (tenant_id, appointment_id, amount, mode, status, procedures_json, source, reference_number, payment_type)
            VALUES (?, ?, ?, ?, ?, ?, 'mobile', ?, 'full')
        ");
        $ins->bind_param("iidssss", $tenant_id, $appointment_id, $amount, $mode, $status, $procedures_json, $reference_number);
        $ins->execute();

        $new_id = $conn->insert_id;
        $ins->close();

        echo json_encode([
            'success'          => true,
            'message'          => 'Payment recorded successfully.',
            'payment_id'       => $new_id,
            'reference_number' => $reference_number,
        ]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Only GET and POST requests are allowed.']);
}

$conn->close();
?>