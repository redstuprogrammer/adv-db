<?php
// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../connect.php';

// ─────────────────────────────────────────────
//  GET  →  fetch billings for a patient
//  Query: payments JOIN appointment (to get appointment_date & patient filter)
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $patient_id = $_GET['patient_id'] ?? '';

    if (empty($patient_id) || !is_numeric($patient_id)) {
        echo json_encode(['success' => false, 'message' => 'A valid patient_id is required.']);
        exit;
    }

    // Join payments → appointment so we can:
    //   • filter by patient_id (lives on appointment)
    //   • return appointment_date alongside the payment record
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
            a.appointment_date
        FROM payments p
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
//  POST  →  submit / record a payment
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

    // Check if a payment already exists for this appointment
    $check = $conn->prepare("SELECT payment_id FROM payments WHERE appointment_id = ? LIMIT 1");
    $check->bind_param("i", $appointment_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        // UPDATE the existing payment row
        $check->bind_result($existing_payment_id);
        $check->fetch();
        $check->close();

        $upd = $conn->prepare("
            UPDATE payments
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

        // INSERT a new payment row
        $ins = $conn->prepare("
            INSERT INTO payments
                (tenant_id, appointment_id, amount, mode, status, procedures_json, source, reference_number)
            VALUES (?, ?, ?, ?, ?, ?, 'mobile', ?)
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
