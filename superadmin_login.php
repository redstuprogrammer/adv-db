<?php
session_start();

function envOrNull(string $key): ?string {
    $val = getenv($key);
    if ($val === false || $val === null || $val === '') {
        if (isset($_ENV[$key])) $val = (string)$_ENV[$key];
        else if (isset($_SERVER[$key])) $val = (string)$_SERVER[$key];
        else $val = null;
    }
    if ($val === null) return null;
    $val = trim((string)$val);
    return $val === '' ? null : $val;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$error = '';
$expectedUser = envOrNull('SUPERADMIN_USER');
$expectedPass = envOrNull('SUPERADMIN_PASS');
$authEnabled = ($expectedUser !== null && $expectedPass !== null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    if (!$authEnabled) {
        // If credentials are not configured, allow access (dev mode).
        $_SESSION['superadmin_authed'] = true;
        header('Location: superadmin_dash.php');
        exit;
    }

    if ($user === $expectedUser && hash_equals($expectedPass, $pass)) {
        $_SESSION['superadmin_authed'] = true;
        header('Location: superadmin_dash.php');
        exit;
    }

    $error = 'Invalid username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>OralSync | Super Admin Login</title>
    <link rel="stylesheet" href="tenant_style.css" />
</head>
<body>
    <div class="t-wrap">
        <div class="t-shell" style="grid-template-columns: 1fr;">
            <section class="t-card">
                <h1 class="t-cardTitle">Super Admin Login</h1>
                <div class="t-cardSub">
                    <?php if ($authEnabled): ?>
                        Sign in to manage tenants and clinic onboarding.
                    <?php else: ?>
                        Super admin credentials are not set yet. This login will allow access for now.
                    <?php endif; ?>
                </div>

                <?php if ($error): ?>
                    <div class="t-error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <form class="t-form" method="POST" action="superadmin_login.php">
                    <div class="t-field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" autocomplete="username" required />
                    </div>
                    <div class="t-field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required />
                    </div>
                    <button class="t-btn t-btnPrimary" type="submit">Sign in</button>
                </form>

                <div class="t-foot">
                    Tip: later you can set `SUPERADMIN_USER` and `SUPERADMIN_PASS` as app settings on Azure for real protection.
                </div>
            </section>
        </div>
    </div>
</body>
</html>

