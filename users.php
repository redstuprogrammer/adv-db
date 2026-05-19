<?php
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/tenant_tier_helper.php';
require_once __DIR__ . '/includes/session_utils.php';

$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('Admin');

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Sends a welcome email with a temporary password to a new user.
 */
function sendUserWelcomeEmail($email, $firstName, $lastName, $tempPassword, $tenantName, $tenantSlug, $role) {
    // Check for SMTP settings using environment detection
    $smtpHost = getenv('SMTP_HOST') ?: $_ENV['SMTP_HOST'] ?? $_SERVER['SMTP_HOST'] ?? null;
    $smtpPort = getenv('SMTP_PORT') ?: $_ENV['SMTP_PORT'] ?? $_SERVER['SMTP_PORT'] ?? null;
    $smtpUser = getenv('SMTP_USERNAME') ?: $_ENV['SMTP_USERNAME'] ?? $_SERVER['SMTP_USERNAME'] ?? null;
    $smtpPass = getenv('SMTP_PASSWORD') ?: $_ENV['SMTP_PASSWORD'] ?? $_SERVER['SMTP_PASSWORD'] ?? null;
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: $_ENV['SMTP_FROM_EMAIL'] ?? $smtpUser;
    $fromName = 'OralSync';

    if (!$smtpHost || !$smtpPort || !$smtpUser || !$smtpPass) {
        error_log("SMTP settings missing. Could not send welcome email to $email");
        return false;
    }

    require_once __DIR__ . '/vendor/autoload.php';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)$smtpPort;

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, trim($firstName . ' ' . $lastName));

        $mail->isHTML(true);
        $mail->Subject = "Welcome to " . $tenantName . " | Your Account Details";
        
        $safeName = htmlspecialchars($firstName ?: 'there', ENT_QUOTES, 'UTF-8');
        $safeClinic = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
        $safePass = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');
        $safeRole = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
        
        // Build login URL
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $loginUrl = $scheme . '://' . $host . '/tenant_login.php?tenant=' . urlencode($tenantSlug);

        $mail->Body = <<<HTML
<div style="font-family: sans-serif; max-width: 600px; margin: auto; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(15,23,42,0.08);">
    <div style="background: linear-gradient(135deg, #0d3b66, #0f172a); color: white; padding: 32px; text-align: center;">
        <div style="font-weight: 800; letter-spacing: 0.5px; font-size: 24px; margin-bottom: 8px;">OralSync</div>
        <p style="margin: 0; opacity: 0.9; font-size: 16px;">Welcome to the Team</p>
    </div>
    <div style="padding: 32px; color: #1e293b; line-height: 1.6;">
        <p style="font-size: 18px; margin-bottom: 16px;">Hello <strong>{$safeName}</strong>,</p>
        <p>You have been registered as a <strong>{$safeRole}</strong> at <strong>{$safeClinic}</strong>. You can now access the clinic management portal using the credentials below:</p>
        
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin: 24px 0;">
            <div style="margin-bottom: 16px;">
                <div style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">Login URL</div>
                <div style="font-family: monospace; font-size: 14px; color: #0d3b66; word-break: break-all;">{$loginUrl}</div>
            </div>
            <div style="margin-bottom: 16px;">
                <div style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">Email / Username</div>
                <div style="font-size: 16px; font-weight: 600;">{$email}</div>
            </div>
            <div>
                <div style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">Temporary Password</div>
                <div style="font-family: monospace; font-size: 18px; color: #0d3b66; font-weight: 800;">{$safePass}</div>
            </div>
        </div>
        
        <div style="margin-top: 24px;">
            <div style="font-weight: 800; color: #0d3b66; margin-bottom: 12px; font-size: 14px; text-transform: uppercase;">Next Steps</div>
            <ul style="margin: 0; padding-left: 20px; color: #475569;">
                <li style="margin-bottom: 8px;">Click the button below to open your clinic's login page.</li>
                <li style="margin-bottom: 8px;">Log in using your email and the temporary password provided.</li>
                <li style="margin-bottom: 8px;">For security, you will be asked to change your password immediately.</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="{$loginUrl}" style="background: #0d3b66; color: white; padding: 14px 28px; text-decoration: none; border-radius: 999px; font-weight: 800; display: inline-block; box-shadow: 0 4px 12px rgba(13, 59, 102, 0.2);">Access OralSync Portal</a>
        </div>
    </div>
    <div style="background: #f8fafc; border-top: 1px solid #e2e8f0; color: #94a3b8; padding: 20px; text-align: center; font-size: 12px;">
        &copy; <?php echo date('Y'); ?> OralSync - Advanced Dental Management System. All rights reserved.
    </div>
</div>
HTML;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $e->getMessage());
        return false;
    }
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();
$formError = '';

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $username = $email; // Use email as username
    $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $phone = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : '';

    // Generate a random 8-character temporary password in the background
    $rawPassword = bin2hex(random_bytes(4)); 

    if ($email === '' || $role === '') {
        $formError = 'Email and role are required.';
    } elseif (!in_array($role, ['Admin', 'Receptionist', 'Dentist'], true)) {
        $formError = 'Invalid role selected.';
    } else {
        if (strcasecmp($role, 'Dentist') === 0) {
            $maxDentists = getTenantTierLimit((int)$tenantId, 'max_dentists', $conn);
            if ($maxDentists !== null) {
                $countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE tenant_id = ? AND role = 'Dentist'");
                if ($countStmt) {
                    $countStmt->bind_param('i', $tenantId);
                    $countStmt->execute();
                    $countRow = $countStmt->get_result()->fetch_assoc();
                    $countStmt->close();
                    if ((int)($countRow['c'] ?? 0) >= $maxDentists) {
                        $formError = 'Your current plan allows up to ' . $maxDentists . ' dentist account(s). Upgrade to add more.';
                    }
                }
            }
        } elseif (strcasecmp($role, 'Receptionist') === 0) {
            $maxReceptionists = getTenantTierLimit((int)$tenantId, 'max_receptionists', $conn);
            if ($maxReceptionists !== null) {
                $countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE tenant_id = ? AND role = 'Receptionist'");
                if ($countStmt) {
                    $countStmt->bind_param('i', $tenantId);
                    $countStmt->execute();
                    $countRow = $countStmt->get_result()->fetch_assoc();
                    $countStmt->close();
                    if ((int)($countRow['c'] ?? 0) >= $maxReceptionists) {
                        $formError = 'Your current plan allows up to ' . $maxReceptionists . ' receptionist account(s). Upgrade to add more.';
                    }
                }
            }
        }


        if ($formError === '') {
            // Check duplicate email within this tenant
            $checkEmailStmt = $conn->prepare('SELECT user_id FROM users WHERE tenant_id = ? AND email = ? LIMIT 1');
            if ($checkEmailStmt) {
                $checkEmailStmt->bind_param('is', $tenantId, $email);
                $checkEmailStmt->execute();
                $resultEmail = $checkEmailStmt->get_result();
                if ($resultEmail && $resultEmail->num_rows > 0) {
                    $formError = 'That email address is already in use by another user in this clinic.';
                }
                $checkEmailStmt->close();
            }
        }

        if ($formError === '') {
            $password = password_hash($rawPassword, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('INSERT INTO users (tenant_id, username, email, phone, password, role, first_name, last_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            if ($stmt) {
                $stmt->bind_param('isssssss', $tenantId, $username, $email, $phone, $password, $role, $firstName, $lastName);
                if ($stmt->execute()) {
                    if (strcasecmp($role, 'Dentist') === 0) {
                        syncDentistRecordFromUser($conn, $stmt->insert_id);
                    }
                    
                    // Send welcome email with temporary password
                    sendUserWelcomeEmail($email, $firstName, $lastName, $rawPassword, $tenantName, $tenantSlug, $role);
                    
                    header('Location: users.php?tenant=' . urlencode($tenantSlug) . '&success=1');
                    exit;
                } else {
                    error_log("Error adding user: " . $stmt->error);
                    $formError = 'Unable to create the user. Please try again.';
                }
                $stmt->close();
            } else {
                $formError = 'Unable to prepare user creation. Please try again later.';
            }
        }
    }
}

// Fetch users for display
$users = [];
try {
    $stmt = $conn->prepare('SELECT user_id, email, phone, role, first_name, last_name, created_at FROM users WHERE tenant_id = ? ORDER BY email');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Users</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
      :root {
        --accent: #0d3b66;
        --border: #e2e8f0;
        --bg: #f8fafc;
      }

      .btn-primary {
        background: var(--accent);
        color: white;
        padding: 10px 16px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: background 0.2s ease;
      }

      .btn-primary:hover {
        background: #0a2d4f;
      }

      .module-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
      }

      .module-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
      }

      .module-table th {
        background: var(--bg);
        border-bottom: 2px solid var(--border);
        padding: 12px;
        text-align: left;
        font-weight: 700;
        color: var(--accent);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .module-table td {
        padding: 12px;
        border-bottom: 1px solid var(--border);
      }

      .module-table tbody tr:hover {
        background: var(--bg);
      }

      .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
      }

      .badge-admin { background: rgba(13, 59, 102, 0.15); color: #0d3b66; }
      .badge-receptionist { background: rgba(245, 158, 11, 0.15); color: #d97706; }
      .badge-dentist { background: rgba(88, 28, 135, 0.15); color: #581c87; }

      .action-btn {
        display: inline-block;
        padding: 8px 12px;
        margin-right: 4px;
        background: var(--accent);
        border: 1px solid var(--accent);
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-size: 12px;
        color: white;
        font-weight: 600;
        transition: all 0.2s ease;
      }

      .action-btn:hover {
        background: #0a2d4f;
        border-color: #0a2d4f;
      }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <!-- Main Content -->
    <div class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">👤 Users</div>
        <div style="display: flex; align-items: center; gap: 16px;">
          <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
          <div id="liveClock" class="live-clock-badge">00:00:00 AM</div>
        </div>
      </div>

      <?php if (!empty($formError)): ?>
        <div class="alert-box" style="background: #fee2e2; color: #991b1b; border-color: #fecaca; margin-bottom: 20px;">
          <?php echo h($formError); ?>
        </div>
      <?php endif; ?>

      <div class="module-card">
        <div style="display: flex; justify-content: flex-end; align-items: center; margin-bottom: 20px;">
          <a href="#" class="btn-primary" onclick="openAddUserModal()">+ Add User</a>
        </div>
        
        <table class="module-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Joined Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr>
                <td colspan="6" style="text-align: center; color: rgb(100, 116, 139);">No users found. Click "Add User" to create one.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $user): 
                $role = $user['role'];
                $badgeClass = 'badge-admin';
                if ($role === 'Receptionist') $badgeClass = 'badge-receptionist';
                elseif ($role === 'Dentist') $badgeClass = 'badge-dentist';
                $createdAt = isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'N/A';
                $userFullName = trim((isset($user['first_name']) ? $user['first_name'] : '') . ' ' . (isset($user['last_name']) ? $user['last_name'] : ''));
                if (empty($userFullName)) $userFullName = '(not provided)';
              ?>
              <tr>
                <td><?php echo h($userFullName); ?></td>
                <td><?php echo h($user['email']); ?></td>
                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo h($role); ?></span></td>
                <td><?php echo h($createdAt); ?></td>
                <td>
                  <button class="action-btn" onclick="toggleUserState(this)">Deactivate</button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <script>
    function toggleUserState(button) {
      if (!button) return;
      const isActive = button.textContent.trim().toLowerCase() === 'active';
      if (isActive) {
        button.textContent = 'Deactivate';
        button.style.background = '#0a2d4f';
      } else {
        button.textContent = 'Active';
        button.style.background = '#10b981';
      }
      alert('The user state has been ' + (isActive ? 'set to active' : 'set to inactive') + '.\nIf they try to log in, they will be asked to contact admin.');
    }

    function openAddUserModal() {
      document.getElementById('addUserModal').style.display = 'flex';
    }

    function closeAddUserModal() {
      document.getElementById('addUserModal').style.display = 'none';
    }
  </script>

  <!-- Add User Modal -->
  <div id="addUserModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; flex-direction: column;">
    <div class="modal-content" style="background: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 400px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <span style="font-size: 20px; font-weight: bold;">Add New User</span>
        <button class="close" onclick="closeAddUserModal()" style="border: none; background: none; font-size: 20px; cursor: pointer;">&times;</button>
      </div>
      <form method="POST">
        <div style="margin-bottom: 10px;">
          <label style="display: block; margin-bottom: 4px;">First Name</label>
          <input type="text" name="first_name" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;">
        </div>
        <div style="margin-bottom: 10px;">
          <label style="display: block; margin-bottom: 4px;">Last Name</label>
          <input type="text" name="last_name" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;">
        </div>
        <div style="margin-bottom: 10px;">
          <label style="display: block; margin-bottom: 4px;">Phone Number</label>
          <input type="text" name="phone_number" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;">
        </div>
        <div style="margin-bottom: 10px;">
          <label style="display: block; margin-bottom: 4px;">Email</label>
          <input type="email" name="email" required style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;">
          <p style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">A temporary password will be auto-generated and sent to this email.</p>
        </div>
        <div style="margin-bottom: 15px;">
          <label style="display: block; margin-bottom: 4px;">Role</label>
          <select name="role" required style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;">
            <option value="">Select Role</option>
            <option value="Admin">Admin</option>
            <option value="Receptionist">Receptionist</option>
            <option value="Dentist">Dentist</option>
          </select>
        </div>
        <div style="text-align: right;">
          <button type="button" onclick="closeAddUserModal()" style="padding: 8px 16px; margin-right: 10px; border: 1px solid var(--border); background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
          <button type="submit" name="add_user" style="padding: 8px 16px; background: var(--accent); color: white; border: none; border-radius: 4px; cursor: pointer;">Add User</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Live Clock - Update every second
    function updateClock() {
      const clockElement = document.getElementById('liveClock');
      if (clockElement) {
        clockElement.textContent = new Date().toLocaleTimeString('en-US', { hour12: true });
      }
    }
    // Initialize clock immediately
    updateClock();
    // Update every second
    setInterval(updateClock, 1000);
  </script>
</body>
</html>


