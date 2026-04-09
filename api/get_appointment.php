<?php
// ============================================================
// FILE TYPE: API ENDPOINT — deploy to server
// PATH on server: /api/get_appointment.php
// ============================================================
// GET params:
//   patient_id (int, required)
//
// LAZY VOID LOGIC (runs on every fetch):
//   - pending_payment  + appointment datetime has passed → voided
//   - pending          + appointment datetime has passed → voided
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../connect.php';

// ── Guard: DB connection must exist ─────────────────────────────────────────
if (!isset($conn) || !$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . ($conn->connect_error ?? 'null connection'),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Only GET requests allowed']);
    exit;
}

$patient_id = $_GET['patient_id'] ?? '';

if (empty($patient_id) || !is_numeric($patient_id)) {
    echo json_encode(['success' => false, 'message' => 'A valid patient_id is required']);
    exit;
}

$patient_id = (int) $patient_id;

// ── Lazy void: mark expired pending/pending_payment appointments as voided ──
// Parameterized to avoid injection and surface errors cleanly.
$void_stmt = $conn->prepare("
    UPDATE appointment
    SET status = 'voided'
    WHERE patient_id = ?
      AND status IN ('pending', 'pending_payment')
      AND TIMESTAMP(appointment_date, COALESCE(appointment_time, '23:59:59')) < NOW()
");

if (!$void_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed (void): ' . $conn->error]);
    exit;
}

$void_stmt->bind_param("i", $patient_id);
$void_stmt->execute();
$void_stmt->close();

// ── Fetch all appointments for this patient ──────────────────────────────────
$stmt = $conn->prepare("
    SELECT
        a.appointment_id,
        a.appointment_date  AS date,
        a.appointment_time  AS time,
        a.procedure_name    AS procedure,
        a.status,
        a.notes,
        a.tenant_id,
        CONCAT(d.first_name, ' ', d.last_name) AS doctor,
        dep.payment_id      AS deposit_payment_id,
        dep.amount          AS deposit_amount,
        dep.status          AS deposit_status,
        dep.reference_number AS deposit_reference,
        dep.payment_type    AS deposit_payment_type
    FROM appointment a
    JOIN tenants t       ON a.tenant_id  = t.tenant_id
    LEFT JOIN dentist d  ON a.dentist_id = d.dentist_id
    LEFT JOIN payment dep
        ON  dep.appointment_id = a.appointment_id
        AND dep.payment_type   = 'deposit'
    WHERE a.patient_id = ?
      AND t.status = 'active'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed (select): ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}

echo json_encode([
    'success'      => true,
    'message'      => 'Appointments fetched successfully',
    'appointments' => $appointments,
]);

$stmt->close();
$conn->close();
?>