<?php
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('dentist');

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
$tenantData = $sessionManager->getTenantData();
$tenantName = $tenantData['tenant_name'] ?? '';
$tenantId = $sessionManager->getTenantId();
$userId = $sessionManager->getUserId();

$message = '';
$messageType = 'info';

$currentUser = null;
$stmt = mysqli_prepare($conn, "SELECT username, email, password, role FROM users WHERE user_id = ? AND tenant_id = ? LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $userId, $tenantId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $currentUser = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
}

if (!$currentUser || strcasecmp((string)$currentUser['role'], 'Dentist') !== 0) {
    http_response_code(403);
    die('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = trim((string)($_POST['username'] ?? ''));
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($newUsername === '') {
        $message = 'Username is required.';
        $messageType = 'error';
    } elseif (strlen($newUsername) < 3) {
        $message = 'Username must be at least 3 characters.';
        $messageType = 'error';
    } else {
        $duplicateStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE tenant_id = ? AND username = ? AND user_id <> ? LIMIT 1");
        if ($duplicateStmt) {
            mysqli_stmt_bind_param($duplicateStmt, "isi", $tenantId, $newUsername, $userId);
            mysqli_stmt_execute($duplicateStmt);
            $dupResult = mysqli_stmt_get_result($duplicateStmt);
            if ($dupResult && mysqli_num_rows($dupResult) > 0) {
                $message = 'That username is already in use in this clinic.';
                $messageType = 'error';
            }
            mysqli_stmt_close($duplicateStmt);
        }
    }

    $changePassword = ($currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '');
    if ($message === '' && $changePassword) {
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $message = 'Fill in all password fields to change your password.';
            $messageType = 'error';
        } elseif (!password_verify($currentPassword, (string)$currentUser['password'])) {
            $message = 'Current password is incorrect.';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 8) {
            $message = 'New password must be at least 8 characters.';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'New password and confirmation do not match.';
            $messageType = 'error';
        }
    }

    if ($message === '') {
        if ($changePassword) {
            $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $updateStmt = mysqli_prepare($conn, "UPDATE users SET username = ?, password = ? WHERE user_id = ? AND tenant_id = ?");
            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, "ssii", $newUsername, $newPasswordHash, $userId, $tenantId);
            }
        } else {
            $updateStmt = mysqli_prepare($conn, "UPDATE users SET username = ? WHERE user_id = ? AND tenant_id = ?");
            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, "sii", $newUsername, $userId, $tenantId);
            }
        }

        if (isset($updateStmt) && $updateStmt && mysqli_stmt_execute($updateStmt)) {
            mysqli_stmt_close($updateStmt);
            $message = 'Account credentials updated successfully.';
            $messageType = 'success';

            $refreshStmt = mysqli_prepare($conn, "SELECT username, email, password, role FROM users WHERE user_id = ? AND tenant_id = ? LIMIT 1");
            if ($refreshStmt) {
                mysqli_stmt_bind_param($refreshStmt, "ii", $userId, $tenantId);
                mysqli_stmt_execute($refreshStmt);
                $refreshResult = mysqli_stmt_get_result($refreshStmt);
                $currentUser = $refreshResult ? mysqli_fetch_assoc($refreshResult) : $currentUser;
                mysqli_stmt_close($refreshStmt);
            }

            $sessionPayload = [
                'tenant_id' => (int)$tenantData['tenant_id'],
                'tenant_name' => (string)($tenantData['tenant_name'] ?? ''),
                'tenant_code' => (string)($tenantData['tenant_code'] ?? ''),
                'role' => 'Dentist',
                'user_id' => (int)$userId,
                'username' => (string)$currentUser['username'],
                'email' => (string)$currentUser['email'],
            ];
            $sessionManager->loginTenantUser($tenantSlug, $sessionPayload);
        } else {
            if (isset($updateStmt) && $updateStmt) {
                mysqli_stmt_close($updateStmt);
            }
            $message = 'Failed to update account credentials.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Dentist Account Settings</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
        .settings-form { max-width: 520px; margin: 0 auto; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.45rem; font-weight: 600; color: #1f2937; }
        .form-group input { width: 100%; padding: 0.7rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95rem; }
        .form-group input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14); }
        .helper { font-size: 0.82rem; color: #6b7280; margin-top: 0.3rem; }
        .section-title { margin: 1.5rem 0 0.6rem; font-weight: 700; color: #0f172a; }
    </style>
</head>
<body>
    <div class="t-wrap">
        <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

        <main class="t-main">
            <div class="t-header" style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h1 class="t-title">Account Settings</h1>
                    <p class="t-subtitle">Change your dentist username and password.</p>
                </div>
                <?php renderDateClock(); ?>
            </div>

            <div class="t-content">
                <?php if ($message !== ''): ?>
                    <div class="t-alert t-alert-<?php echo h($messageType); ?>" style="margin-bottom: 1.4rem;">
                        <?php echo h($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="settings-form" autocomplete="off">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" required value="<?php echo h((string)($currentUser['username'] ?? '')); ?>">
                        <div class="helper">Unique per clinic, minimum 3 characters.</div>
                    </div>

                    <div class="section-title">Change Password (Optional)</div>

                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input id="new_password" name="new_password" type="password" autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password">
                        <div class="helper">At least 8 characters.</div>
                    </div>

                    <div style="margin-top: 1.25rem; text-align:center;">
                        <button type="submit" class="t-btn t-btnPrimary">Save Account Changes</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
        <?php printDateClockScript(); ?>
    </script>
</body>
</html>
