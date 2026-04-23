<?php
include "db.php";

// Function to hash passwords
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Clear existing sample data first (be careful with this in production!)
echo "<h2>Clearing existing sample data...</h2>";
$conn->query("DELETE FROM payment WHERE payment_id > 0");
$conn->query("DELETE FROM treatment_record WHERE treatment_id > 0");
$conn->query("DELETE FROM clinical_notes WHERE note_id > 0");
$conn->query("DELETE FROM dental_chart WHERE chart_id > 0");
$conn->query("DELETE FROM appointment WHERE appointment_id > 0");
$conn->query("DELETE FROM patient WHERE patient_id > 0");
$conn->query("DELETE FROM dentist_schedule WHERE schedule_id > 0");
$conn->query("DELETE FROM staff_details WHERE staff_id > 0");
$conn->query("DELETE FROM users WHERE user_id > 5"); // Keep original users
$conn->query("DELETE FROM dentist WHERE dentist_id > 1"); // Keep default dentist

echo "Cleared existing sample data.<br><br>";

echo "<h1>Adding Sample Data to OralSync Database</h1>";

// 1. ADD MORE USERS
echo "<h2>Adding Users...</h2>";
$users = [
    ['username' => 'dr_smith', 'email' => 'dr.smith@oralsync.com', 'password' => hashPassword('password123'), 'role' => 'Dentist'],
    ['username' => 'dr_jones', 'email' => 'dr.jones@oralsync.com', 'password' => hashPassword('password123'), 'role' => 'Dentist'],
    ['username' => 'receptionist1', 'email' => 'receptionist@oralsync.com', 'password' => hashPassword('password123'), 'role' => 'Receptionist'],
    ['username' => 'admin_test', 'email' => 'admin@oralsync.com', 'password' => hashPassword('password123'), 'role' => 'Admin'],
    ['username' => 'patient_user', 'email' => 'patient@oralsync.com', 'password' => hashPassword('password123'), 'role' => 'Patient']
];

foreach ($users as $user) {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $user['username'], $user['email'], $user['password'], $user['role']);
    $stmt->execute();
    echo "Added user: {$user['username']} ({$user['role']})<br>";
}

// 2. ADD DENTIST SCHEDULES (using existing dentists)
echo "<h2>Adding Dentist Schedules...</h2>";
$schedules = [
    ['dentist_id' => 1, 'day_of_week' => 'Monday', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
    ['dentist_id' => 1, 'day_of_week' => 'Tuesday', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
    ['dentist_id' => 1, 'day_of_week' => 'Wednesday', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
    ['dentist_id' => 1, 'day_of_week' => 'Thursday', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
    ['dentist_id' => 1, 'day_of_week' => 'Friday', 'start_time' => '09:00:00', 'end_time' => '17:00:00']
];

foreach ($schedules as $schedule) {
    $stmt = $conn->prepare("INSERT INTO dentist_schedule (dentist_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $schedule['dentist_id'], $schedule['day_of_week'], $schedule['start_time'], $schedule['end_time']);
    $stmt->execute();
}

// 4. ADD PATIENTS
echo "<h2>Adding Patients...</h2>";
$patients = [
    ['first_name' => 'John', 'last_name' => 'Doe', 'contact_number' => '09123456789', 'email' => 'john.doe@email.com', 'address' => '123 Main St, Manila', 'birthdate' => '1985-05-15', 'gender' => 'Male', 'occupation' => 'Teacher', 'medical_history' => 'No major issues', 'allergies' => 'None'],
    ['first_name' => 'Jane', 'last_name' => 'Smith', 'contact_number' => '09123456790', 'email' => 'jane.smith@email.com', 'address' => '456 Oak Ave, Quezon City', 'birthdate' => '1990-08-22', 'gender' => 'Female', 'occupation' => 'Engineer', 'medical_history' => 'Hypertension', 'allergies' => 'Penicillin'],
    ['first_name' => 'Michael', 'last_name' => 'Johnson', 'contact_number' => '09123456791', 'email' => 'michael.j@email.com', 'address' => '789 Pine St, Makati', 'birthdate' => '1978-12-03', 'gender' => 'Male', 'occupation' => 'Businessman', 'medical_history' => 'Diabetes', 'allergies' => 'None'],
    ['first_name' => 'Sarah', 'last_name' => 'Williams', 'contact_number' => '09123456792', 'email' => 'sarah.w@email.com', 'address' => '321 Elm St, Pasig', 'birthdate' => '1995-03-18', 'gender' => 'Female', 'occupation' => 'Student', 'medical_history' => 'Asthma', 'allergies' => 'Dust'],
    ['first_name' => 'David', 'last_name' => 'Brown', 'contact_number' => '09123456793', 'email' => 'david.b@email.com', 'address' => '654 Maple Ave, Taguig', 'birthdate' => '1982-11-30', 'gender' => 'Male', 'occupation' => 'Accountant', 'medical_history' => 'None', 'allergies' => 'Shellfish'],
    ['first_name' => 'Emily', 'last_name' => 'Davis', 'contact_number' => '09123456794', 'email' => 'emily.d@email.com', 'address' => '987 Cedar St, BGC', 'birthdate' => '2000-07-12', 'gender' => 'Female', 'occupation' => 'Designer', 'medical_history' => 'None', 'allergies' => 'None'],
    ['first_name' => 'Robert', 'last_name' => 'Miller', 'contact_number' => '09123456795', 'email' => 'robert.m@email.com', 'address' => '147 Birch St, Alabang', 'birthdate' => '1975-09-25', 'gender' => 'Male', 'occupation' => 'Doctor', 'medical_history' => 'Heart condition', 'allergies' => 'None'],
    ['first_name' => 'Lisa', 'last_name' => 'Garcia', 'contact_number' => '09123456796', 'email' => 'lisa.g@email.com', 'address' => '258 Spruce Ave, Paranaque', 'birthdate' => '1988-04-08', 'gender' => 'Female', 'occupation' => 'Nurse', 'medical_history' => 'None', 'allergies' => 'Latex']
];

foreach ($patients as $patient) {
    $stmt = $conn->prepare("INSERT INTO patient (first_name, last_name, contact_number, email, address, birthdate, gender, occupation, medical_history, allergies) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssss", $patient['first_name'], $patient['last_name'], $patient['contact_number'], $patient['email'], $patient['address'], $patient['birthdate'], $patient['gender'], $patient['occupation'], $patient['medical_history'], $patient['allergies']);
    $stmt->execute();
    echo "Added patient: {$patient['first_name']} {$patient['last_name']}<br>";
}

// 4. ADD APPOINTMENTS
echo "<h2>Adding Appointments...</h2>";

// Get the actual patient IDs that were just added
$patient_result = $conn->query("SELECT patient_id, first_name, last_name FROM patient ORDER BY patient_id");
$patient_ids = [];
while($row = $patient_result->fetch_assoc()) {
    $patient_ids[] = $row['patient_id'];
}

$appointments = [
    ['patient_id' => $patient_ids[0], 'dentist_id' => 1, 'appointment_date' => '2026-04-05', 'appointment_time' => '10:00:00', 'service_id' => 1, 'status' => 'Completed', 'notes' => 'Regular check-up'],
    ['patient_id' => $patient_ids[1], 'dentist_id' => 1, 'appointment_date' => '2026-04-06', 'appointment_time' => '14:00:00', 'service_id' => 2, 'status' => 'Completed', 'notes' => 'Deep cleaning'],
    ['patient_id' => $patient_ids[2], 'dentist_id' => 1, 'appointment_date' => '2026-04-07', 'appointment_time' => '11:00:00', 'service_id' => 3, 'status' => 'Scheduled', 'notes' => 'Root canal treatment'],
    ['patient_id' => $patient_ids[3], 'dentist_id' => 1, 'appointment_date' => '2026-04-08', 'appointment_time' => '09:00:00', 'service_id' => 4, 'status' => 'Scheduled', 'notes' => 'Consultation'],
    ['patient_id' => $patient_ids[4], 'dentist_id' => 1, 'appointment_date' => '2026-04-09', 'appointment_time' => '15:00:00', 'service_id' => 5, 'status' => 'Pending', 'notes' => 'Oral prophylaxis'],
    ['patient_id' => $patient_ids[5], 'dentist_id' => 1, 'appointment_date' => '2026-04-10', 'appointment_time' => '13:00:00', 'service_id' => 13, 'status' => 'Completed', 'notes' => 'Veneers consultation'],
    ['patient_id' => $patient_ids[6], 'dentist_id' => 1, 'appointment_date' => '2026-04-11', 'appointment_time' => '10:30:00', 'service_id' => 20, 'status' => 'Scheduled', 'notes' => 'Tooth extraction'],
    ['patient_id' => $patient_ids[7], 'dentist_id' => 1, 'appointment_date' => '2026-04-12', 'appointment_time' => '16:00:00', 'service_id' => 23, 'status' => 'Pending', 'notes' => 'Composite filling'],
    ['patient_id' => $patient_ids[0], 'dentist_id' => 1, 'appointment_date' => '2026-04-15', 'appointment_time' => '11:00:00', 'service_id' => 1, 'status' => 'Scheduled', 'notes' => 'Follow-up check-up'],
    ['patient_id' => $patient_ids[1], 'dentist_id' => 1, 'appointment_date' => '2026-04-16', 'appointment_time' => '14:30:00', 'service_id' => 14, 'status' => 'Scheduled', 'notes' => 'Teeth whitening']
];

foreach ($appointments as $appointment) {
    $stmt = $conn->prepare("INSERT INTO appointment (patient_id, dentist_id, appointment_date, appointment_time, service_id, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssss", $appointment['patient_id'], $appointment['dentist_id'], $appointment['appointment_date'], $appointment['appointment_time'], $appointment['service_id'], $appointment['status'], $appointment['notes']);
    $stmt->execute();
    echo "Added appointment for patient {$appointment['patient_id']} on {$appointment['appointment_date']}<br>";
}

// 6. ADD PAYMENTS
echo "<h2>Adding Payments...</h2>";

// Get the actual appointment IDs that were just added
$appointment_result = $conn->query("SELECT appointment_id FROM appointment ORDER BY appointment_id");
$appointment_ids = [];
while($row = $appointment_result->fetch_assoc()) {
    $appointment_ids[] = $row['appointment_id'];
}

$payments = [
    ['appointment_id' => $appointment_ids[0], 'amount' => 500.00, 'payment_method' => 'Cash', 'status' => 'Paid'],
    ['appointment_id' => $appointment_ids[1], 'amount' => 1000.00, 'payment_method' => 'Credit Card', 'status' => 'Paid'],
    ['appointment_id' => $appointment_ids[5], 'amount' => 12000.00, 'payment_method' => 'Cash', 'status' => 'Paid'],
    ['appointment_id' => $appointment_ids[2], 'amount' => 2500.00, 'payment_method' => 'Cash', 'status' => 'Partial'],
    ['appointment_id' => $appointment_ids[3], 'amount' => 500.00, 'payment_method' => 'Cash', 'status' => 'Paid']
];

foreach ($payments as $payment) {
    $stmt = $conn->prepare("INSERT INTO payment (appointment_id, amount, payment_method, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("idss", $payment['appointment_id'], $payment['amount'], $payment['payment_method'], $payment['status']);
    $stmt->execute();
    echo "Added payment of ₱{$payment['amount']} for appointment {$payment['appointment_id']}<br>";
}

// 6. ADD CLINICAL NOTES
echo "<h2>Adding Clinical Notes...</h2>";
$clinical_notes = [
    ['patient_id' => $patient_ids[0], 'dentist_id' => 1, 'appointment_id' => $appointment_ids[0], 'service_rendered' => 'General Check-up', 'treatment_notes' => 'Patient presented with good oral hygiene. No cavities detected. Recommended regular cleaning.'],
    ['patient_id' => $patient_ids[1], 'dentist_id' => 1, 'appointment_id' => $appointment_ids[1], 'service_rendered' => 'Oral Prophylaxis', 'treatment_notes' => 'Heavy tartar buildup removed. Patient educated on proper brushing technique. Fluoride treatment applied.'],
    ['patient_id' => $patient_ids[5], 'dentist_id' => 1, 'appointment_id' => $appointment_ids[5], 'service_rendered' => 'Veneers Consultation', 'treatment_notes' => 'Patient interested in cosmetic improvement. Discussed veneer options and treatment plan. Impressions taken.'],
    ['patient_id' => $patient_ids[2], 'dentist_id' => 1, 'appointment_id' => $appointment_ids[2], 'service_rendered' => 'Root Canal', 'treatment_notes' => 'Tooth #14 showed signs of infection. Root canal procedure initiated. Patient comfortable throughout procedure.']
];

foreach ($clinical_notes as $note) {
    $stmt = $conn->prepare("INSERT INTO clinical_notes (patient_id, dentist_id, appointment_id, service_rendered, treatment_notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiss", $note['patient_id'], $note['dentist_id'], $note['appointment_id'], $note['service_rendered'], $note['treatment_notes']);
    $stmt->execute();
    echo "Added clinical note for patient {$note['patient_id']}<br>";
}

// 8. ADD DENTAL CHART DATA
echo "<h2>Adding Dental Chart Data...</h2>";
$dental_chart = [
    ['patient_id' => $patient_ids[0], 'tooth_number' => 14, 'condition_type' => 'Cavity', 'notes' => 'Small cavity on occlusal surface'],
    ['patient_id' => $patient_ids[0], 'tooth_number' => 15, 'condition_type' => 'Healthy', 'notes' => 'No issues detected'],
    ['patient_id' => $patient_ids[1], 'tooth_number' => 18, 'condition_type' => 'Tartar', 'notes' => 'Heavy calculus buildup'],
    ['patient_id' => $patient_ids[1], 'tooth_number' => 17, 'condition_type' => 'Healthy', 'notes' => 'Good condition'],
    ['patient_id' => $patient_ids[2], 'tooth_number' => 14, 'condition_type' => 'Root Canal', 'notes' => 'Infected pulp, treatment in progress'],
    ['patient_id' => $patient_ids[5], 'tooth_number' => 11, 'condition_type' => 'Veneer', 'notes' => 'Planning cosmetic veneer'],
    ['patient_id' => $patient_ids[5], 'tooth_number' => 21, 'condition_type' => 'Veneer', 'notes' => 'Planning cosmetic veneer']
];

foreach ($dental_chart as $chart) {
    $stmt = $conn->prepare("INSERT INTO dental_chart (patient_id, tooth_number, condition_type, notes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $chart['patient_id'], $chart['tooth_number'], $chart['condition_type'], $chart['notes']);
    $stmt->execute();
    echo "Added dental chart entry for patient {$chart['patient_id']}, tooth {$chart['tooth_number']}<br>";
}

// 8. ADD TREATMENT RECORDS
echo "<h2>Adding Treatment Records...</h2>";
$treatment_records = [
    ['appointment_id' => $appointment_ids[0], 'dentist_id' => 1, 'procedure_notes' => 'Comprehensive oral examination completed. No immediate concerns.', 'prescription' => 'None', 'date_performed' => '2026-04-05'],
    ['appointment_id' => $appointment_ids[1], 'dentist_id' => 1, 'procedure_notes' => 'Scaling and root planing performed. Oral hygiene instructions given.', 'prescription' => 'Chlorhexidine mouthwash', 'date_performed' => '2026-04-06'],
    ['appointment_id' => $appointment_ids[5], 'dentist_id' => 1, 'procedure_notes' => 'Veneer consultation completed. Treatment plan discussed and approved.', 'prescription' => 'None', 'date_performed' => '2026-04-10']
];

foreach ($treatment_records as $record) {
    $stmt = $conn->prepare("INSERT INTO treatment_record (appointment_id, dentist_id, procedure_notes, prescription, date_performed) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $record['appointment_id'], $record['dentist_id'], $record['procedure_notes'], $record['prescription'], $record['date_performed']);
    $stmt->execute();
    echo "Added treatment record for appointment {$record['appointment_id']}<br>";
}

// 10. ADD STAFF DETAILS
echo "<h2>Adding Staff Details...</h2>";
// Get the actual user IDs for the staff (skip the first 5 original users)
$user_result = $conn->query("SELECT user_id, username FROM users WHERE user_id > 5 ORDER BY user_id");
$user_ids = [];
while($row = $user_result->fetch_assoc()) {
    $user_ids[] = $row['user_id'];
}
$staff_details = [
    ['user_id' => $user_ids[0], 'specialization' => 'General Dentistry', 'license_number' => 'DENT-001', 'phone_number' => '09123456789', 'schedule_days' => 'Mon-Fri', 'bio' => 'Experienced general dentist with 10+ years in practice.'],
    ['user_id' => $user_ids[1], 'specialization' => 'Cosmetic Dentistry', 'license_number' => 'DENT-002', 'phone_number' => '09123456790', 'schedule_days' => 'Mon,Wed,Fri', 'bio' => 'Specialist in cosmetic dentistry and smile makeovers.'],
    ['user_id' => $user_ids[2], 'specialization' => 'Receptionist', 'license_number' => 'RECP-001', 'phone_number' => '09123456792', 'schedule_days' => 'Mon-Sat', 'bio' => 'Experienced receptionist with excellent customer service skills.']
];

foreach ($staff_details as $detail) {
    $stmt = $conn->prepare("INSERT INTO staff_details (user_id, specialization, license_number, phone_number, schedule_days, bio) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $detail['user_id'], $detail['specialization'], $detail['license_number'], $detail['phone_number'], $detail['schedule_days'], $detail['bio']);
    $stmt->execute();
    echo "Added staff details for user {$detail['user_id']}<br>";
}

echo "<h2>Sample Data Addition Complete!</h2>";
echo "<p>You can now test all pages with realistic data:</p>";
echo "<ul>";
echo "<li><strong>Admin Dashboard:</strong> View statistics, manage users, see appointments</li>";
echo "<li><strong>Dentist Dashboard:</strong> Today's schedule, patient records, clinical notes</li>";
echo "<li><strong>Receptionist Dashboard:</strong> Appointment management, patient registration</li>";
echo "<li><strong>Patient Pages:</strong> View appointments, medical history</li>";
echo "</ul>";
echo "<p><a href='test_pages.php'>← Back to Test Pages</a></p>";

$conn->close();
?>