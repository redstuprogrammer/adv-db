<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin' || $_SESSION['tenant_slug'] !== $tenantSlug) {
    header("Location: tenant_login.php?tenant=" . rawurlencode($tenantSlug));
    exit();
}

requireTenantLogin($tenantSlug);
$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();

$user_id = (int)$_GET['id'];
$message = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $specialization = trim($_POST['specialization'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $professional_biography = trim($_POST['professional_biography'] ?? '');

    // Update dentist table
    $stmt = $conn->prepare("UPDATE dentist SET 
        primary_specialization = ?,
        license_number = ?,
        contact_phone = ?,
        professional_biography = ?
        WHERE username = (SELECT username FROM users WHERE user_id = ? AND tenant_id = ?) AND tenant_id = ?");

    if ($stmt) {
        $stmt->bind_param('ssssiii', $specialization, $license_number, $contact_phone, $professional_biography, $user_id, $tenantId, $tenantId);
        if ($stmt->execute()) {
            $success = true;
            $message = 'Staff details updated successfully.';
        } else {
            $message = 'Error updating details: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = 'Database error: ' . $conn->error;
    }
}

// Fetch current data
$stmt = $conn->prepare("SELECT u.username, u.first_name, u.last_name, 
                       d.primary_specialization, d.license_number, d.contact_phone, d.professional_biography
                       FROM users u 
                       LEFT JOIN dentist d ON u.username = d.username 
                       WHERE u.user_id = ? AND u.tenant_id = ?");
if ($stmt) {
    $stmt->bind_param('ii', $user_id, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
}

if (!$data) {
    die("Staff member not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo h($tenantName); ?> | Edit Staff Details</title>
    <link rel="stylesheet" href="style1.css">
    <link rel="stylesheet" href="tenant_style.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h1 {
            color: var(--primary, #0d3b66);
            margin: 0;
            font-size: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--primary, #0d3b66);
            box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background 0.2s;
        }

        .btn-primary {
            background: var(--primary, #0d3b66);
            color: white;
        }

        .btn-primary:hover {
            background: #002855;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .message.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <div class="t-wrap">
        <div class="t-shell">
            <div class="form-container">
                <div class="form-header">
                    <h1>Edit Staff Details</h1>
                    <p>Updating profile for: <strong><?php echo h($data['first_name'] . ' ' . $data['last_name']); ?></strong></p>
                </div>

                <?php if ($message): ?>
                    <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                        <?php echo h($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="specialization">Primary Specialization</label>
                            <input type="text" id="specialization" name="specialization" 
                                   value="<?php echo h($data['primary_specialization'] ?? ''); ?>" 
                                   placeholder="e.g. Orthodontics, General Dentistry">
                        </div>

                        <div class="form-group">
                            <label for="license_number">License Number</label>
                            <input type="text" id="license_number" name="license_number" 
                                   value="<?php echo h($data['license_number'] ?? ''); ?>" 
                                   placeholder="e.g. PRC-1234567">
                        </div>

                        <div class="form-group">
                            <label for="contact_phone">Contact Phone</label>
                            <input type="text" id="contact_phone" name="contact_phone" 
                                   value="<?php echo h($data['contact_phone'] ?? ''); ?>" 
                                   placeholder="e.g. 0917-XXX-XXXX">
                        </div>

                        <div class="form-group full-width">
                            <label for="professional_biography">Professional Biography</label>
                            <textarea id="professional_biography" name="professional_biography" 
                                      placeholder="Enter background, education, experience, and qualifications..."><?php echo h($data['professional_biography'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="view_staff.php?id=<?php echo $user_id; ?>&tenant=<?php echo rawurlencode($tenantSlug); ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>