<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

// Parse JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

// Required identifiers
$patient_id = isset($data['patient_id']) ? (int) $data['patient_id'] : 0;
$tenant_id  = isset($data['tenant_id'])  ? (int) $data['tenant_id']  : 0;

if ($patient_id <= 0 || $tenant_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid patient_id and tenant_id are required']);
    exit;
}

// Editable fields — only what the patient is allowed to change
$first_name      = trim($data['first_name']      ?? '');
$last_name       = trim($data['last_name']       ?? '');
$contact_number  = trim($data['contact_number']  ?? '');
$address         = trim($data['address']         ?? '');
$gender          = trim($data['gender']          ?? '');
$birthdate       = trim($data['birthdate']       ?? '');
$occupation      = trim($data['occupation']      ?? '');
$allergies       = trim($data['allergies']       ?? '');
$medical_history = trim($data['medical_history'] ?? '');

// Basic validation
if (empty($first_name) || empty($last_name)) {
    echo json_encode(['success' => false, 'message' => 'First name and last name are required']);
    exit;
}

if (empty($contact_number)) {
    echo json_encode(['success' => false, 'message' => 'Contact number is required']);
    exit;
}

// Validate birthdate format if provided
if (!empty($birthdate)) {
    $d = DateTime::createFromFormat('Y-m-d', $birthdate);
    if (!$d || $d->format('Y-m-d') !== $birthdate) {
        echo json_encode(['success' => false, 'message' => 'Invalid birthdate format. Use YYYY-MM-DD']);
        exit;
    }
    // Sanity check — not in the future
    if ($d > new DateTime()) {
        echo json_encode(['success' => false, 'message' => 'Birthdate cannot be in the future']);
        exit;
    }
}

// Confirm this patient belongs to this tenant before updating
$check = $conn->prepare("SELECT patient_id FROM patient WHERE patient_id = ? AND tenant_id = ?");
$check->bind_param("ii", $patient_id, $tenant_id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    $check->close();
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}
$check->close();

// Update
$birthdateValue = !empty($birthdate) ? $birthdate : null;

$stmt = $conn->prepare("
    UPDATE patient
    SET
        first_name      = ?,
        last_name       = ?,
        contact_number  = ?,
        address         = ?,
        gender          = ?,
        birthdate       = ?,
        occupation      = ?,
        allergies       = ?,
        medical_history = ?
    WHERE patient_id = ? AND tenant_id = ?
");

$stmt->bind_param(
    "sssssssssii",
    $first_name,
    $last_name,
    $contact_number,
    $address,
    $gender,
    $birthdateValue,
    $occupation,
    $allergies,
    $medical_history,
    $patient_id,
    $tenant_id
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'data'    => [
            'patient_id'     => $patient_id,
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'contact_number' => $contact_number,
            'address'        => $address,
            'gender'         => $gender,
            'birthdate'      => $birthdateValue,
            'occupation'     => $occupation,
            'allergies'      => $allergies,
            'medical_history'=> $medical_history,
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update profile. Please try again.'
    ]);
}

$stmt->close();
$conn->close();
?>