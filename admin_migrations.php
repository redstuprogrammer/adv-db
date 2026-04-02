<?php
session_start();

// Check if user is superadmin
if (empty($_SESSION['superadmin_authed'])) {
    http_response_code(403);
    die('Unauthorized. Please log in as super admin first.');
}

require_once __DIR__ . '/connect.php';

// Allowed migrations
$migrations = [
    'add_password_reset' => [
        'name' => 'Add Password Reset Functionality',
        'description' => 'Adds username and password reset columns to tenants and super_admins tables',
        'execute' => 'migration_add_password_reset'
    ]
];

$message = '';
$message_type = 'info';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate'])) {
    $migration_key = $_POST['migrate'];
    
    if (!isset($migrations[$migration_key])) {
        $message = 'Invalid migration selected.';
        $message_type = 'error';
    } else {
        $migration = $migrations[$migration_key];
        $sql_file = __DIR__ . "/Dump20260320/{$migration['execute']}.sql";
        
        if (!file_exists($sql_file)) {
            $message = 'Migration file not found.';
            $message_type = 'error';
        } else {
            $sql = file_get_contents($sql_file);
            $statements = array_filter(array_map('trim', preg_split('/;+/', $sql)));
            
            $success = 0;
            $errors = [];
            
            foreach ($statements as $statement) {
                if (empty($statement)) continue;
                
                if (mysqli_query($conn, $statement)) {
                    $success++;
                } else {
                    $errors[] = [
                        'statement' => substr($statement, 0, 100),
                        'error' => mysqli_error($conn)
                    ];
                }
            }
            
            if (empty($errors)) {
                $message = "✓ Migration completed successfully! {$success} SQL statements executed.";
                $message_type = 'success';
            } else {
                $message = "Migration completed with " . count($errors) . " errors (out of " . ($success + count($errors)) . " statements)";
                $message_type = 'warning';
                $result = ['success' => $success, 'errors' => $errors];
            }
        }
    }
}

$update_message = '';
$update_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_usernames'])) {
    $query = "SELECT tenant_id, company_name FROM tenants WHERE username IS NULL OR username = ''";
    $result_query = mysqli_query($conn, $query);
    
    if (!$result_query) {
        $update_message = 'Query failed: ' . mysqli_error($conn);
        $update_type = 'error';
    } else {
        $updated = 0;
        $errors_update = [];
        
        while ($row = mysqli_fetch_assoc($result_query)) {
            $tenantId = (int)$row['tenant_id'];
            $companyName = $row['company_name'];
            
            $baseUsername = strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $companyName)));
            $baseUsername = substr($baseUsername, 0, 30);
            
            if (strlen($baseUsername) === 0) {
                $baseUsername = 'clinic';
            }
            
            $username = $baseUsername;
            $counter = 1;
            
            while ($counter <= 1000) {
                $checkStmt = mysqli_prepare($conn, "SELECT tenant_id FROM tenants WHERE username = ? LIMIT 1");
                mysqli_stmt_bind_param($checkStmt, "s", $username);
                mysqli_stmt_execute($checkStmt);
                $checkResult = mysqli_stmt_get_result($checkStmt);
                
                if (mysqli_num_rows($checkResult) === 0) {
                    break;
                }
                
                $username = $baseUsername . $counter;
                $counter++;
            }
            
            if ($counter > 1000) {
                $errors_update[] = "Could not generate unique username for tenant {$tenantId}";
            } else {
                $updateStmt = mysqli_prepare($conn, "UPDATE tenants SET username = ? WHERE tenant_id = ?");
                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, "si", $username, $tenantId);
                    if (mysqli_stmt_execute($updateStmt)) {
                        $updated++;
                    } else {
                        $errors_update[] = "Failed to update tenant {$tenantId}: " . mysqli_error($conn);
                    }
                }
            }
        }
        
        if (empty($errors_update)) {
            $update_message = "✓ Updated {$updated} tenants with usernames!";
            $update_type = 'success';
        } else {
            $update_message = "Updated {$updated} tenants, but {$count($errors_update)} had errors.";
            $update_type = 'warning';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OralSync | Database Migrations</title>
    <link rel="stylesheet" href="style1.css">
    <style>
        .migration-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .migration-container h1 {
            color: #0d3b66;
            margin-bottom: 10px;
        }
        
        .migration-subtitle {
            color: #64748b;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .migration-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #f8fafc;
        }
        
        .migration-card h3 {
            color: #0d3b66;
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .migration-card p {
            color: #64748b;
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .migration-button {
            background: #22c55e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 15px;
        }
        
        .migration-button:hover {
            background: #16a34a;
        }
        
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .message.warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        
        .error-details {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
            max-height: 300px;
            overflow-y: auto;
            font-size: 12px;
        }
        
        .error-item {
            padding: 8px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .error-item:last-child {
            border-bottom: none;
        }
        
        .error-statement {
            color: #666;
            font-family: monospace;
            margin-bottom: 4px;
        }
        
        .error-message {
            color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="migration-container">
        <h1>🔧 Database Migrations</h1>
        <p class="migration-subtitle">Apply pending database changes to enable new features</p>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
                <?php if ($result && isset($result['errors'])): ?>
                    <div class="error-details">
                        <strong>Errors encountered:</strong>
                        <?php foreach ($result['errors'] as $err): ?>
                            <div class="error-item">
                                <div class="error-statement"><?php echo htmlspecialchars($err['statement']); ?></div>
                                <div class="error-message"><?php echo htmlspecialchars($err['error']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($update_message): ?>
            <div class="message <?php echo $update_type; ?>">
                <?php echo htmlspecialchars($update_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="migration-card">
                <h3>Add Password Reset Functionality</h3>
                <p>Adds username and password reset support to both tenant and super admin accounts. This migration adds the following columns:</p>
                <ul style="color: #64748b; font-size: 14px; margin: 10px 0;">
                    <li><code>tenants.username</code> - Clinic login username (UNIQUE)</li>
                    <li><code>tenants.password_reset_token</code> - Temporary password reset token</li>
                    <li><code>tenants.password_reset_expires</code> - Token expiration timestamp</li>
                    <li><code>super_admins.password_reset_token</code> - Admin reset token</li>
                    <li><code>super_admins.password_reset_expires</code> - Admin token expiration</li>
                </ul>
                <button type="submit" name="migrate" value="add_password_reset" class="migration-button">
                    ✓ Run Migration
                </button>
            </div>
            
            <div class="migration-card">
                <h3>Update Existing Tenants with Usernames</h3>
                <p>Generates unique usernames for all existing clinics that don't have one yet. Usernames are auto-generated based on clinic name and stored in the database.</p>
                <button type="submit" name="update_usernames" value="1" class="migration-button">
                    ✓ Update Usernames
                </button>
            </div>
        </form>
        
        <div class="migration-card" style="background: #f0f9ff; border-color: #bfdbfe;">
            <h3 style="color: #1e40af;">📋 Migration Status</h3>
            <p><strong>Current Features:</strong></p>
            <ul style="color: #64748b; font-size: 14px; margin: 10px 0;">
                <li>✓ Forgot password pages created</li>
                <li>✓ Username field added to registration form</li>
                <li>✓ Tenant login changed to username/password</li>
                <li>⏳ Database schema changes pending (run migrations above)</li>
            </ul>
        </div>
    </div>
</body>
</html>
