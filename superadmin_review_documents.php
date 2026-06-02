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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Document Review | OralSync</title>
    <link rel="stylesheet" href="style1.css">
    <link rel="stylesheet" href="tenant_style.css">
    <style>
        :root {
            --sa-primary: #0d3b66;
            --sa-muted: #64748b;
            --sa-border: #e2e8f0;
            --sa-bg: #f8fafc;
        }

        body {
            background-color: var(--sa-bg);
            color: #0f172a;
        }

        .sa-main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            margin-bottom: 10px;
        }

        .sa-main-header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--sa-primary);
            margin: 0;
        }

        .sa-main-header span {
            font-size: 0.85rem;
            color: var(--sa-muted);
        }

        .sa-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sa-profile span {
            font-weight: 600;
        }

        .review-table { width: 100%; border-collapse: collapse; }
        .review-table th, .review-table td { padding: 12px 14px; border: 1px solid #e2e8f0; vertical-align: top; }
        .review-table th { background: #f8fafc; color: #0f172a; text-align: left; }
        .review-chip { display: inline-flex; gap: 0.35rem; padding: 0.5rem 0.85rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
        .review-chip.pending { background: #fde68a; color: #92400e; }
        .review-chip.approved { background: #dcfce7; color: #166534; }
        .review-chip.rejected { background: #fee2e2; color: #991b1b; }
        .review-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 28px; box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08); }
        .review-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; }
        .review-actions form { margin: 0; }
        .review-textarea { width: 100%; min-height: 100px; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; font-size: 0.95rem; }
        .status-box { padding: 18px 22px; border-radius: 18px; margin-bottom: 24px; border: 1px solid #cbd5e1; }
        .status-box.success { background: #ecfdf5; color: #166534; border-color: #86efac; }
        .status-box.warning { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .status-box.danger { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .status-box.info { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    </style>
</head>
<body>

<div class="container">
    <?php include __DIR__ . '/includes/sidebar_superadmin.php'; ?>

    <main class="main-content">
        <header class="sa-main-header">
            <div>
                <h1>Registration Approvals</h1>
                <span>Review new clinic applications and documents.</span>
            </div>
            <div class="sa-profile">
                <span>Welcome, <strong>Super Admin</strong></span>
                <div class="sa-profile-avatar">🛡️</div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="status-box <?php echo htmlspecialchars($alertType, ENT_QUOTES, 'UTF-8'); ?> mb-6">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="review-card">
            <h2 class="text-xl font-bold text-slate-900 mb-4">Clinic Applications</h2>

            <?php if (empty($reviewRequests)): ?>
                <div class="status-box info">There are no pending applications at this time.</div>
            <?php else: ?>
                <table class="review-table">
                    <thead>
                        <tr>
                            <th># ID</th>
                            <th>Clinic / Tenant Info</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviewRequests as $request): ?>
                            <?php
                                $statusClass = strtolower($request['registration_status']);

                                // Fetch uploaded tenant documents for preview (if any)
                                $tenantDocs = [];
                                $tenantId = intval($request['tenant_id']);
                                $docsStmt = $conn->prepare("SELECT id, document_name, file_path, file_type FROM tenant_documents WHERE tenant_id = ? ORDER BY id DESC");
                                if ($docsStmt) {
                                    $docsStmt->bind_param('i', $tenantId);
                                    $docsStmt->execute();
                                    $docsResult = $docsStmt->get_result();
                                    while ($d = $docsResult->fetch_assoc()) {
                                        $tenantDocs[] = $d;
                                    }
                                    $docsStmt->close();
                                }
                            ?>
                            <tr>
                                <td class="align-top text-sm text-slate-700"><?php echo (int)$request['tenant_id']; ?></td>
                                <td class="align-top text-sm text-slate-700">
                                    <div class="font-semibold"><?php echo htmlspecialchars($request['company_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php if (!empty($request['subdomain_slug'])): ?>
                                        <div class="text-xs text-slate-500">Slug: <?php echo htmlspecialchars($request['subdomain_slug'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($request['owner_name'])): ?>
                                        <div class="text-xs text-slate-500">Owner: <?php echo htmlspecialchars($request['owner_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($request['contact_email'])): ?>
                                        <div class="text-xs text-slate-500">Email: <?php echo htmlspecialchars($request['contact_email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($request['phone'])): ?>
                                        <div class="text-xs text-slate-500">Phone: <?php echo htmlspecialchars($request['phone'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($request['subscription_tier'])): ?>
                                        <div class="text-xs text-slate-500">Tier: <?php echo htmlspecialchars($request['subscription_tier'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int)$request['subscription_duration']; ?> months)</div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-2 text-xs text-slate-600">Documents:</div>
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        <?php if (!empty($tenantDocs)): ?>
                                            <?php foreach ($tenantDocs as $d): ?>
                                                <?php
                                                    $downloadUrl = 'download_tenant_document.php?id=' . urlencode((int)$d['id']);
                                                    $docName = htmlspecialchars($d['document_name'], ENT_QUOTES, 'UTF-8');
                                                    $ext = strtolower(pathinfo($d['file_path'], PATHINFO_EXTENSION));
                                                ?>
                                                <?php if (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                                                    <a href="<?php echo $downloadUrl; ?>" target="_blank" title="<?php echo $docName; ?>"><img src="<?php echo $downloadUrl; ?>" style="max-width:120px;max-height:90px;border-radius:8px;object-fit:cover;border:1px solid #e6e6e6;" alt="<?php echo $docName; ?>"></a>
                                                <?php else: ?>
                                                    <div style="padding:6px 10px;background:#f8fafc;border-radius:8px;border:1px solid #e6e6e6;font-size:0.9rem;"><a href="<?php echo $downloadUrl; ?>" target="_blank"><?php echo $docName; ?></a></div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-xs text-slate-400">No uploaded documents found.</div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <span class="review-chip <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($request['registration_status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($request['tenant_status'] === 'archived'): ?>
                                        <br><span class="text-xs text-rose-600 font-bold">Archived</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-top">
                                    <?php if ($request['registration_status'] === 'PENDING'): ?>
                                        <form method="post" class="space-y-3">
                                            <input type="hidden" name="tenant_id" value="<?php echo (int)$request['tenant_id']; ?>">
                                            <div class="review-actions">
                                                <button type="submit" name="action" value="approve" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-semibold hover:bg-emerald-700">Approve</button>
                                                <button type="submit" name="action" value="reject" class="px-4 py-2 bg-rose-600 text-white rounded-xl text-sm font-semibold hover:bg-rose-700">Reject</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-sm text-slate-500">Processed.</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                </table>
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
                if (dropdownItems.style.display === 'none' || dropdownItems.style.display === '') {
                    dropdownItems.style.display = 'flex';
                    dropdownToggle.classList.add('active');
                } else {
                    dropdownItems.style.display = 'none';
                    dropdownToggle.classList.remove('active');
                }
            });
        }

        if (dropdownItems) {
            dropdownItems.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        document.addEventListener('click', function(e) {
            if (dropdown && !dropdown.contains(e.target)) {
                if (dropdownItems) dropdownItems.style.display = 'none';
                if (dropdownToggle) dropdownToggle.classList.remove('active');
            }
        });
    });
</script>
</body>
</html>
