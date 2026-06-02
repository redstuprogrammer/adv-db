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
            margin-bottom: 24px;
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
            font-size: 0.9rem;
            color: var(--sa-muted);
        }

        .sa-profile span {
            font-weight: 600;
        }

        .sa-profile-avatar {
            width: 35px;
            height: 35px;
            border-radius: 999px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sa-card {
            background: #ffffff;
            border: 1px solid var(--sa-border);
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08);
            transition: all 0.2s ease;
        }

        .sa-card:hover {
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.12);
            border-color: var(--sa-primary);
        }

        .sa-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--sa-border);
            padding-bottom: 16px;
        }

        .sa-card-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--sa-primary);
            letter-spacing: -0.3px;
            margin: 0;
        }

        .sa-card-subtitle {
            font-size: 0.9rem;
            color: var(--sa-muted);
            margin-top: 6px;
            font-weight: 500;
        }

        .status-box {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid;
            font-size: 0.95rem;
        }

        .status-box.success { background: #ecfdf5; color: #166534; border-color: #86efac; }
        .status-box.warning { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .status-box.danger { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .status-box.info { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }

        .review-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        .review-table th {
            background: #f8fafc;
            color: #0f172a;
            text-align: left;
            padding: 14px 16px;
            font-weight: 700;
            border-bottom: 2px solid var(--sa-border);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .review-table td {
            padding: 16px;
            border-bottom: 1px solid var(--sa-border);
            vertical-align: top;
        }

        .review-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .review-chip {
            display: inline-flex;
            gap: 0.35rem;
            padding: 0.5rem 0.85rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .review-chip.pending { background: #fde68a; color: #92400e; }
        .review-chip.approved { background: #dcfce7; color: #166534; }
        .review-chip.rejected { background: #fee2e2; color: #991b1b; }

        .review-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .review-actions form {
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .review-textarea {
            width: 100%;
            min-height: 100px;
            border: 1px solid var(--sa-border);
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 0.9rem;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            resize: vertical;
        }

        .review-textarea:focus {
            outline: none;
            border-color: var(--sa-primary);
            box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.1);
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
        }

        .btn-approve, .btn-reject {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-approve {
            background: #22c55e;
            color: white;
        }

        .btn-approve:hover {
            background: #16a34a;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }

        .btn-reject {
            background: #ef4444;
            color: white;
        }

        .btn-reject:hover {
            background: #dc2626;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: var(--sa-muted);
            font-size: 1rem;
        }

        .tenant-info-section {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            margin: 8px 0;
        }

        .tenant-info-row {
            display: flex;
            gap: 4px;
            font-size: 0.85rem;
            color: #475569;
            margin: 6px 0;
        }

        .tenant-info-label {
            font-weight: 600;
            color: var(--sa-primary);
            min-width: 70px;
        }

        .document-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .document-thumbnail {
            max-width: 120px;
            max-height: 90px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid var(--sa-border);
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .document-thumbnail:hover {
            transform: scale(1.05);
        }

        .document-link {
            padding: 6px 12px;
            background: #f1f5f9;
            border-radius: 6px;
            border: 1px solid var(--sa-border);
            font-size: 0.85rem;
            text-decoration: none;
            color: var(--sa-primary);
            display: inline-block;
            transition: all 0.2s ease;
        }

        .document-link:hover {
            background: var(--sa-primary);
            color: white;
        }
    </style>
</head>
<body>

<div class="container">
    <?php include __DIR__ . '/includes/sidebar_superadmin.php'; ?>

    <main class="main-content">
        <header class="sa-main-header">
            <div>
                <h1>Registration Approvals</h1>
                <span>Review new clinic applications and documents</span>
            </div>
            <div class="sa-profile">
                <span>Welcome, <strong>Super Admin</strong></span>
                <div class="sa-profile-avatar">🛡️</div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="status-box <?php echo htmlspecialchars($alertType, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="sa-card">
            <div class="sa-card-header">
                <div>
                    <h2 class="sa-card-title">Clinic Applications</h2>
                    <p class="sa-card-subtitle">Manage and review pending clinic registration requests</p>
                </div>
            </div>

            <?php if (empty($reviewRequests)): ?>
                <div class="empty-state">
                    <p style="font-size: 1.1rem; margin-bottom: 8px;">ℹ️</p>
                    <p>There are no pending applications at this time.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="review-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Clinic Information</th>
                                <th style="width: 100px;">Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviewRequests as $request): ?>
                                <?php
                                    $statusClass = strtolower($request['registration_status']);
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
                                    <td style="text-align: center; color: var(--sa-muted); font-weight: 600;">
                                        #<?php echo (int)$request['tenant_id']; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: #0f172a; font-size: 1rem; margin-bottom: 12px;">
                                            <?php echo htmlspecialchars($request['company_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <div class="tenant-info-section">
                                            <?php if (!empty($request['subdomain_slug'])): ?>
                                                <div class="tenant-info-row">
                                                    <span class="tenant-info-label">Slug:</span>
                                                    <span><?php echo htmlspecialchars($request['subdomain_slug'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($request['owner_name'])): ?>
                                                <div class="tenant-info-row">
                                                    <span class="tenant-info-label">Owner:</span>
                                                    <span><?php echo htmlspecialchars($request['owner_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($request['contact_email'])): ?>
                                                <div class="tenant-info-row">
                                                    <span class="tenant-info-label">Email:</span>
                                                    <span><?php echo htmlspecialchars($request['contact_email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($request['phone'])): ?>
                                                <div class="tenant-info-row">
                                                    <span class="tenant-info-label">Phone:</span>
                                                    <span><?php echo htmlspecialchars($request['phone'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($request['subscription_tier'])): ?>
                                                <div class="tenant-info-row">
                                                    <span class="tenant-info-label">Plan:</span>
                                                    <span><?php echo htmlspecialchars($request['subscription_tier'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int)$request['subscription_duration']; ?> mo.)</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($tenantDocs)): ?>
                                            <div style="margin-top: 12px;">
                                                <div style="font-size: 0.85rem; font-weight: 600; color: var(--sa-primary); margin-bottom: 8px;">📎 Documents:</div>
                                                <div class="document-preview">
                                                    <?php foreach ($tenantDocs as $d): ?>
                                                        <?php
                                                            $downloadUrl = 'download_tenant_document.php?id=' . urlencode((int)$d['id']);
                                                            $docName = htmlspecialchars($d['document_name'], ENT_QUOTES, 'UTF-8');
                                                            $ext = strtolower(pathinfo($d['file_path'], PATHINFO_EXTENSION));
                                                        ?>
                                                        <?php if (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                                                            <a href="<?php echo $downloadUrl; ?>" target="_blank" title="<?php echo $docName; ?>">
                                                                <img src="<?php echo $downloadUrl; ?>" class="document-thumbnail" alt="<?php echo $docName; ?>">
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="<?php echo $downloadUrl; ?>" target="_blank" class="document-link">
                                                                📄 <?php echo $docName; ?>
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div style="margin-top: 12px; font-size: 0.85rem; color: var(--sa-muted);">
                                                No uploaded documents
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="review-chip <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($request['registration_status'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                        <?php if ($request['tenant_status'] === 'archived'): ?>
                                            <div style="font-size: 0.75rem; color: #dc2626; font-weight: 700; margin-top: 6px;">Archived</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($request['registration_status'] === 'PENDING'): ?>
                                            <form method="post" class="review-actions">
                                                <input type="hidden" name="tenant_id" value="<?php echo (int)$request['tenant_id']; ?>">
                                                <textarea name="admin_notes" class="review-textarea" placeholder="Add optional admin notes..."></textarea>
                                                <div class="action-buttons">
                                                    <button type="submit" name="action" value="approve" class="btn-approve">✓ Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn-reject">✕ Reject</button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <span style="font-size: 0.9rem; color: var(--sa-muted);">Already processed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
