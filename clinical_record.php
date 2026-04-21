<?php
/**
 * ============================================
 * CLINICAL RECORD - PATIENT TREATMENT HISTORY
 * Last Updated: April 6, 2026
 * For: Dentist Access
 * ============================================
 */

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';
require_once __DIR__ . '/includes/custom_modal.php';

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Dentist') {
    header("Location: tenant_login.php");
    exit();
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

$tenantName = $_SESSION['tenant_name'];
$tenantId = $_SESSION['tenant_id'];
$dentistId = $_SESSION['user_id'] ?? 0;
if (!$dentistId) {
    echo "<script>showCustomAlert('Access denied: Invalid session.'); window.history.back();</script>";
    exit();
}
$dentistName = $_SESSION['username'] ?? 'Dentist';

// Get patient_id from URL
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$patient_id) {
    echo "<script>showCustomAlert('Patient not found.'); window.history.back();</script>";
    exit();
}

// Fetch patient data
$patientStmt = $conn->prepare("SELECT patient_id, tenant_patient_id, first_name, last_name, birthdate, gender, contact_number, email FROM patient WHERE patient_id = ? AND tenant_id = ?");
if ($patientStmt) {
    $patientStmt->bind_param('ii', $patient_id, $tenantId);
    $patientStmt->execute();
    $patientResult = $patientStmt->get_result();
    $patient = $patientResult->fetch_assoc();
    $patientStmt->close();
}

if (!$patient) {
    echo "<script>showCustomAlert('Patient not found.'); window.history.back();</script>";
    exit();
}

// Handle treatment note submission
$successMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_clinical_note'])) {
    $notes = trim($_POST['clinical_notes'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    
    if (!empty($notes) || !empty($diagnosis) || !empty($treatment)) {
        $noteStmt = $conn->prepare("INSERT INTO clinical_notes (tenant_id, patient_id, dentist_id, treatment_notes) VALUES (?, ?, ?, ?)");
        if ($noteStmt) {
            $combined_notes = "Diagnosis: $diagnosis\nTreatment: $treatment\nNotes: $notes";
            $noteStmt->bind_param('iis', $tenantId, $patient_id, $dentistId, $combined_notes);
            if ($noteStmt->execute()) {
                $successMsg = '✓ Clinical note saved successfully.';
            }
            $noteStmt->close();
        }
    }
}

// Fetch clinical history
$clinicalHistory = [];
$historyStmt = $conn->prepare("SELECT * FROM clinical_notes WHERE tenant_id = ? AND patient_id = ? ORDER BY note_id DESC");
if ($historyStmt) {
    $historyStmt->bind_param('ii', $tenantId, $patient_id);
    $historyStmt->execute();
    $historyResult = $historyStmt->get_result();
    while ($row = $historyResult->fetch_assoc()) {
        $clinicalHistory[] = $row;
    }
    $historyStmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinical Record - <?php echo h($patient['first_name'] . ' ' . $patient['last_name']); ?></title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
        :root {
            --primary: #0d3b66;
            --bg-light: #f8fafc;
            --border: #e2e8f0;
            --text-main: #334155;
            --text-muted: #64748b;
            --success: #dcfce7;
            --success-text: #166534;
        }

        body { background-color: var(--bg-light); }

        .clinical-container { max-width: 900px; margin: 30px auto; padding: 0 20px; }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header-section h1 { margin: 0; color: var(--primary); font-size: 28px; }

        .back-link {
            text-decoration: none;
            color: var(--primary);
            font-size: 16px;
            font-weight: 600;
            padding: 8px 16px;
            border: 2px solid var(--primary);
            border-radius: 8px;
            background: white;
            transition: all 0.2s ease;
        }

        .back-link:hover {
            background: var(--primary);
            color: white;
        }

        .success-message {
            background: var(--success);
            color: var(--success-text);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #bbf7d0;
            font-weight: 600;
        }

        .patient-summary {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border-left: 6px solid var(--primary);
        }

        .patient-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 15px;
        }

        .patient-field {
            display: flex;
            flex-direction: column;
        }

        .patient-field label {
            font-size: 11px;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .patient-field p {
            margin: 0;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
        }

        .entry-form {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .entry-form h3 {
            margin: 0 0 20px 0;
            color: var(--primary);
            font-size: 18px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(13,59,102,0.1);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-save {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-save:hover { background: #002855; }

        .btn-clear {
            background: white;
            color: var(--text-muted);
            border: 1px solid var(--border);
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-clear:hover { background: var(--bg-light); }

        .history-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .history-section h3 {
            margin: 0 0 20px 0;
            color: var(--primary);
            font-size: 18px;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border);
        }

        .history-entry {
            background: #f8fafc;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            position: relative;
            border: 1px solid var(--border);
        }

        .history-entry::before {
            content: '';
            position: absolute;
            left: -34px;
            top: 20px;
            width: 10px;
            height: 10px;
            background: var(--primary);
            border-radius: 50%;
            border: 4px solid white;
        }

        .entry-date {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .entry-content {
            font-size: 14px;
            color: var(--text-main);
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

<div class="clinical-container">
    <div class="header-section">
        <h1>Clinical Record</h1>
        <a href="dentist_appointments.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="back-link">← Back to Appointments</a>
    </div>

    <?php if ($successMsg): ?>
        <div class="success-message"><?php echo $successMsg; ?></div>
    <?php endif; ?>

    <div class="patient-summary">
        <h2 style="margin: 0; color: var(--primary); font-size: 22px;">
            <?php echo h($patient['first_name'] . ' ' . $patient['last_name']); ?>
        </h2>
        <p style="margin: 5px 0 0 0; color: var(--text-muted); font-size: 13px;">
            Patient ID: #<?php echo h(str_pad($patient['tenant_patient_id'], 4, '0', STR_PAD_LEFT)); ?>
        </p>

        <div class="patient-grid">
            <div class="patient-field">
                <label>Date of Birth</label>
                <p><?php echo h($patient['birthdate'] ? date('M d, Y', strtotime($patient['birthdate'])) : 'N/A'); ?></p>
            </div>
            <div class="patient-field">
                <label>Gender</label>
                <p><?php echo h($patient['gender'] ?? 'N/A'); ?></p>
            </div>
            <div class="patient-field">
                <label>Contact Number</label>
                <p><?php echo h($patient['contact_number'] ?? 'N/A'); ?></p>
            </div>
            <div class="patient-field">
                <label>Email</label>
                <p><?php echo h($patient['email'] ?? 'N/A'); ?></p>
            </div>
        </div>
    </div>

    <div class="entry-form">
        <h3>📝 Add Clinical Note</h3>
        <form method="POST">
            <div class="form-group">
                <label>Diagnosis</label>
                <textarea name="diagnosis" placeholder="Enter patient diagnosis..."></textarea>
            </div>

            <div class="form-group">
                <label>Treatment Provided</label>
                <textarea name="treatment" placeholder="Describe treatment provided..."></textarea>
            </div>

            <div class="form-group">
                <label>Clinical Notes</label>
                <textarea name="clinical_notes" placeholder="Additional observations and notes..." required></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" name="save_clinical_note" class="btn-save">Save Note</button>
                <button type="reset" class="btn-clear">Clear</button>
            </div>
        </form>
    </div>

    <?php if (!empty($clinicalHistory)): ?>
        <div class="history-section">
            <h3>📋 Clinical History</h3>
            <div class="timeline">
                <?php foreach ($clinicalHistory as $entry): ?>
                    <div class="history-entry">
                        <div class="entry-date"><?php echo date('M d, Y', strtotime($entry['created_at'] ?? 'now')); ?></div>
                        <div class="entry-content"><?php echo h($entry['treatment_notes'] ?? ''); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="history-section">
            <div class="empty-state">
                <p>No clinical notes on file for this patient yet.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php renderCustomModal(); ?>

</body>
</html>
