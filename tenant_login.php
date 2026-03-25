<?php
session_start();
require_once __DIR__ . '/security_headers.php';
require_once 'connect.php';
require_once 'tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function baseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
$error = '';
$tenant = null;

if ($tenantSlug !== '') {
    $stmt = mysqli_prepare($conn, "SELECT tenant_id, company_name, owner_name, contact_email, password, status, subdomain_slug FROM tenants WHERE subdomain_slug = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $tenantSlug);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $tenant = $res ? mysqli_fetch_assoc($res) : null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($tenantSlug === '' || !$tenant) {
        $error = 'Invalid clinic link. Please check the URL provided to you.';
    } elseif (strtolower((string)$tenant['status']) !== 'active') {
        $error = 'This clinic is currently inactive. Please contact OralSync support.';
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } elseif (strtolower($email) !== strtolower((string)$tenant['contact_email'])) {
        $error = 'Email does not match this clinic.';
    } elseif (!password_verify($password, (string)$tenant['password'])) {
        $error = 'Incorrect password.';
    } else {
        $_SESSION['tenant_id'] = (int)$tenant['tenant_id'];
        $_SESSION['tenant_slug'] = (string)$tenant['subdomain_slug'];
        $_SESSION['tenant_name'] = (string)$tenant['company_name'];
        $_SESSION['tenant_email'] = (string)$tenant['contact_email'];

        // Log tenant login activity
        logActivity($conn, (int)$tenant['tenant_id'], 'Tenant Login', 'Tenant logged in', $email, 'tenant_owner', 'Tenant Owner');

        // Ensure redirects work even when current URL is rewritten from /tenant/{slug}/login
        header('Location: /tenant_dashboard.php?tenant=' . rawurlencode((string)$tenant['subdomain_slug']));
        exit;
    }
}

$clinicName = $tenant ? (string)$tenant['company_name'] : 'Clinic Portal';
$ownerName = $tenant ? (string)$tenant['owner_name'] : '';
$loginAction = 'tenant_login.php?tenant=' . rawurlencode($tenantSlug ?: 'unknown');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($clinicName); ?> | OralSync Login</title>
    <link rel="stylesheet" href="tenant_style.css">
</head>
<body>
    <div class="t-wrap">
        <div class="t-shell">
            <section class="t-brandPanel">
                <div class="t-brandTop">
                    <div class="t-logo">OS</div>
                    <div>
                        <div class="t-brandTitle"><?php echo h($clinicName); ?></div>
                        <div class="t-brandSub">Powered by OralSync</div>
                    </div>
                </div>

                <div class="t-brandBody">
                    Sign in to manage appointments, patients, and clinic operations. Your clinic can customize this portal soon.
                </div>

                <div class="t-placeholder">
                    <strong>Customization spots (coming soon)</strong><br>
                    - Clinic logo upload<br>
                    - Accent color / theme<br>
                    - Welcome message / announcements<br>
                    - Support contact details
                </div>
            </section>

            <section class="t-card">
                <h1 class="t-cardTitle">Clinic Login</h1>
                <div class="t-cardSub">
                    <?php if ($ownerName): ?>
                        Welcome, <?php echo h($ownerName); ?>. Please sign in to continue.
                    <?php else: ?>
                        Use the email and temporary password sent to you.
                    <?php endif; ?>
                </div>

                <?php if ($error): ?>
                    <div class="t-error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <form class="t-form" method="POST" action="<?php echo h($loginAction); ?>">
                    <div class="t-field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" autocomplete="username" required value="<?php echo h((string)($_POST['email'] ?? '')); ?>">
                    </div>
                    <div class="t-field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                    <button class="t-btn t-btnPrimary" type="submit">Sign in</button>
                </form>

                <div class="t-foot">
                    Having trouble? Make sure you’re using the exact clinic link from your email.
                </div>
            </section>
        </div>
    </div>
</body>
</html>

