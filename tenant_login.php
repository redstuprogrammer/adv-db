<?php
define('ROOT_PATH', __DIR__ . '/');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Load includes FIRST before using any functions
require_once ROOT_PATH . 'includes/security_headers.php';
require_once ROOT_PATH . 'includes/connect.php';
require_once ROOT_PATH . 'includes/tenant_utils.php';
require_once ROOT_PATH . 'includes/tenant_settings_functions.php';

// Prevent redirect loops by tracking redirect count
$_SESSION['tenant_login_redirect_count'] = ($_SESSION['tenant_login_redirect_count'] ?? 0) + 1;
if ($_SESSION['tenant_login_redirect_count'] > 5) {
    // Clear redirect counter and show error
    $_SESSION['tenant_login_redirect_count'] = 0;
    http_response_code(400);
    die("Too many redirects detected. Please check your cookies are enabled and try clearing your browser cache.");
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));

// Check if already logged in for the requested tenant
if ($tenantSlug !== '' && isset($_SESSION['tenant_context']) && is_array($_SESSION['tenant_context'])) {
    $currentContext = $_SESSION['tenant_context'][$tenantSlug] ?? null;
    if ($currentContext && isset($currentContext['role'])) {
        $dashboardUrl = getRoleDashboardUrl($currentContext['role'], $tenantSlug);
        if ($dashboardUrl) {
            header('Location: ' . $dashboardUrl);
            exit;
        }
    }
}

// Fallback: if tenant slug was not specified but a current context exists, redirect there.
if ($tenantSlug === '' && isset($_SESSION['tenant_context']) && is_array($_SESSION['tenant_context']) && isset($_SESSION['tenant_slug_current'])) {
    $currentContext = $_SESSION['tenant_context'][$_SESSION['tenant_slug_current']] ?? null;
    if ($currentContext && isset($currentContext['role'])) {
        $dashboardUrl = getRoleDashboardUrl($currentContext['role'], $_SESSION['tenant_slug_current']);
        if ($dashboardUrl) {
            header('Location: ' . $dashboardUrl);
            exit;
        }
    }
}

// Check if superadmin is logged in - redirect to dashboard
// Commented out to allow testing multiple roles in different tabs
// if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
//     header('Location: superadmin_dash.php');
//     exit();
// }

// Load tenant-specific login customization settings from tenant_configs
$loginSettings = [
    'brand_bg_color' => '#001f3f',
    'brand_text_color' => '#ffffff',
    'primary_btn_color' => '#22c55e',
    'link_color' => '#2563eb',
    'login_title' => 'Clinic Login',
    'login_description' => 'Please sign in to access your clinic portal.',
    'brand_subtitle' => 'Powered by OralSync',
    'brand_logo_path' => '',
    'brand_bg_image_path' => ''
];

$tenant_id = 0;
$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
$error = '';
$tenant = null;

// Check connection
if (!$conn && (!isset($pdo) || !$pdo)) {
    error_log("CRITICAL: Database connection failed in tenant_login.php");
    http_response_code(500);
    die("Database connection error. Please try again later.");
}

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
    try {
        $stmt = mysqli_prepare($conn, "SELECT tenant_id, company_name, owner_name, contact_email, password, status, subdomain_slug FROM tenants WHERE subdomain_slug = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $tenantSlug);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                $tenant = $res ? mysqli_fetch_assoc($res) : null;
            } else {
                error_log("Query execution failed in tenant_login for slug: " . $tenantSlug . " - Error: " . mysqli_error($conn));
                $error = "Database error. Please try again.";
            }
            mysqli_stmt_close($stmt);
        } else {
            error_log("Statement preparation failed in tenant_login: " . mysqli_error($conn));
            $error = "Database error. Please try again.";
        }
    } catch (Exception $e) {
        error_log("Exception in tenant lookup: " . $e->getMessage());
        $error = "An error occurred. Please try again.";
    }
}

if ($tenant && isset($tenant['tenant_id'])) {
    $tenant_id = (int)$tenant['tenant_id'];
    try {
        $stmt = $conn->prepare("SELECT brand_bg_color, brand_text_color, primary_btn_color, link_color, login_title, login_description, brand_subtitle, brand_logo_path, brand_bg_image_path FROM tenant_configs WHERE tenant_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $tenant_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $config = $result ? $result->fetch_assoc() : null;
                if ($config) {
                    foreach ($config as $key => $value) {
                        if ($value !== null && $value !== '') {
                            $loginSettings[$key] = $value;
                        }
                    }
                }
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error loading tenant config: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($tenantSlug === '' || !$tenant) {
        $error = 'Invalid clinic link. Please check the URL provided to you.';
    } elseif (strtolower((string)$tenant['status']) !== 'active') {
        $error = 'This clinic is currently inactive. Please contact OralSync support.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $authenticated = false;
        $userRole = '';
        $userData = null;

        // First, try tenant owner login using email.
        if (strcasecmp($username, (string)$tenant['contact_email']) === 0 && password_verify($password, (string)$tenant['password'])) {
            $authenticated = true;
            $userRole = 'Admin';
            $userData = [
                'user_id' => null, // owner has no user_id
                'username' => $username,
                'email' => (string)$tenant['contact_email'],
                'role' => 'Admin'
            ];
        } else {
            // Fallback: check users table for receptionist/dentist
            $isEmailInput = strpos($username, '@') !== false;
            try {
                if ($isEmailInput) {
                    $stmt = mysqli_prepare($conn, "SELECT user_id, username, email, password, role FROM users WHERE tenant_id = ? AND email = ? LIMIT 1");
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "is", $tenant['tenant_id'], $username);
                    }
                } else {
                    $stmt = mysqli_prepare($conn, "SELECT user_id, username, email, password, role FROM users WHERE tenant_id = ? AND BINARY username = ? LIMIT 1");
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "is", $tenant['tenant_id'], $username);
                    }
                }

                if ($stmt) {
                    if (mysqli_stmt_execute($stmt)) {
                        $res = mysqli_stmt_get_result($stmt);
                        $user = $res ? mysqli_fetch_assoc($res) : null;
                        if ($user && password_verify($password, (string)$user['password'])) {
                            $authenticated = true;
                            $userRole = (string)$user['role'];
                            $userData = $user;
                        }
                    } else {
                        error_log("Users query execution failed: " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    error_log("Users statement preparation failed: " . mysqli_error($conn));
                }
            } catch (Exception $e) {
                error_log("Exception checking users table: " . $e->getMessage());
            }
        }

        if (!$authenticated) {
            $error = 'Incorrect email or password.';
        } else {
            if (!isset($_SESSION['tenant_context']) || !is_array($_SESSION['tenant_context'])) {
                $_SESSION['tenant_context'] = [];
            }

            $context = [
                'tenant_id' => (int)$tenant['tenant_id'],
                'tenant_slug' => (string)$tenant['subdomain_slug'],
                'tenant_name' => (string)$tenant['company_name'],
                'tenant_email' => (string)$tenant['contact_email'],
                'tenant_username' => $userData['username'],
                'role' => $userRole,
                'user_id' => $userData['user_id'],
                'user_email' => $userData['email']
            ];

            // Regenerate session ID to allow multiple concurrent sessions
            session_regenerate_id(true);

            $_SESSION['tenant_context'][$tenant['subdomain_slug']] = $context;
            $_SESSION['tenant_slug_current'] = $tenant['subdomain_slug'];

            // Mirror legacy fields for existing pages
            $_SESSION['tenant_id'] = $context['tenant_id'];
            $_SESSION['tenant_slug'] = $context['tenant_slug'];
            $_SESSION['tenant_name'] = $context['tenant_name'];
            $_SESSION['tenant_email'] = $context['tenant_email'];
            $_SESSION['tenant_username'] = $context['tenant_username'];
            $_SESSION['role'] = $context['role'];
            $_SESSION['user_id'] = $context['user_id'];
            $_SESSION['email'] = $context['user_email'];

            // Log activity
            $activityType = ucfirst(strtolower($userRole)) . ' Login';
            try {
                logActivity($conn, (int)$tenant['tenant_id'], $activityType, ucfirst(strtolower($userRole)) . ' logged in', $userData['username'], strtolower($userRole), ucfirst(strtolower($userRole)));
            } catch (Exception $e) {
                error_log('Activity logging failed: ' . $e->getMessage());
                // Don't break login flow if logging fails
            }

            // Reset redirect counter on successful login
            $_SESSION['tenant_login_redirect_count'] = 0;

            $dashboardUrl = getRoleDashboardUrl($userRole, (string)$tenant['subdomain_slug']);
            error_log("Post-login redirect from tenant_login.php: " . $dashboardUrl);
            if (!$dashboardUrl || empty($dashboardUrl)) {
                $error = 'Dashboard URL could not be generated. Please contact support.';
                error_log("ERROR: Dashboard URL is empty for role: " . $userRole);
            } else {
                header('Location: ' . $dashboardUrl);
                exit;
            }
        }
    }
}

$clinicName = $tenant ? (string)$tenant['company_name'] : 'Clinic Portal';
$ownerName = $tenant ? (string)$tenant['owner_name'] : '';
$base = getAppBasePath();
$loginAction = ($base !== '' ? $base : '') . '/tenant_login.php?tenant=' . rawurlencode($tenantSlug ?: 'unknown');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($clinicName); ?> | OralSync Login</title>
    <link rel="stylesheet" href="/tenant_style.css">
    <style>
        .t-brandPanel {
            color: <?php echo h($loginSettings['brand_text_color']); ?>;
            background: <?php echo h($loginSettings['brand_bg_color']); ?>;
            background-image: <?php echo $loginSettings['brand_bg_image_path'] ? "linear-gradient(rgba(0, 0, 0, 0.22), rgba(0, 0, 0, 0.22)), url('" . h($loginSettings['brand_bg_image_path']) . "')" : 'none'; ?>;
            background-size: cover;
            background-position: center;
        }
        .t-brandPanel, .t-brandPanel * {
            color: <?php echo h($loginSettings['brand_text_color']); ?> !important;
        }
        .t-logo img {
            display: block;
            width: 44px;
            height: 44px;
            object-fit: contain;
        }
        .t-btnPrimary {
            background: <?php echo h($loginSettings['primary_btn_color']); ?> !important;
        }
        .t-card a[href*="forgot_password"] {
            color: <?php echo h($loginSettings['link_color']); ?> !important;
        }
    </style>
</head>
<body>
    <div class="t-wrap">
        <div class="t-shell">
            <section class="t-brandPanel">
                <div class="t-brandTop">
                    <div class="t-logo">
                        <?php if (!empty($loginSettings['brand_logo_path'])): ?>
                            <img src="<?php echo h($loginSettings['brand_logo_path']); ?>" alt="Clinic logo">
                        <?php else: ?>
                            OS
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="t-brandTitle"><?php echo h($clinicName); ?></div>
                        <div class="t-brandSub"><?php echo h($loginSettings['brand_subtitle']); ?></div>
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
                <h1 class="t-cardTitle"><?php echo h($loginSettings['login_title']); ?></h1>
                <div class="t-cardSub">
                    <?php echo h($loginSettings['login_description']); ?>
                </div>

                <?php if ($error): ?>
                    <div class="t-error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <form class="t-form" method="POST" action="<?php echo h($loginAction); ?>">
                    <div class="t-field">
                        <label for="username">Email / Username</label>
                        <input id="username" name="username" type="text" autocomplete="username" required value="<?php echo h((string)($_POST['username'] ?? '')); ?>">
                    </div>
                    <div class="t-field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                    <button class="t-btn t-btnPrimary" type="submit">Sign in</button>
                </form>
                <div style="margin-top: 16px; display: flex; gap: 8px; justify-content: space-between;">
                    <a href="/forgot_password_tenant.php?tenant=<?php echo h(rawurlencode($tenantSlug)); ?>" style="color: #0d3b66; text-decoration: none; font-size: 12px; font-weight: 600;">Forgot password?</a>
                </div>
                <div class="t-foot">
                    Don't have an account? Contact your clinic for access.
                </div>
            </section>
        </div>
    </div>
</body>
</html>

