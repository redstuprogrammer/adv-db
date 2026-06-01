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

$message = '';
$alertType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $queueId = intval($_POST['queue_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if ($queueId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $newStatus = $action === 'approve' ? 'APPROVED' : 'REJECTED';
        $tenantStatus = $action === 'approve' ? 'APPROVED' : 'PENDING_ADMIN_REVIEW';

        $stmt = $conn->prepare("SELECT tenant_id, clinic_name FROM document_review_queue WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $queueId);
        $stmt->execute();
        $queueRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($queueRow) {
            $tenantId = intval($queueRow['tenant_id']);
            $updateQueue = $conn->prepare(
                "UPDATE document_review_queue SET status = ?, reviewed_at = NOW(), reviewed_by_admin_id = ?, admin_notes = ? WHERE id = ?"
            );
            $adminId = $sessionManager->getUserId() ?? 0;
            $updateQueue->bind_param('sisi', $newStatus, $adminId, $adminNotes, $queueId);
            if ($updateQueue->execute()) {
                if ($tenantId > 0) {
                    $updateTenant = $conn->prepare(
                        "UPDATE tenants SET registration_status = ? WHERE id = ?"
                    );
                    $updateTenant->bind_param('si', $tenantStatus, $tenantId);
                    $updateTenant->execute();
                    $updateTenant->close();
                }
                $message = $action === 'approve'
                    ? 'Document review approved and tenant registration status updated.'
                    : 'Document review rejected. Tenant remains pending admin verification.';
                $alertType = $action === 'approve' ? 'success' : 'warning';
            } else {
                $message = 'Unable to update the review request. Please try again.';
                $alertType = 'danger';
            }
            $updateQueue->close();
        } else {
            $message = 'Review request not found.';
            $alertType = 'danger';
        }
    } else {
        $message = 'Invalid review action.';
        $alertType = 'danger';
    }
}

$reviewRequests = [];
$query = "SELECT q.*, t.company_name, t.subdomain_slug, t.contact_email, t.phone, t.owner_name, t.status AS tenant_status, t.registration_status
          FROM document_review_queue q
          LEFT JOIN tenants t ON t.id = q.tenant_id
          ORDER BY FIELD(q.status, 'PENDING', 'APPROVED', 'REJECTED'), q.created_at DESC";
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
        .code-block { white-space: pre-wrap; word-break: break-word; background: #f8fafc; padding: 14px; border-radius: 14px; border: 1px solid #e2e8f0; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 0.85rem; }
    </style>
</head>
<body>
    <main class="container mx-auto px-4 py-10">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-slate-500 uppercase tracking-[0.3em] mb-2">Superadmin</p>
                <h1 class="text-3xl font-bold text-slate-900">Document Review Queue</h1>
                <p class="text-slate-600 mt-2 max-w-2xl">Review OCR verification cases flagged for manual approval or rejection.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="superadmin_dash.php" class="px-5 py-3 text-sm font-semibold bg-slate-900 text-white rounded-xl hover:bg-slate-700">Back to Dashboard</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="status-box <?php echo htmlspecialchars($alertType, ENT_QUOTES, 'UTF-8'); ?> mb-6">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="review-card">
            <h2 class="text-xl font-bold text-slate-900 mb-4">Queued Requests</h2>

            <?php if (empty($reviewRequests)): ?>
                <div class="status-box info">There are no document review requests at this time.</div>
            <?php else: ?>
                <table class="review-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Clinic / Tenant</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>Admin Notes</th>
                            <th>Model Output</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviewRequests as $request): ?>
                            <?php
                                $modelOutput = null;
                                if (!empty($request['model_output'])) {
                                    $decoded = json_decode($request['model_output'], true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $modelOutput = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                    } else {
                                        $modelOutput = $request['model_output'];
                                    }
                                }
                                $statusClass = strtolower($request['status']);

                                // Fetch uploaded tenant documents for preview (if any)
                                $tenantDocs = [];
                                $tenantId = intval($request['tenant_id'] ?? 0);
                                if ($tenantId > 0) {
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
                                }
                            ?>
                            <tr>
                                <td class="align-top text-sm text-slate-700"><?php echo (int)$request['id']; ?></td>
                                <td class="align-top text-sm text-slate-700">
                                    <div class="font-semibold"><?php echo htmlspecialchars($request['clinic_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php if (!empty($request['company_name'])): ?>
                                        <div class="text-xs text-slate-500">Tenant: <?php echo htmlspecialchars($request['company_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
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
                                    <?php if (!empty($request['tenant_status'])): ?>
                                        <div class="text-xs text-slate-500">Tenant Status: <?php echo htmlspecialchars($request['tenant_status'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($request['registration_status'])): ?>
                                        <div class="text-xs text-slate-500">Registration Status: <?php echo htmlspecialchars($request['registration_status'], ENT_QUOTES, 'UTF-8'); ?></div>
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
                                    <span class="review-chip <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($request['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <div class="text-xs text-slate-500 mt-1">Created: <?php echo htmlspecialchars($request['created_at'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </td>
                                <td class="align-top text-sm text-slate-700"><?php echo nl2br(htmlspecialchars($request['reason'] ?? 'No reason provided.', ENT_QUOTES, 'UTF-8')); ?></td>
                                <td class="align-top text-sm text-slate-700"><?php echo nl2br(htmlspecialchars($request['admin_notes'] ?? '-', ENT_QUOTES, 'UTF-8')); ?></td>
                                <td class="align-top text-sm text-slate-700">
                                    <?php if ($modelOutput): ?>
                                        <div class="code-block"><?php echo htmlspecialchars($modelOutput, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php else: ?>
                                        <span class="text-slate-500">No output available.</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-top">
                                    <?php if ($request['status'] === 'PENDING'): ?>
                                        <form method="post" class="space-y-3">
                                            <input type="hidden" name="queue_id" value="<?php echo (int)$request['id']; ?>">
                                            <label class="block text-xs text-slate-500">Admin notes (optional)</label>
                                            <textarea name="admin_notes" class="review-textarea" placeholder="Enter notes for this decision..."><?php echo htmlspecialchars($request['admin_notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            <div class="review-actions">
                                                <button type="submit" name="action" value="approve" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-semibold hover:bg-emerald-700">Approve</button>
                                                <button type="submit" name="action" value="reject" class="px-4 py-2 bg-rose-600 text-white rounded-xl text-sm font-semibold hover:bg-rose-700">Reject</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-sm text-slate-500">Review completed.</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
