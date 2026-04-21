<?php
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';

$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('dentist');

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
$patientId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$tenantData = $sessionManager->getTenantData();
$tenantName = $tenantData['tenant_name'] ?? '';
$tenantId   = $sessionManager->getTenantId();
$dentistId  = $sessionManager->getUserId();

// Fetch patient — must belong to this tenant
$patient = null;
if ($patientId > 0) {
    $stmt = mysqli_prepare($conn,
        'SELECT p.*, MAX(a.appointment_date) AS last_visit
         FROM patient p
         LEFT JOIN appointment a ON p.patient_id = a.patient_id AND a.tenant_id = p.tenant_id
         WHERE p.patient_id = ? AND p.tenant_id = ?
         GROUP BY p.patient_id
         LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $patientId, $tenantId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $patient = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
}

// Fetch appointment history for this patient (all dentists, full history)
$appointments = [];
if ($patient) {
    $stmt = mysqli_prepare($conn,
        'SELECT a.appointment_id, a.appointment_date, a.status,
                CONCAT(d.first_name, " ", d.last_name) AS dentist_name,
                COALESCE(s.service_name, "General Consultation") AS service_name
         FROM appointment a
         LEFT JOIN dentist d ON a.dentist_id = d.dentist_id
         LEFT JOIN services s ON a.service_id = s.service_id
         WHERE a.patient_id = ? AND a.tenant_id = ?
         ORDER BY a.appointment_date DESC
         LIMIT 50');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $patientId, $tenantId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $appointments[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Patient Profile</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
      :root { --accent: #0d3b66; --border: #e2e8f0; --bg: #f8fafc; }

      .profile-grid {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 20px;
        align-items: start;
      }

      @media (max-width: 900px) {
        .profile-grid { grid-template-columns: 1fr; }
      }

      .card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(15,23,42,0.08);
        overflow: hidden;
      }

      .card-header {
        background: var(--accent);
        color: white;
        padding: 16px 20px;
        font-weight: 700;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 12px 20px;
        border-bottom: 1px solid var(--border);
        gap: 12px;
      }
      .detail-row:last-child { border-bottom: none; }
      .detail-label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap; }
      .detail-value { font-size: 13px; color: #1e293b; font-weight: 500; text-align: right; word-break: break-word; max-width: 60%; }

      .allergy-badge {
        display: inline-block;
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
      }

      .appt-table { width: 100%; border-collapse: collapse; }
      .appt-table th, .appt-table td { padding: 12px 16px; border-bottom: 1px solid var(--border); text-align: left; font-size: 13px; color: #334155; }
      .appt-table th { background: var(--bg); color: var(--accent); font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; }
      .appt-table tbody tr:hover { background: #f8fafc; }

      .status-pill { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
      .status-completed { background: #d1fae5; color: #065f46; }
      .status-pending   { background: #fef3c7; color: #92400e; }
      .status-cancelled { background: #fee2e2; color: #991b1b; }
      .status-no-show   { background: #f1f5f9; color: #475569; }

      .action-btn { padding: 6px 14px; background: var(--accent); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-block; }
      .action-btn:hover { background: #0a2d4f; }
      .action-btn.outline { background: white; color: var(--accent); border: 1px solid var(--border); }
      .action-btn.outline:hover { background: var(--bg); }

      .back-link { display: inline-flex; align-items: center; gap: 6px; color: var(--accent); font-size: 13px; font-weight: 600; text-decoration: none; margin-bottom: 20px; }
      .back-link:hover { text-decoration: underline; }

      .not-found {
        text-align: center;
        padding: 80px 20px;
        color: #64748b;
      }
      .not-found .icon { font-size: 48px; margin-bottom: 16px; }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title"><?php echo h($tenantName); ?> — Patient Profile</div>
        <?php renderDateClock(); ?>
      </div>

      <a href="dentist_patients.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" class="back-link">
        ← Back to My Patients
      </a>

      <?php if (!$patient): ?>
        <div class="card">
          <div class="not-found">
            <div class="icon">🔍</div>
            <p style="font-size:16px; font-weight:600; margin:0 0 8px;">Patient not found</p>
            <p style="font-size:13px; margin:0;">This patient does not exist or does not belong to your clinic.</p>
          </div>
        </div>

      <?php else:
        $lastVisit  = $patient['last_visit'] ? date('M d, Y', strtotime($patient['last_visit'])) : 'No visits yet';
        $age        = $patient['birthdate'] ? floor((time() - strtotime($patient['birthdate'])) / 31557600) : null;
        $allergies  = trim($patient['allergies'] ?? '');
        $medHistory = trim($patient['medical_history'] ?? '');
      ?>

        <div class="profile-grid">

          <!-- ── Left: Demographics ─────────────────────────── -->
          <div style="display:flex; flex-direction:column; gap:20px;">

            <div class="card">
              <div class="card-header">👤 Patient Information</div>

              <div class="detail-row">
                <div class="detail-label">Full Name</div>
                <div class="detail-value" style="font-weight:700; font-size:14px; color:var(--accent);">
                  <?php echo h($patient['first_name'] . ' ' . $patient['last_name']); ?>
                </div>
              </div>

              <?php
              $demographics = [
                'Patient ID'    => 'P' . str_pad($patient['patient_id'], 3, '0', STR_PAD_LEFT),
                'Gender'        => $patient['gender'] ?? 'N/A',
                'Birthdate'     => $patient['birthdate'] ?? 'N/A',
                'Age'           => $age !== null ? $age . ' years old' : 'N/A',
                'Contact'       => $patient['contact_number'] ?? 'N/A',
                'Email'         => $patient['email'] ?? 'N/A',
                'Address'       => $patient['address'] ?? 'N/A',
                'Occupation'    => $patient['occupation'] ?? 'N/A',
                'Last Visit'    => $lastVisit,
              ];
              foreach ($demographics as $label => $value): ?>
                <div class="detail-row">
                  <div class="detail-label"><?php echo h($label); ?></div>
                  <div class="detail-value"><?php echo h($value); ?></div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="card">
              <div class="card-header">🏥 Medical Notes</div>

              <div style="padding:16px 20px;">
                <div style="margin-bottom:16px;">
                  <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:8px;">Allergies</div>
                  <?php if ($allergies): ?>
                    <?php foreach (explode(',', $allergies) as $a): ?>
                      <span class="allergy-badge"><?php echo h(trim($a)); ?></span>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span style="font-size:13px; color:#94a3b8;">None on record</span>
                  <?php endif; ?>
                </div>

                <div>
                  <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:8px;">Medical History</div>
                  <p style="font-size:13px; color:#334155; margin:0; line-height:1.6;">
                    <?php echo $medHistory ? h($medHistory) : '<span style="color:#94a3b8;">None on record</span>'; ?>
                  </p>
                </div>

                <?php if (!empty($patient['notes'])): ?>
                  <div style="margin-top:16px;">
                    <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:8px;">Clinical Notes</div>
                    <p style="font-size:13px; color:#334155; margin:0; line-height:1.6;"><?php echo h($patient['notes']); ?></p>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div style="display:flex; gap:10px;">
              <a href="clinical_record.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&patient_id=<?php echo $patientId; ?>" class="action-btn" style="flex:1; text-align:center; padding:10px;">
                📋 Open Clinical Records
              </a>
            </div>

          </div>

          <!-- ── Right: Appointment History ─────────────────── -->
          <div class="card">
            <div class="card-header">📅 Appointment History
              <span style="margin-left:auto; font-size:12px; font-weight:400; opacity:0.8;">
                <?php echo count($appointments); ?> record<?php echo count($appointments) !== 1 ? 's' : ''; ?>
              </span>
            </div>

            <?php if (empty($appointments)): ?>
              <div style="text-align:center; padding:40px; color:#94a3b8;">
                <div style="font-size:36px; margin-bottom:12px;">📭</div>
                <p style="margin:0; font-size:13px;">No appointment history found.</p>
              </div>
            <?php else: ?>
              <div style="overflow-x:auto;">
                <table class="appt-table">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Service</th>
                      <th>Dentist</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($appointments as $appt):
                      $statusClass = 'status-' . strtolower(str_replace(' ', '-', $appt['status'] ?? 'pending'));
                    ?>
                      <tr>
                        <td style="white-space:nowrap;"><?php echo h(date('M d, Y', strtotime($appt['appointment_date']))); ?></td>
                        <td><?php echo h($appt['service_name']); ?></td>
                        <td><?php echo h($appt['dentist_name'] ?? 'N/A'); ?></td>
                        <td><span class="status-pill <?php echo $statusClass; ?>"><?php echo h(ucfirst(strtolower($appt['status'] ?? ''))); ?></span></td>
                        <td>
                          <a href="clinical_record.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&id=<?php echo $patientId; ?>&appt=<?php echo $appt['appointment_id']; ?>" class="action-btn outline" style="font-size:11px; padding:4px 10px;">
                            Log
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

        </div>
      <?php endif; ?>

    </div>
  </div>

  <script>
    <?php printDateClockScript(); ?>
  </script>
</body>
</html>
