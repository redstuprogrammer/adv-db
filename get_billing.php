<?php
define('ROOT_PATH', __DIR__ . '/');
// 1. Headers for JSON and Cross-Origin requests (CORS)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight 'OPTIONS' requests from mobile/web browsers
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Database Connection
// Ensure connect.php handles the Azure SSL certificate
require_once ROOT_PATH . 'includes/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // 3. Capture Inputs
    $patient_id = $_GET['patient_id'] ?? '';

    // 4. Validation
    if (empty($patient_id) || !is_numeric($patient_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'A valid patient_id is required',
            'debug' => [
                'method' => $_SERVER['REQUEST_METHOD'],
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none'
            ]
        ]);
        exit;
    }

    // 5. Database Query
    // Get billing info (payments) for the patient's appointments
    $stmt = $conn->prepare("
        SELECT
            p.payment_id,
            p.amount,
            p.mode,
            p.status,
            p.payment_date,
            p.procedures_json,
            p.reference_number,
            a.appointment_id,
            a.appointment_date,
            a.procedure_name,
            t.name AS clinic_name
        FROM payment p
        JOIN appointment a ON p.appointment_id = a.appointment_id
        JOIN tenants t ON p.tenant_id = t.tenant_id
        WHERE a.patient_id = ?
          AND t.status = 'active'
        ORDER BY p.payment_date DESC
    ");

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $billings = [];
    while ($row = $result->fetch_assoc()) {
        // Parse procedures_json if it exists
        if (!empty($row['procedures_json'])) {
            $row['procedures'] = json_decode($row['procedures_json'], true);
        } else {
            $row['procedures'] = null;
        }
        $billings[] = $row;
    }

    echo json_encode([
        'success'   => true,
        'message'   => 'Billing info fetched successfully',
        'billings'  => $billings
    ]);

    $stmt->close();

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Handle mobile payment submissions
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }

    // Required fields for mobile payment
    $tenant_id = (int)($input['tenant_id'] ?? 0);
    $appointment_id = (int)($input['appointment_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $mode = trim($input['mode'] ?? '');
    $status = trim($input['status'] ?? '');
    $procedures_json = trim($input['procedures_json'] ?? '');
    $reference_number = trim($input['reference_number'] ?? '');

    // Validation
    $errors = [];
    if ($tenant_id <= 0) $errors[] = "Invalid tenant";
    if ($appointment_id <= 0) $errors[] = "Invalid appointment";
    if ($amount <= 0) $errors[] = "Amount must be greater than 0";
    if (empty($mode)) $errors[] = "Payment mode required";
    if (empty($status)) $errors[] = "Payment status required";
    if (empty($procedures_json)) $errors[] = "Procedures data required";
    if (empty($reference_number)) $errors[] = "Reference number required";

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => 'Validation errors', 'errors' => $errors]);
        exit;
    }

    // Parse and validate procedures
    $procedures = json_decode($procedures_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($procedures)) {
        echo json_encode(['success' => false, 'message' => 'Invalid procedures data']);
        exit;
    }

    // Concatenate procedure names
    $procedure_names = array_column($procedures, 'name');
    $procedure_name_concat = implode(', ', $procedure_names);

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Insert payment record
        $insert_sql = "INSERT INTO payment (
            tenant_id, appointment_id, amount, mode, status,
            procedures_json, source, reference_number, payment_date
        ) VALUES (?, ?, ?, ?, ?, ?, 'mobile', ?, NOW())";

        $stmt = mysqli_prepare($conn, $insert_sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare payment insert: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, "iidssss", $tenant_id, $appointment_id, $amount, $mode, $status, $procedures_json, $reference_number);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to insert payment: " . mysqli_stmt_error($stmt));
        }

        $payment_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // Update appointment.procedure_name
        $update_appt_sql = "UPDATE appointment SET procedure_name = ? WHERE appointment_id = ? AND tenant_id = ?";
        $stmt2 = mysqli_prepare($conn, $update_appt_sql);
        if (!$stmt2) {
            throw new Exception("Failed to prepare appointment update: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt2, "sii", $procedure_name_concat, $appointment_id, $tenant_id);

        if (!mysqli_stmt_execute($stmt2)) {
            throw new Exception("Failed to update appointment: " . mysqli_stmt_error($stmt2));
        }

        mysqli_stmt_close($stmt2);

        // If payment status is 'Paid', update appointment status to 'Completed'
        if (strtolower($status) === 'paid') {
            $update_status_sql = "UPDATE appointment SET status = 'Completed' WHERE appointment_id = ? AND tenant_id = ?";
            $stmt3 = mysqli_prepare($conn, $update_status_sql);
            if (!$stmt3) {
                throw new Exception("Failed to prepare status update: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt3, "ii", $appointment_id, $tenant_id);

            if (!mysqli_stmt_execute($stmt3)) {
                throw new Exception("Failed to update appointment status: " . mysqli_stmt_error($stmt3));
            }

            mysqli_stmt_close($stmt3);
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Mobile payment processed successfully',
            'payment_id' => $payment_id,
            'reference_number' => $reference_number
        ]);

    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();

        error_log("Mobile payment processing error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to process payment: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>