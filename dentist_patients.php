<?php
/**
 * ============================================
 * DENTIST PATIENT DIRECTORY
 * Last Updated: April 2026
 * ============================================
 */

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

function formatTenantPatientId($id): string {
    return '#' . str_pad($id, 4, '0', STR_PAD_LEFT);
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
$tenantData = $sessionManager->getTenantData();
$tenantName = $tenantData['tenant_name'] ?? '';
$tenantId   = $sessionManager->getTenantId();
$dentistId  = $sessionManager->getUserId();

$patients = [];
$stmt = mysqli_prepare($conn,
    'SELECT p.patient_id, p.tenant_patient_id, p.first_name, p.last_name,
            p.contact_number, p.email, p.birthdate, p.gender,
            MAX(a.appointment_date) AS last_visit
     FROM patient p
     LEFT JOIN appointment a ON p.patient_id = a.patient_id AND a.tenant_id = p.tenant_id
     WHERE p.tenant_id = ?
     GROUP BY p.patient_id, p.tenant_patient_id, p.first_name, p.last_name,
              p.contact_number, p.email, p.birthdate, p.gender
     ORDER BY p.first_name ASC');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $tenantId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $patients[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Inline view via ?view_patient_id=
$viewPatient = null;
if (isset($_GET['view_patient_id'])) {
    $vpId = (int)$_GET['view_patient_id'];
    $stmt = mysqli_prepare($conn, 'SELECT * FROM patient WHERE patient_id = ? AND tenant_id = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $vpId, $tenantId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $viewPatient = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | My Patients</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
      :root { --accent: #0d3b66; --border: #e2e8f0; --bg: #f8fafc; }

      .btn-primary { background: var(--accent); color: white; padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-weight: 600; font-size: 13px; transition: background 0.2s; }
      .btn-primary:hover { background: #0a2d4f; }

      .module-card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 24px; box-shadow: 0 4px 12px rgba(15,23,42,0.08); }

      .search-bar { display: flex; gap: 12px; margin-bottom: 20px; }
      .search-bar input { flex: 1; padding: 10px 12px; border: 2px solid #d1d5db !important; border-radius: 8px; font-size: 13px; }

      .search-bar input:focus {
        border-color: var(--accent) !important;
        box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.1);
      }


      .patient-table { width: 100%; border-collapse: collapse; }
      .patient-table th, .patient-table td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; text-align: left; color: #334155; }
      .patient-table th { background: #f8fafc; color: var(--accent); font-weight: 700; font-size: 13px; }
      .patient-table tbody tr:hover { background: #f1f5f9; }



      .action-btn { padding: 6px 12px; background: var(--accent); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-block; }
      .action-btn:hover { background: #0a2d4f; }
      .action-btn.outline { background: white; color: var(--accent); border: 1px solid var(--border); }
      .action-btn.outline:hover { background: var(--bg); }

      .modal { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 1000; align-items: center; justify-content: center; }
      .modal.open { display: flex; }
      .modal-content { background: white; border-radius: 12px; width: 100%; max-width: 540px; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(15,23,42,0.2); }
      .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px 16px; border-bottom: 1px solid var(--border); position: sticky; top: 0; background: white; z-index: 1; }
      .modal-header span { font-weight: 700; font-size: 15px; color: var(--accent); }
      .close { font-size: 22px; cursor: pointer; background: none; border: none; color: #64748b; line-height: 1; }
      .close:hover { color: var(--accent); }
      .patient-detail-row { display: flex; justify-content: space-between; padding: 12px 24px; border-bottom: 1px solid var(--border); }
      .patient-detail-label { font-weight: 700; color: var(--accent); font-size: 13px; }
      .patient-detail-value { font-size: 13px; color: #64748b; text-align: right; max-width: 60%; }
      .modal-footer { padding: 16px 24px; display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid var(--border); }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">👥 My Patients</div>
        <?php renderDateClock(); ?>
      </div>

      <div class="module-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
          <h2 style="margin:0; color:var(--accent); font-size:16px;">Patient Directory</h2>
          <span style="font-size:13px; color:#64748b;"><?php echo count($patients); ?> patient<?php echo count($patients) !== 1 ? 's' : ''; ?></span>
        </div>

        <div class="search-bar">
          <input type="text" id="searchInput" placeholder="Search patient by name or ID..." onkeyup="filterPatients()" />
        </div>

        <div style="overflow-x:auto;">
          <table class="patient-table" id="patientGrid">
            <thead>
              <tr>
                <th>Patient ID</th>
                <th>Full Name</th>
                <th>Contact</th>
                <th>Email</th>
                <th>Last Visit</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($patients)): ?>
                <tr><td colspan="6" style="text-align:center; padding:32px; color:#64748b;">No patients found in your records.</td></tr>
              <?php else: ?>
                <?php foreach ($patients as $patient):
                  $lastVisit = $patient['last_visit'] ? date('M d, Y', strtotime($patient['last_visit'])) : 'Never';
                ?>
                  <tr data-patient-name="<?php echo strtolower(h($patient['first_name'] . ' ' . $patient['last_name'])); ?>">
                    <td><?php echo h(formatTenantPatientId($patient['tenant_patient_id'])); ?></td>
                    <td style="font-weight:600;"><?php echo h($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                    <td><?php echo h($patient['contact_number'] ?? 'N/A'); ?></td>
                    <td><?php echo h($patient['email'] ?? 'N/A'); ?></td>
                    <td><?php echo h($lastVisit); ?></td>
                    <td style="display:flex; gap:6px;">
                      <button class="action-btn" onclick="openPatientModal(<?php echo (int)$patient['patient_id']; ?>)">View</button>
                      <a href="clinical_record.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&patient_id=<?php echo (int)$patient['patient_id']; ?>" class="action-btn outline">Records</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Patient Detail Modal -->
  <div id="patientModal" class="modal <?php echo $viewPatient ? 'open' : ''; ?>">
    <div class="modal-content">
      <div class="modal-header">
        <span id="modalPatientName">Patient Details</span>
        <button class="close" onclick="closePatientModal()">&times;</button>
      </div>
      <div id="modalBody">
        <?php if ($viewPatient): ?>
          <?php
            $fields = [
              'Patient ID'      => 'P' . str_pad($viewPatient['patient_id'], 3, '0', STR_PAD_LEFT),
              'Full Name'       => h($viewPatient['first_name'] . ' ' . $viewPatient['last_name']),
              'Contact Number'  => h($viewPatient['contact_number'] ?? 'N/A'),
              'Email'           => h($viewPatient['email'] ?? 'N/A'),
              'Gender'          => h($viewPatient['gender'] ?? 'N/A'),
              'Birthdate'       => h($viewPatient['birthdate'] ?? 'N/A'),
              'Address'         => h($viewPatient['address'] ?? 'N/A'),
              'Occupation'      => h($viewPatient['occupation'] ?? 'N/A'),
              'Medical History' => h($viewPatient['medical_history'] ?? 'N/A'),
              'Allergies'       => h($viewPatient['allergies'] ?? 'N/A'),
              'Notes'           => h($viewPatient['notes'] ?? 'N/A'),
            ];
            foreach ($fields as $label => $value): ?>
              <div class="patient-detail-row">
                <div class="patient-detail-label"><?php echo $label; ?></div>
                <div class="patient-detail-value"><?php echo $value; ?></div>
              </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <a id="modalRecordsLink" href="#" class="action-btn outline">View Clinical Records</a>
        <button class="action-btn" onclick="closePatientModal()">Close</button>
      </div>
    </div>
  </div>

  <script>
    <?php printDateClockScript(); ?>

    const tenantSlug = '<?php echo rawurlencode($tenantSlug); ?>';

    const patientData = <?php
      $map = [];
      foreach ($patients as $p) {
          $map[$p['patient_id']] = [
              'name'      => $p['first_name'] . ' ' . $p['last_name'],
              'pid'       => 'P' . str_pad($p['patient_id'], 3, '0', STR_PAD_LEFT),
              'contact'   => $p['contact_number'] ?? 'N/A',
              'email'     => $p['email'] ?? 'N/A',
              'gender'    => $p['gender'] ?? 'N/A',
              'birthdate' => $p['birthdate'] ?? 'N/A',
              'last_visit'=> $p['last_visit'] ? date('M d, Y', strtotime($p['last_visit'])) : 'Never',
          ];
      }
      echo json_encode($map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>;

    function openPatientModal(id) {
      const p = patientData[id];
      if (!p) return;
      document.getElementById('modalPatientName').textContent = p.name + ' — Patient Details';
      document.getElementById('modalRecordsLink').href =
        'clinical_record.php?tenant=' + tenantSlug + '&patient_id=' + id;

      const fields = [
        ['Patient ID', p.pid], ['Full Name', p.name], ['Contact', p.contact],
        ['Email', p.email], ['Gender', p.gender], ['Birthdate', p.birthdate],
        ['Last Visit', p.last_visit],
      ];
      document.getElementById('modalBody').innerHTML = fields.map(([l, v]) =>
        `<div class="patient-detail-row">
           <div class="patient-detail-label">${l}</div>
           <div class="patient-detail-value">${v}</div>
         </div>`
      ).join('');
      document.getElementById('patientModal').classList.add('open');
    }

    function closePatientModal() {
      document.getElementById('patientModal').classList.remove('open');
      history.replaceState(null, '', window.location.pathname + '?tenant=' + tenantSlug);
    }

    document.getElementById('patientModal').addEventListener('click', function(e) {
      if (e.target === this) closePatientModal();
    });

    function filterPatients() {
      const q = document.getElementById('searchInput').value.toLowerCase();
      document.querySelectorAll('#patientGrid tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    }
  </script>
</body>
</html>
