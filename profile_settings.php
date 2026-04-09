<?php
/**
 * USER PROFILE SETTINGS
 * Allows users to update their profile information
 */

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

// Role Check Implementation - Ensure user is logged in
$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser();

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function baseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
$tenantData = $sessionManager->getTenantData();
$tenantName = $tenantData['tenant_name'] ?? '';
$tenantId = $sessionManager->getTenantId();
$userId = $sessionManager->getUserId();
$role = $sessionManager->getRole();

$message = '';
$messageType = 'info';

// Get current user data
$userData = null;
if ($role === 'admin') {
    // For admin (tenant owner), get data from tenants table
    try {
        $stmt = mysqli_prepare($conn, "SELECT owner_name, contact_email FROM tenants WHERE tenant_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $tenantId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $userData = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        error_log("Error fetching admin data: " . $e->getMessage());
    }
} else {
    // For dentist/receptionist, get data from users table
    try {
        $stmt = mysqli_prepare($conn, "SELECT first_name, last_name, email, phone FROM users WHERE user_id = ? AND tenant_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $userId, $tenantId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $userData = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        error_log("Error fetching user data: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));

    if ($role === 'admin') {
        // Update tenant owner info
        try {
            $stmt = mysqli_prepare($conn, "UPDATE tenants SET owner_name = ?, contact_email = ? WHERE tenant_id = ?");
            if ($stmt) {
                $fullName = $firstName . ' ' . $lastName;
                mysqli_stmt_bind_param($stmt, "ssi", $fullName, $email, $tenantId);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Profile updated successfully!';
                    $messageType = 'success';
                    // Update session data
                    $sessionManager->loginTenantUser($tenantSlug, array_merge($tenantData, ['username' => $fullName, 'email' => $email]));
                } else {
                    $message = 'Failed to update profile.';
                    $messageType = 'error';
                }
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            $message = 'Error updating profile: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        // Update user info
        try {
            $stmt = mysqli_prepare($conn, "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE user_id = ? AND tenant_id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssssii", $firstName, $lastName, $email, $phone, $userId, $tenantId);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Profile updated successfully!';
                    $messageType = 'success';
                    // Update session data
                    $sessionManager->loginTenantUser($tenantSlug, array_merge($tenantData, ['username' => $firstName . ' ' . $lastName, 'email' => $email]));
                } else {
                    $message = 'Failed to update profile.';
                    $messageType = 'error';
                }
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            $message = 'Error updating profile: ' . $e->getMessage();
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
    <title>Profile Settings - OralSync</title>
    <link rel="stylesheet" href="/tenant_style.css">
    <style>
        .profile-form { max-width: 500px; margin: 0 auto; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    </style>
</head>
<body>
    <div class="t-wrap">
        <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

        <main class="t-main">
            <div class="t-header">
                <h1 class="t-title">Profile Settings</h1>
                <p class="t-subtitle">Update your personal information</p>
            </div>

            <div class="t-content">
                <?php if ($message): ?>
                    <div class="t-alert t-alert-<?php echo $messageType; ?>" style="margin-bottom: 2rem;">
                        <?php echo h($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="profile-form">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" required
                               value="<?php echo h($userData['first_name'] ?? $userData['owner_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required
                               value="<?php echo h($userData['last_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required
                               value="<?php echo h($userData['email'] ?? $userData['contact_email'] ?? ''); ?>">
                    </div>

                    <?php if ($role !== 'admin'): ?>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone"
                                   value="<?php echo h($userData['phone'] ?? ''); ?>">
                        </div>
                    <?php endif; ?>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="t-btn t-btnPrimary">Update Profile</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>