<?php
/**
 * ============================================
 * EDIT STAFF DETAILS - ADMIN
 * Updates data in 'staff_details' table.
 * ============================================
 */

require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

// Role Check - Ensure user is admin
$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('admin');

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
$tenantName = $sessionManager->getTenantData()['tenant_name'] ?? '';
$tenantId = $sessionManager->getTenantId();

$staff_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$message = '';
$success = false;

if (!$staff_id && !$user_id) {
    header("Location: staff.php?tenant=" . rawurlencode($tenantSlug));
    exit();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = trim($_POST['role'] ?? 'Receptionist');
    $specialties = trim($_POST['specialties'] ?? '');
    $publicBio = trim($_POST['public_bio'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $isPublicVisible = isset($_POST['is_public_visible']) ? 1 : 0;

    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both create and update
    $sql = "INSERT INTO staff_details 
            (tenant_id, first_name, last_name, email, phone, role, specialties, public_bio, status, is_public_visible)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            phone = VALUES(phone),
            role = VALUES(role),
            specialties = VALUES(specialties),
            public_bio = VALUES(public_bio),
            status = VALUES(status),
            is_public_visible = VALUES(is_public_visible)";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'issssssssi', $tenantId, $firstName, $lastName, $email, $phone, $role, $specialties, $publicBio, $status, $isPublicVisible);
        if (mysqli_stmt_execute($stmt)) {
            $success = true;
            // If it was an insert, get the new ID
            if ($staff_id === 0) {
                $staff_id = mysqli_insert_id($conn);
            }
            header("Location: view_staff_profile.php?tenant=" . rawurlencode($tenantSlug) . "&id=" . $staff_id . "&uid=" . $user_id . "&success=1");
            exit();
        } else {
            $message = 'Error saving details: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = 'Database error: ' . mysqli_error($conn);
    }
}

// Fetch current data (trying staff_details first, then fallback to users)
$staff = null;
if ($staff_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM staff_details WHERE staff_id = ? AND tenant_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $staff_id, $tenantId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $staff = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

if (!$staff && $user_id > 0) {
    // Fallback to user data if staff_details doesn't exist yet
    $stmt = mysqli_prepare($conn, "SELECT first_name, last_name, email, role FROM users WHERE user_id = ? AND tenant_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $tenantId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $staff = mysqli_fetch_assoc($result);
        if ($staff) {
            $staff['staff_id'] = 0; // Explicitly 0 for new record
            $staff['status'] = 'Active';
        }
        mysqli_stmt_close($stmt);
    }
}

if (!$staff) {
    die("Staff member not found or access denied.");
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Edit Staff Details</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
        :root {
            --accent: #0d3b66;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --text: #334155;
            --muted: #64748b;
        }

        .form-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
        }

        .form-header {
            margin-bottom: 30px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 16px;
        }

        .form-header h1 {
            color: var(--accent);
            margin: 0;
            font-size: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        label {
            display: block;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            color: var(--text);
            transition: all 0.2s;
            background: #fff;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-group input {
            width: auto;
        }

        .button-group {
            display: flex;
            gap: 16px;
            margin-top: 32px;
        }

        .btn {
            flex: 1;
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: #002855;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: var(--muted);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .alert {
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-weight: 600;
            font-size: 14px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <main class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">Edit Staff Professional Details</div>
        <?php renderDateClock(); ?>
      </div>

      <div class="form-container">
        <div class="form-header">
            <h1>Update Professional Profile</h1>
            <p style="margin-top: 8px; color: var(--muted);">Managing details for: <strong><?php echo h($staff['first_name'] . ' ' . $staff['last_name']); ?></strong></p>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo h($staff['first_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo h($staff['last_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo h($staff['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Contact Phone</label>
                    <input type="text" id="phone" name="phone" value="<?php echo h($staff['phone'] ?? ''); ?>" placeholder="0917-XXX-XXXX">
                </div>

                <div class="form-group">
                    <label for="role">Staff Role</label>
                    <select id="role" name="role">
                        <option value="Dentist" <?php echo ($staff['role'] === 'Dentist') ? 'selected' : ''; ?>>Dentist</option>
                        <option value="Receptionist" <?php echo ($staff['role'] === 'Receptionist') ? 'selected' : ''; ?>>Receptionist</option>
                        <option value="Assistant" <?php echo ($staff['role'] === 'Assistant') ? 'selected' : ''; ?>>Assistant</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Account Status</label>
                    <select id="status" name="status">
                        <option value="Active" <?php echo ($staff['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($staff['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label for="specialties">Specialties / Expertise</label>
                    <input type="text" id="specialties" name="specialties" value="<?php echo h($staff['specialties'] ?? ''); ?>" placeholder="e.g. Orthodontics, Periodontics, Oral Surgery">
                </div>

                <div class="form-group full-width">
                    <label for="public_bio">Professional Biography</label>
                    <textarea id="public_bio" name="public_bio" placeholder="Enter educational background, experience, and certifications..."><?php echo h($staff['public_bio'] ?? ''); ?></textarea>
                </div>

                <div class="form-group full-width checkbox-group">
                    <input type="checkbox" id="is_public_visible" name="is_public_visible" <?php echo $staff['is_public_visible'] ? 'checked' : ''; ?>>
                    <label for="is_public_visible" style="margin-bottom: 0; text-transform: none;">Make profile visible to public/patients</label>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-primary">Save Professional Details</button>
                <a href="view_staff_profile.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&id=<?php echo $staff_id; ?>&uid=<?php echo $user_id; ?>" class="btn btn-secondary">Cancel</a>
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