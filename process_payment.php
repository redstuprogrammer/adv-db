<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

// Role Check Implementation - Ensure user is a Receptionist
if (!isset($_SESSION['role'])) {
    header("Location: tenant_login.php");
    exit();
}

if ($_SESSION['role'] !== 'Receptionist') {
    header("Location: tenant_login.php");
    exit();
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function parse_procedures_json(string $json): array {
    $decoded = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return [];
    }
    return $decoded;
}

function compute_amount_from_procedures(array $procedures): float {
    $total = 0.0;
    foreach ($procedures as $procedure) {
        if (isset($procedure['price']) && is_numeric($procedure['price'])) {
            $total += (float)$procedure['price'];
        }
    }
    return round($total, 2);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: receptionist_billing.php");
    exit();
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

$tenantId = getCurrentTenantId();

// Get POST data
$patient_id = (int)($_POST['patient_id'] ?? 0);
$appointment_id = (int)($_POST['appointment_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$mode = trim($_POST['mode'] ?? '');
$status = trim($_POST['status'] ?? 'Pending');
$procedures_json = trim($_POST['procedures_json'] ?? '');
$payment_id = (int)($_POST['payment_id'] ?? 0); // For future editing

// Validation
$errors = [];
if ($patient_id <= 0) $errors[] = "Invalid patient selected";
if ($appointment_id <= 0) $errors[] = "Invalid appointment selected";
if (empty($procedures_json)) $errors[] = "No procedures selected";

$procedures = parse_procedures_json($procedures_json);
if (empty($procedures)) {
    $errors[] = "Invalid procedures data";
}

$totalProcedureAmount = compute_amount_from_procedures($procedures);
if ($totalProcedureAmount <= 0) {
    $errors[] = "Amount must be greater than 0";
} else {
    if ($amount <= 0) {
        $amount = $totalProcedureAmount;
    }

    $depositApplied = 0.0;
    if ($appointment_id > 0) {
        $apptStmt = $conn->prepare("SELECT requested_by FROM appointment WHERE appointment_id = ? AND tenant_id = ? LIMIT 1");
        if ($apptStmt) {
            $apptStmt->bind_param("ii", $appointment_id, $tenantId);
            $apptStmt->execute();
            $appt = $apptStmt->get_result()->fetch_assoc();
            $apptStmt->close();

            if ($appt && strtolower(trim($appt['requested_by'] ?? '')) === 'patient') {
                $depositStmt = $conn->prepare(
                    "SELECT IFNULL(SUM(amount), 0) AS deposit_sum
                     FROM payment
                     WHERE appointment_id = ? AND tenant_id = ? AND payment_type = 'deposit'"
                );
                if ($depositStmt) {
                    $depositStmt->bind_param("ii", $appointment_id, $tenantId);
                    $depositStmt->execute();
                    $depositRow = $depositStmt->get_result()->fetch_assoc();
                    $depositApplied = isset($depositRow['deposit_sum']) ? (float)$depositRow['deposit_sum'] : 0.0;
                    $depositStmt->close();
                }
            }
        }
    }

    if ($depositApplied > 0) {
        $amount = round(max($amount - $depositApplied, 0), 2);
        if ($amount === 0.0 && strtolower($status) !== 'paid') {
            $status = 'Paid';
        }
    }
}

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    header("Location: receptionist_billing.php?tenant=" . rawurlencode($tenantSlug));
    exit();
}

// Generate reference number for tracking
$reference_number = 'WEB-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

// Concatenate procedure names for appointment.procedure_name
$procedure_names = array_column($procedures, 'name');
$procedure_name_concat = implode(', ', $procedure_names);

// Begin transaction
$conn->begin_transaction();

try {
    // Insert payment record
    $insert_sql = "INSERT INTO payment (
        tenant_id, appointment_id, amount, mode, status,
        procedures_json, source, reference_number, payment_date
    ) VALUES (?, ?, ?, ?, ?, ?, 'web', ?, NOW())";

    $stmt = mysqli_prepare($conn, $insert_sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare payment insert: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "iidssss", $tenantId, $appointment_id, $amount, $mode, $status, $procedures_json, $reference_number);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to insert payment: " . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

    // If payment status is 'Paid', update appointment status to 'Completed'
    if (strtolower($status) === 'paid') {
        $update_status_sql = "UPDATE appointment SET status = 'Completed' WHERE appointment_id = ? AND tenant_id = ?";
        $stmt3 = mysqli_prepare($conn, $update_status_sql);
        if (!$stmt3) {
            throw new Exception("Failed to prepare status update: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt3, "ii", $appointment_id, $tenantId);

        if (!mysqli_stmt_execute($stmt3)) {
            throw new Exception("Failed to update appointment status: " . mysqli_stmt_error($stmt3));
        }

        mysqli_stmt_close($stmt3);
    }

    // Commit transaction
    $conn->commit();

    $successMessage = "Payment processed successfully! Reference: " . $reference_number;
    if (!empty($depositApplied) && $depositApplied > 0) {
        $successMessage = "Deposit of ₱" . number_format($depositApplied, 2) . " applied. " . $successMessage;
    }
    $_SESSION['success'] = $successMessage;
    header("Location: receptionist_billing.php?tenant=" . rawurlencode($tenantSlug));
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    error_log("Payment processing error: " . $e->getMessage());
    $_SESSION['errors'] = ["Failed to process payment: " . $e->getMessage()];
    header("Location: receptionist_billing.php?tenant=" . rawurlencode($tenantSlug));
    exit();
}
?>