<?php
/**
 * Update the appointment.status column to only allow:
 *   - pending
 *   - ongoing
 *   - completed
 *   - cancelled
 *
 * Run from the adv db folder:
 *   php update_appointment_status_enum.php
 */

require_once __DIR__ . '/includes/connect.php';

if (!isset($conn) || !$conn) {
    fwrite(STDERR, "Database connection is unavailable.\n");
    exit(1);
}

$allowedStatuses = [
    'pending',
    'ongoing',
    'completed',
    'cancelled',
];

$cleanupMap = [
    'approved' => 'pending',
    'disapproved' => 'cancelled',
    'pending_payment' => 'pending',
];

foreach ($cleanupMap as $from => $to) {
    $sql = "UPDATE appointment SET status = ? WHERE status = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        fwrite(STDERR, "Failed to prepare cleanup statement: " . mysqli_error($conn) . "\n");
        exit(1);
    }
    mysqli_stmt_bind_param($stmt, 'ss', $to, $from);
    if (!mysqli_stmt_execute($stmt)) {
        fwrite(STDERR, "Failed to normalize status '$from' to '$to': " . mysqli_stmt_error($stmt) . "\n");
        mysqli_stmt_close($stmt);
        exit(1);
    }
    mysqli_stmt_close($stmt);
}

$sql = "ALTER TABLE appointment \
    MODIFY COLUMN status ENUM('pending','ongoing','completed','cancelled') \
    COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending'";

if (mysqli_query($conn, $sql)) {
    echo "Appointment status enum updated successfully.\n";
    exit(0);
}

fwrite(STDERR, "Failed to update appointment.status enum: " . mysqli_error($conn) . "\n");
exit(1);
