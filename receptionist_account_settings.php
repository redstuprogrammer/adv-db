<?php
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('receptionist');

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

if (!$currentUser || strcasecmp((string)$currentUser['role'], 'Receptionist') !== 0) {
    http_response_code(403);
    die('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = trim((string)($_POST['username'] ?? ''));
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    $usernameChanged = ($newUsername !== '' && $newUsername !== $currentUser['username']);
    $passwordChanged = ($currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '');

    if (!$usernameChanged && !$passwordChanged) {
        $message = 'No changes were detected.';
        $messageType = 'info';
    } else {
        // Validate Username if changed
        if ($usernameChanged) {
            if (strlen($newUsername) < 3) {
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
        }

        // Validate Password if changed
        if ($message === '' && $passwordChanged) {
            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                $message = 'To change password, provide current, new, and confirmation passwords.';
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
            $updateQueries = [];
            $params = [];
            $types = "";

            if ($usernameChanged) {
                $updateQueries[] = "username = ?";
                $params[] = $newUsername;
                $types .= "s";
            }
            if ($passwordChanged) {
                $updateQueries[] = "password = ?";
                $params[] = password_hash($newPassword, PASSWORD_BCRYPT);
                $types .= "s";
            }

            if (!empty($updateQueries)) {
                $sql = "UPDATE users SET " . implode(", ", $updateQueries) . " WHERE user_id = ? AND tenant_id = ?";
                $params[] = $userId;
                $params[] = $tenantId;
                $types .= "ii";

                $updateStmt = mysqli_prepare($conn, $sql);
                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, $types, ...$params);
                    if (mysqli_stmt_execute($updateStmt)) {
                        $message = 'Account updated successfully.';
                        $messageType = 'success';
                        
                        // Refresh current user data
                        $refreshStmt = mysqli_prepare($conn, "SELECT username, email, password, role FROM users WHERE user_id = ? AND tenant_id = ? LIMIT 1");
                        if ($refreshStmt) {
                            mysqli_stmt_bind_param($refreshStmt, "ii", $userId, $tenantId);
                            mysqli_stmt_execute($refreshStmt);
                            $refreshResult = mysqli_stmt_get_result($refreshStmt);
                            $currentUser = $refreshResult ? mysqli_fetch_assoc($refreshResult) : $currentUser;
                            mysqli_stmt_close($refreshStmt);
                        }

                        // Update session
                        $sessionPayload = [
                            'tenant_id' => (int)$tenantData['tenant_id'],
                            'tenant_name' => (string)($tenantData['tenant_name'] ?? ''),
                            'tenant_code' => (string)($tenantData['tenant_code'] ?? ''),
                            'role' => 'Receptionist',
                            'user_id' => (int)$userId,
                            'username' => (string)$currentUser['username'],
                            'email' => (string)$currentUser['email'],
                        ];
                        $sessionManager->loginTenantUser($tenantSlug, $sessionPayload);
                    } else {
                        $message = 'Database update failed.';
                        $messageType = 'error';
                    }
                    mysqli_stmt_close($updateStmt);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Account Settings</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
        :root {
            --accent: #0d3b66;
            --border: #e2e8f0;
            --bg: #f8fafc;
        }
        .settings-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 700; color: #334155; font-size: 13px; text-transform: uppercase; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        .form-group input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.1); }
        .helper { font-size: 12px; color: #64748b; margin-top: 5px; }
        .section-title { margin: 2rem 0 1rem; font-weight: 800; color: var(--accent); font-size: 16px; border-bottom: 2px solid var(--bg); padding-bottom: 8px; }
        .save-btn {
            background: var(--accent);
            color: white;
            padding: 14px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            font-size: 15px;
            transition: background 0.2s;
        }
        .save-btn:hover { background: #0a2d4f; }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <div class="tenant-main-content">
      <!-- Header Bar -->
      <div class="tenant-header-bar">
        <div class="tenant-header-title">
            Account Settings
        </div>
        <?php renderDateClock(); ?>
      </div>

      <div class="settings-container">
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" placeholder="Enter username to update">
                <div class="helper">Your unique login name within this clinic.</div>
            </div>

            <div class="section-title">Security Update</div>

            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input id="current_password" name="current_password" type="password" placeholder="Required only if changing password">
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input id="new_password" name="new_password" type="password" placeholder="At least 8 characters">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input id="confirm_password" name="confirm_password" type="password">
            </div>

            <div style="margin-top: 2rem;">
                <button type="submit" class="save-btn">Save Account Changes</button>
            </div>
        </form>
      </div>
    </div>
  </div>
    <script>
        <?php printDateClockScript(); ?>
    </script>
</body>
</html>
