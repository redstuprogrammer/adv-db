<?php
session_start();
include "db.php"; 
date_default_timezone_set('Asia/Manila');

// 1. SECURITY & DATA VALIDATION
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Dentist') {
    header("Location: login.php"); exit();
}

$patient_id = $_GET['id'] ?? null;
$dentist_id = $_SESSION['user_id'];

if (!$patient_id) { 
    echo "<script>alert('Patient not found'); window.location.href='patient.php';</script>"; 
    exit(); 
}

// 2. FETCH PATIENT DATA
$p_query = $conn->prepare("SELECT *, CONCAT(first_name, ' ', last_name) AS full_name FROM patient WHERE patient_id = ?");
$p_query->bind_param("i", $patient_id);
$p_query->execute();
$patient = $p_query->get_result()->fetch_assoc();

// 3. HANDLE TREATMENT NOTE SUBMISSION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_note'])) {
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    $service = mysqli_real_escape_string($conn, $_POST['service_rendered']);
    
    $stmt = $conn->prepare("INSERT INTO clinical_notes (patient_id, dentist_id, service_rendered, treatment_notes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $patient_id, $dentist_id, $service, $notes);
    
    if($stmt->execute()){
        // Redirect back to this page with a success flag
        header("Location: clinical_record.php?id=$patient_id&msg=success");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Record for <?= htmlspecialchars($patient['last_name']) ?></title>
    <link rel="stylesheet" href="style1.css">
    <style>
        .record-container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        
        /* Success Message */
        .toast-success { background: #dcfce7; color: #15803d; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #bbf7d0; font-weight: 600; text-align: center; }

        /* Patient Header */
        .patient-summary { 
            background: white; padding: 25px; border-radius: 15px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; 
            justify-content: space-between; align-items: center; margin-bottom: 25px;
            border-left: 6px solid #0d3b66;
        }
        
        /* Note Form */
        .entry-box { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 40px; }
        .soap-input { 
            width: 100%; height: 180px; padding: 15px; border: 1px solid #e2e8f0; 
            border-radius: 10px; margin-top: 10px; font-family: inherit; 
            line-height: 1.6; resize: vertical; outline: none; transition: 0.3s;
        }
        .soap-input:focus { border-color: #0d3b66; box-shadow: 0 0 0 3px rgba(13,59,102,0.1); }
        
        /* Timeline */
        .timeline { position: relative; padding-left: 30px; }
        .timeline::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 2px; background: #cbd5e1; }
        .history-card { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; position: relative; border: 1px solid #e2e8f0; }
        .history-card::before { content: ''; position: absolute; left: -34px; top: 22px; width: 10px; height: 10px; background: #0d3b66; border-radius: 50%; border: 4px solid #f1f5f9; }
        
        .date-label { font-size: 11px; color: #94a3b8; font-weight: 800; text-transform: uppercase; }
        .service-badge { display: inline-block; padding: 4px 12px; background: #e0f2fe; color: #0369a1; border-radius: 6px; font-size: 12px; font-weight: 700; margin-bottom: 10px; }
        
        .btn-finalize { background: #0d3b66; color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-finalize:hover { background: #002855; transform: translateY(-1px); }
    </style>
</head>
<body style="background: #f8fafc;">

<div class="record-container">
    
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
        <div class="toast-success">✔️ Treatment note saved to clinical history.</div>
    <?php endif; ?>

    <div class="patient-summary">
        <div>
            <h1 style="margin:0; color:#0d3b66;"><?= htmlspecialchars($patient['full_name']) ?></h1>
            <p style="margin:5px 0 0 0; color:#64748b; font-size: 14px;">
                Gender: <?= $patient['gender'] ?> | ID: #PAT-<?= $patient['patient_id'] ?>
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="dentist_patient_view.php?id=<?= $patient_id ?>" class="btn-action" style="background:#f1f5f9; color:#475569; text-decoration:none; padding:10px 15px; border-radius:8px; font-size: 13px; font-weight: 600;">View Profile</a>
            <a href="dentist_appointments.php" class="btn-action" style="background:#e2e8f0; color:#0d3b66; text-decoration:none; padding:10px 15px; border-radius:8px; font-size: 13px; font-weight: 600;">Close</a>
        </div>
    </div>

    <div class="entry-box">
        <h3 style="margin-top:0; color:#0d3b66; font-size: 18px;">New Treatment Session</h3>
        <form method="POST">
            <div style="margin-bottom: 15px;">
                <label style="font-weight:700; font-size:13px; color:#64748b; text-transform:uppercase;">Service Rendered</label>
                <input type="text" name="service_rendered" placeholder="e.g. Tooth Extraction, Scaling & Polishing" required style="width:100%; padding:12px; border:1px solid #e2e8f0; border-radius:8px; margin-top:5px; font-size:15px;">
            </div>
            
            <label style="font-weight:700; font-size:13px; color:#64748b; text-transform:uppercase;">Clinical SOAP Notes</label>
            <textarea name="notes" class="soap-input" placeholder="Subjective: Patient reports pain...&#10;Objective: Visible cavity on #3...&#10;Assessment: Dental Caries...&#10;Plan: Filling performed..."></textarea>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="submit" name="save_note" class="btn-finalize">Finalize & Save Note</button>
            </div>
        </form>
    </div>

    <h3 style="color:#0d3b66; margin-bottom:20px; font-size: 18px;">Clinical History Timeline</h3>
    <div class="timeline">
        <?php
        $history = $conn->query("SELECT cn.*, d.last_name FROM clinical_notes cn JOIN dentist d ON cn.dentist_id = d.dentist_id WHERE cn.patient_id = $patient_id ORDER BY cn.created_at DESC");
        if($history->num_rows > 0):
            while($row = $history->fetch_assoc()): ?>
                <div class="history-card">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <span class="service-badge"><?= htmlspecialchars($row['service_rendered']) ?></span>
                        <div class="date-label"><?= date('M d, Y | h:i A', strtotime($row['created_at'])) ?></div>
                    </div>
                    <div style="white-space: pre-wrap; font-size:14px; color:#334155; line-height:1.6; margin-top:5px;"><?= htmlspecialchars($row['treatment_notes']) ?></div>
                    <div style="margin-top:15px; padding-top:10px; border-top:1px solid #f8fafc; font-size:11px; color:#94a3b8; font-style: italic;">
                        Documented by Dr. <?= $row['last_name'] ?>
                    </div>
                </div>
            <?php endwhile; 
        else: ?>
            <div style="text-align:center; padding: 40px; background: white; border-radius: 15px; color:#94a3b8; border: 2px dashed #e2e8f0;">
                No clinical notes recorded for this patient yet.
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>