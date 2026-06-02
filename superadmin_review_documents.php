<?php
// Superadmin page for manual OCR document verification review
ini_set('session.gc_maxlifetime', 86400 * 7);
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

define('ROOT_PATH', __DIR__ . '/');
require_once ROOT_PATH . 'includes/security_headers.php';
require_once ROOT_PATH . 'includes/session_utils.php';
$sessionManager = SessionManager::getInstance();
$sessionManager->requireSuperAdmin();
require_once ROOT_PATH . 'includes/connect.php';
require_once ROOT_PATH . 'includes/onboarding_utils.php';
require_once ROOT_PATH . 'includes/subscription_tiers.php';

$message = '';
$alertType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenantId = intval($_POST['tenant_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if ($tenantId > 0 && in_array($action, ['approve', 'reject'], true)) {
        
        $stmt = $conn->prepare("SELECT tenant_id, company_name, owner_name, contact_email, subscription_tier, subscription_duration, registration_status FROM tenants WHERE tenant_id = ? LIMIT 1");
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $tenantRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($tenantRow && $tenantRow['registration_status'] === 'PENDING') {
            if ($action === 'approve') {
                $tier = $tenantRow['subscription_tier'];
                $duration = (int)$tenantRow['subscription_duration'];
                
                $tier_data = getTierByKey($tier);
                $monthly_amount = $tier_data['price_min'] ?? 0;
                $total_amount = $monthly_amount * $duration;

                $payment_status = ($tier === 'trial' || $total_amount <= 0) ? 'paid' : 'pending';
                $registration_status_final = 'APPROVED';
                
                // Update tenant
                $updateTenant = $conn->prepare("UPDATE tenants SET registration_status = ? WHERE tenant_id = ?");
                $updateTenant->bind_param('si', $registration_status_final, $tenantId);
                $updateTenant->execute();
                $updateTenant->close();

                $paymongo_url = null;
                $paymongo_session_id = null;

                if ($payment_status === 'pending') {
                    $pm_config = null;
                    $config_candidates = [
                        __DIR__ . '/config/paymongo.php',
                        $_SERVER['DOCUMENT_ROOT'] . '/config/paymongo.php',
                    ];
                    foreach ($config_candidates as $path) {
                        if (file_exists($path)) {
                            $pm_config = require $path;
                            break;
                        }
                    }
                    $secret = ($pm_config && isset($pm_config['secret_key'])) ? $pm_config['secret_key'] : (getenv('PAYMONGO_SECRET_KEY') ?: '');
                    
                    if ($secret) {
                        $auth = base64_encode($secret . ':');
                        $amount_centavos = (int) round($total_amount * 100);
                        $description = "OralSync Subscription: " . ucfirst($tier) . " ($duration months)";
                        
                        $payload = json_encode([
                            'data' => [
                                'attributes' => [
                                    'payment_method_types' => ['gcash', 'card', 'paymaya', 'grab_pay'],
                                    'line_items' => [[
                                        'currency'    => 'PHP',
                                        'amount'      => $amount_centavos,
                                        'description' => $description,
                                        'name'        => 'OralSync - ' . ucfirst($tier) . ' Plan',
                                        'quantity'    => 1,
                                    ]],
                                    'description' => $description,
                                    'send_email_receipt' => true,
                                    'metadata' => [
                                        'tenant_id' => (string)$tenantId,
                                        'tier_key' => $tier,
                                        'type' => 'initial_registration',
                                        'duration' => (string)$duration
                                    ]
                                ],
                            ],
                        ]);

                        $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
                        curl_setopt_array($ch, [
                            CURLOPT_POST           => true,
                            CURLOPT_POSTFIELDS     => $payload,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HTTPHEADER     => [
                                'Authorization: Basic ' . $auth,
                                'Content-Type: application/json',
                                'Accept: application/json',
                            ],
                        ]);

                        $pm_response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($http_code === 200) {
                            $pm_data = json_decode($pm_response, true);
                            $paymongo_url = $pm_data['data']['attributes']['checkout_url'] ?? null;
                            $paymongo_session_id = $pm_data['data']['id'] ?? null;
                        } else {
                            error_log("PayMongo API Error (HTTP $http_code): " . $pm_response);
                        }
                    }
                }

                // Update payment record
                if ($paymongo_session_id) {
                    $updPay = $conn->prepare("UPDATE payment SET paymongo_link_id = ? WHERE tenant_id = ? AND status = 'pending' AND paymongo_link_id IS NULL");
                    if ($updPay) {
                        $updPay->bind_param('si', $paymongo_session_id, $tenantId);
                        $updPay->execute();
                        $updPay->close();
                    }
                }

                // Send email
                if ($paymongo_url) {
                    sendTenantApprovalEmail([
                        'clinic_name' => $tenantRow['company_name'],
                        'owner_name' => $tenantRow['owner_name'],
                        'owner_email' => $tenantRow['contact_email'],
                        'payment_url' => $paymongo_url
                    ]);
                }

                $message = 'Application approved and payment link sent to the tenant.';
                $alertType = 'success';

            } else if ($action === 'reject') {
                // FIX: was 'archived' which is not a valid ENUM value — now fixed via migration
                $registration_status_final = 'REJECTED';
                $status = 'archived';

                $updateTenant = $conn->prepare("UPDATE tenants SET registration_status = ?, status = ? WHERE tenant_id = ?");
                $updateTenant->bind_param('ssi', $registration_status_final, $status, $tenantId);
                $updateTenant->execute();
                $updateTenant->close();

                sendTenantRejectionEmail([
                    'clinic_name' => $tenantRow['company_name'],
                    'owner_name' => $tenantRow['owner_name'],
                    'owner_email' => $tenantRow['contact_email']
                ]);

                $message = 'Application rejected and notification sent to the tenant.';
                $alertType = 'warning';
            }
        } else {
            $message = 'Tenant not found or already processed.';
            $alertType = 'danger';
        }
    } else {
        $message = 'Invalid review action.';
        $alertType = 'danger';
    }
}

$reviewRequests = [];
$query = "SELECT t.tenant_id, t.company_name, t.subdomain_slug, t.contact_email, t.phone, t.owner_name, t.status AS tenant_status, t.registration_status, t.subscription_tier, t.subscription_duration
          FROM tenants t
          WHERE t.registration_status IN ('PENDING', 'APPROVED', 'REJECTED')
          ORDER BY FIELD(t.registration_status, 'PENDING', 'APPROVED', 'REJECTED'), t.tenant_id DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reviewRequests[] = $row;
    }
    $result->close();
}

$pendingCount  = count(array_filter($reviewRequests, fn($r) => $r['registration_status'] === 'PENDING'));
$approvedCount = count(array_filter($reviewRequests, fn($r) => $r['registration_status'] === 'APPROVED'));
$rejectedCount = count(array_filter($reviewRequests, fn($r) => $r['registration_status'] === 'REJECTED'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Approvals — OralSync Superadmin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style1.css">
    <link rel="stylesheet" href="tenant_style.css">
    <style>
        /* ── Reset & Base ─────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy:    #0b2d4e;
            --navy-dk: #071e35;
            --teal:    #0e8a7c;
            --teal-lt: #d0f5f1;
            --amber:   #f59e0b;
            --rose:    #e11d48;
            --rose-lt: #fff1f2;
            --green:   #16a34a;
            --green-lt:#dcfce7;
            --slate:   #64748b;
            --border:  #e2e8f0;
            --bg:      #f0f4f8;
            --surface: #ffffff;
            --text:    #0f172a;
            --text-2:  #475569;
            --radius:  14px;
            --shadow:  0 4px 24px rgba(11,45,78,.09);
            --font:    'DM Sans', sans-serif;
            --mono:    'DM Mono', monospace;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            font-size: 15px;
        }

        /* ── Layout ──────────────────────────────────────────────── */
        .page-wrap { display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 32px 36px; overflow-x: hidden; }

        /* ── Page Header ─────────────────────────────────────────── */
        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 16px;
            flex-wrap: wrap;
        }
        .page-header-left h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--navy);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .page-header-left p {
            color: var(--slate);
            font-size: 0.9rem;
            margin-top: 4px;
        }
        .admin-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--navy);
            color: #fff;
            padding: 8px 16px 8px 10px;
            border-radius: 99px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        .admin-badge-icon {
            width: 32px; height: 32px;
            background: var(--teal);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
        }

        /* ── Summary Cards ───────────────────────────────────────── */
        .summary-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .summary-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow);
        }
        .summary-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }
        .summary-icon.pending  { background: #fef3c7; }
        .summary-icon.approved { background: var(--green-lt); }
        .summary-icon.rejected { background: var(--rose-lt); }
        .summary-card .count {
            font-size: 2rem; font-weight: 700; line-height: 1;
            color: var(--navy);
        }
        .summary-card .label {
            font-size: 0.8rem; color: var(--slate); font-weight: 500;
            text-transform: uppercase; letter-spacing: 0.06em; margin-top: 2px;
        }

        /* ── Alert / Flash Message ───────────────────────────────── */
        .flash {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 14px 18px; border-radius: var(--radius);
            margin-bottom: 24px;
            font-size: 0.9rem; font-weight: 500;
            border-left: 4px solid transparent;
            animation: slideDown .25s ease;
        }
        @keyframes slideDown {
            from { opacity:0; transform: translateY(-6px); }
            to   { opacity:1; transform: translateY(0); }
        }
        .flash-icon { font-size: 1.1rem; margin-top: 1px; flex-shrink: 0; }
        .flash.success { background:#ecfdf5; color:#166534; border-color:#16a34a; }
        .flash.warning { background:#fef3c7; color:#854d0e; border-color:#f59e0b; }
        .flash.danger  { background:var(--rose-lt); color:#9f1239; border-color:var(--rose); }
        .flash.info    { background:#eff6ff; color:#1e40af; border-color:#3b82f6; }

        /* ── Main Card ───────────────────────────────────────────── */
        .review-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .review-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 26px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, var(--navy) 0%, #1a4a7a 100%);
        }
        .review-card-header h2 {
            font-size: 1.1rem; font-weight: 700; color: #fff;
            letter-spacing: -0.01em;
        }
        .review-card-header p {
            font-size: 0.8rem; color: rgba(255,255,255,.6); margin-top: 2px;
        }
        .header-pill {
            background: rgba(255,255,255,.15);
            color: #fff; font-size: 0.78rem; font-weight: 600;
            padding: 5px 12px; border-radius: 99px;
        }

        /* ── Empty State ─────────────────────────────────────────── */
        .empty-state {
            text-align: center; padding: 64px 24px;
            color: var(--slate);
        }
        .empty-state-icon { font-size: 3rem; margin-bottom: 12px; opacity: .5; }
        .empty-state h3 { font-size: 1rem; font-weight: 600; color: var(--text-2); }
        .empty-state p  { font-size: 0.875rem; margin-top: 4px; }

        /* ── Application Rows ────────────────────────────────────── */
        .app-list { display: flex; flex-direction: column; }
        .app-row {
            display: grid;
            grid-template-columns: 56px 1fr auto auto;
            gap: 0;
            border-bottom: 1px solid var(--border);
            transition: background .15s;
        }
        .app-row:last-child { border-bottom: none; }
        .app-row:hover { background: #f8fafc; }
        .app-row.is-pending  { border-left: 3px solid var(--amber); }
        .app-row.is-approved { border-left: 3px solid var(--green); }
        .app-row.is-rejected { border-left: 3px solid var(--rose); }

        .app-cell { padding: 20px 22px; }

        /* ID column */
        .app-cell.cell-id {
            display: flex; align-items: flex-start; justify-content: center;
            padding-top: 24px;
        }
        .id-badge {
            font-family: var(--mono);
            font-size: 0.72rem; font-weight: 500;
            color: var(--slate);
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 3px 7px;
            line-height: 1;
        }

        /* Info column */
        .app-cell.cell-info { border-right: 1px solid var(--border); }
        .clinic-name {
            font-size: 1rem; font-weight: 700; color: var(--navy);
            margin-bottom: 6px;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3px 16px;
            margin-bottom: 14px;
        }
        .meta-item {
            font-size: 0.8rem; color: var(--text-2);
            display: flex; align-items: center; gap: 5px;
        }
        .meta-item svg { flex-shrink: 0; opacity: .6; }
        .tier-pill {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.72rem; font-weight: 700; letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 3px 10px; border-radius: 99px;
            background: #e0f2fe; color: #0369a1;
            border: 1px solid #bae6fd;
        }

        /* Documents */
        .docs-label {
            font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; color: var(--slate);
            margin-bottom: 8px; margin-top: 16px;
        }
        .docs-grid { display: flex; flex-wrap: wrap; gap: 8px; }
        .doc-thumb {
            border-radius: 10px; overflow: hidden;
            border: 1.5px solid var(--border);
            transition: border-color .15s, transform .15s;
        }
        .doc-thumb:hover { border-color: var(--teal); transform: scale(1.03); }
        .doc-thumb img {
            display: block; width: 96px; height: 72px;
            object-fit: cover;
        }
        .doc-file {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px;
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 0.8rem; color: var(--navy); font-weight: 500;
            text-decoration: none;
            transition: border-color .15s, background .15s;
        }
        .doc-file:hover { border-color: var(--teal); background: var(--teal-lt); }
        .no-docs {
            font-size: 0.8rem; color: #94a3b8; font-style: italic;
        }

        /* Status column */
        .app-cell.cell-status {
            display: flex; flex-direction: column;
            align-items: flex-start; justify-content: flex-start;
            gap: 6px;
            border-right: 1px solid var(--border);
            min-width: 110px;
        }
        .status-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 99px;
            font-size: 0.78rem; font-weight: 700;
            letter-spacing: 0.04em;
        }
        .status-chip::before {
            content: ''; width: 6px; height: 6px; border-radius: 50%;
        }
        .status-chip.pending  { background: #fef3c7; color: #92400e; }
        .status-chip.pending::before { background: var(--amber); }
        .status-chip.approved { background: var(--green-lt); color: #14532d; }
        .status-chip.approved::before { background: var(--green); }
        .status-chip.rejected { background: var(--rose-lt); color: #9f1239; }
        .status-chip.rejected::before { background: var(--rose); }
        .archived-tag {
            font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em;
            color: var(--rose); background: var(--rose-lt);
            border: 1px solid #fecdd3;
            padding: 2px 8px; border-radius: 99px;
        }

        /* Actions column */
        .app-cell.cell-actions { min-width: 220px; }
        .action-form { display: flex; flex-direction: column; gap: 10px; }
        .action-notes {
            width: 100%;
            font-family: var(--font);
            font-size: 0.85rem;
            color: var(--text);
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            resize: vertical;
            min-height: 76px;
            transition: border-color .15s, box-shadow .15s;
        }
        .action-notes:focus {
            outline: none;
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(14,138,124,.12);
        }
        .action-notes::placeholder { color: #94a3b8; }
        .action-buttons { display: flex; gap: 8px; }
        .btn {
            flex: 1; padding: 9px 14px;
            border: none; border-radius: 10px;
            font-family: var(--font); font-size: 0.85rem; font-weight: 700;
            cursor: pointer; letter-spacing: 0.01em;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            transition: filter .15s, transform .1s, box-shadow .15s;
        }
        .btn:active { transform: scale(.97); }
        .btn-approve {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            box-shadow: 0 2px 8px rgba(22,163,74,.3);
        }
        .btn-approve:hover { filter: brightness(1.08); box-shadow: 0 4px 14px rgba(22,163,74,.4); }
        .btn-reject {
            background: linear-gradient(135deg, #e11d48, #be123c);
            color: #fff;
            box-shadow: 0 2px 8px rgba(225,29,72,.25);
        }
        .btn-reject:hover { filter: brightness(1.08); box-shadow: 0 4px 14px rgba(225,29,72,.35); }
        .processed-label {
            font-size: 0.82rem; color: #94a3b8; font-style: italic;
            display: flex; align-items: center; gap: 6px;
        }

        /* ── Responsive ──────────────────────────────────────────── */
        @media (max-width: 900px) {
            .main-content { padding: 20px 16px; }
            .summary-row { grid-template-columns: 1fr; gap: 10px; }
            .app-row { grid-template-columns: 1fr; }
            .app-cell { border-right: none !important; }
            .app-cell.cell-id { padding: 16px 16px 0; justify-content: flex-start; }
            .meta-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="page-wrap">
    <?php include __DIR__ . '/includes/sidebar_superadmin.php'; ?>

    <main class="main-content">

        <!-- Page Header -->
        <header class="page-header">
            <div class="page-header-left">
                <h1>Registration Approvals</h1>
                <p>Review new clinic applications and uploaded verification documents.</p>
            </div>
            <div class="admin-badge">
                <div class="admin-badge-icon">🛡️</div>
                Super Admin
            </div>
        </header>

        <!-- Summary Row -->
        <div class="summary-row">
            <div class="summary-card">
                <div class="summary-icon pending">⏳</div>
                <div>
                    <div class="count"><?php echo $pendingCount; ?></div>
                    <div class="label">Awaiting Review</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon approved">✅</div>
                <div>
                    <div class="count"><?php echo $approvedCount; ?></div>
                    <div class="label">Approved</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon rejected">❌</div>
                <div>
                    <div class="count"><?php echo $rejectedCount; ?></div>
                    <div class="label">Rejected</div>
                </div>
            </div>
        </div>

        <!-- Flash Message -->
        <?php if ($message):
            $flashIcon = match($alertType) {
                'success' => '✔',
                'warning' => '⚠',
                'danger'  => '✖',
                default   => 'ℹ',
            };
        ?>
            <div class="flash <?php echo htmlspecialchars($alertType, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="flash-icon"><?php echo $flashIcon; ?></span>
                <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <!-- Applications Card -->
        <div class="review-card">
            <div class="review-card-header">
                <div>
                    <h2>Clinic Applications</h2>
                    <p>Sorted by status — pending applications appear first.</p>
                </div>
                <span class="header-pill"><?php echo count($reviewRequests); ?> total</span>
            </div>

            <?php if (empty($reviewRequests)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <h3>No applications found</h3>
                    <p>There are no pending, approved, or rejected applications at this time.</p>
                </div>
            <?php else: ?>
                <div class="app-list">
                    <?php foreach ($reviewRequests as $request):
                        $statusClass = strtolower($request['registration_status']);
                        $rowClass    = 'is-' . $statusClass;

                        // Fetch tenant documents
                        $tenantDocs = [];
                        $tid = intval($request['tenant_id']);
                        $docsStmt = $conn->prepare("SELECT id, document_name, file_path, file_type FROM tenant_documents WHERE tenant_id = ? ORDER BY id DESC");
                        if ($docsStmt) {
                            $docsStmt->bind_param('i', $tid);
                            $docsStmt->execute();
                            $docsResult = $docsStmt->get_result();
                            while ($d = $docsResult->fetch_assoc()) { $tenantDocs[] = $d; }
                            $docsStmt->close();
                        }
                    ?>
                    <div class="app-row <?php echo $rowClass; ?>">

                        <!-- ID -->
                        <div class="app-cell cell-id">
                            <span class="id-badge">#<?php echo (int)$request['tenant_id']; ?></span>
                        </div>

                        <!-- Info + Documents -->
                        <div class="app-cell cell-info">
                            <div class="clinic-name"><?php echo htmlspecialchars($request['company_name'], ENT_QUOTES, 'UTF-8'); ?></div>

                            <div class="meta-grid">
                                <?php if (!empty($request['owner_name'])): ?>
                                <div class="meta-item">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                                    <?php echo htmlspecialchars($request['owner_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($request['contact_email'])): ?>
                                <div class="meta-item">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/></svg>
                                    <?php echo htmlspecialchars($request['contact_email'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($request['phone'])): ?>
                                <div class="meta-item">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 11.56 19a19.45 19.45 0 0 1-6-6 19.79 19.79 0 0 1-2.92-8.23A2 2 0 0 1 4.63 2H7.7a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    <?php echo htmlspecialchars($request['phone'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($request['subdomain_slug'])): ?>
                                <div class="meta-item">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                    <?php echo htmlspecialchars($request['subdomain_slug'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($request['subscription_tier'])): ?>
                            <span class="tier-pill">
                                📦 <?php echo htmlspecialchars(ucfirst($request['subscription_tier']), ENT_QUOTES, 'UTF-8'); ?>
                                &nbsp;·&nbsp; <?php echo (int)$request['subscription_duration']; ?> mo
                            </span>
                            <?php endif; ?>

                            <!-- Documents -->
                            <div class="docs-label">Documents</div>
                            <div class="docs-grid">
                                <?php if (!empty($tenantDocs)): ?>
                                    <?php foreach ($tenantDocs as $d):
                                        $downloadUrl = 'download_tenant_document.php?id=' . urlencode((int)$d['id']);
                                        $docName = htmlspecialchars($d['document_name'], ENT_QUOTES, 'UTF-8');
                                        $ext = strtolower(pathinfo($d['file_path'], PATHINFO_EXTENSION));
                                    ?>
                                        <?php if (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                                            <a href="<?php echo $downloadUrl; ?>" target="_blank" class="doc-thumb" title="<?php echo $docName; ?>">
                                                <img src="<?php echo $downloadUrl; ?>" alt="<?php echo $docName; ?>">
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo $downloadUrl; ?>" target="_blank" class="doc-file">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
                                                <?php echo $docName; ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="no-docs">No documents uploaded.</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="app-cell cell-status">
                            <span class="status-chip <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($request['registration_status'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <?php if ($request['tenant_status'] === 'archived'): ?>
                                <span class="archived-tag">Archived</span>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div class="app-cell cell-actions">
                            <?php if ($request['registration_status'] === 'PENDING'): ?>
                                <form method="post" class="action-form">
                                    <input type="hidden" name="tenant_id" value="<?php echo (int)$request['tenant_id']; ?>">
                                    <textarea
                                        name="admin_notes"
                                        class="action-notes"
                                        placeholder="Admin notes (optional)…"
                                    ></textarea>
                                    <div class="action-buttons">
                                        <button type="submit" name="action" value="approve" class="btn btn-approve">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            Approve
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-reject">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            Reject
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <span class="processed-label">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    Processed
                                </span>
                            <?php endif; ?>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownToggle = document.querySelector('.menu-dropdown-toggle');
        const dropdownItems = document.querySelector('.menu-dropdown-items');
        const dropdown = document.querySelector('.menu-dropdown');

        if (dropdownToggle) {
            dropdownToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownItems.style.display =
                    (dropdownItems.style.display === 'none' || !dropdownItems.style.display)
                        ? 'flex' : 'none';
                dropdownToggle.classList.toggle('active');
            });
        }
        if (dropdownItems) {
            dropdownItems.addEventListener('click', e => e.stopPropagation());
        }
        document.addEventListener('click', function(e) {
            if (dropdown && !dropdown.contains(e.target)) {
                if (dropdownItems) dropdownItems.style.display = 'none';
                if (dropdownToggle) dropdownToggle.classList.remove('active');
            }
        });

        // Confirm before rejecting
        document.querySelectorAll('.btn-reject').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Reject this application? This cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    });
</script>
</body>
</html>
