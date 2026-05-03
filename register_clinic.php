<?php
ob_start(); 
header('Content-Type: application/json');
require_once __DIR__ . '/includes/connect.php'; 
require_once __DIR__ . '/includes/subscription_tiers.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/tenant_tier_helper.php';

$response = [
    'success' => false, 
    'message' => 'An unexpected error occurred.'
];

require_once __DIR__ . '/includes/onboarding_utils.php';


try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $clinicName = mysqli_real_escape_string($conn, $_POST['clinicName'] ?? '');
        $ownerName = mysqli_real_escape_string($conn, $_POST['ownerName'] ?? '');
        $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
        $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
        $city = mysqli_real_escape_string($conn, $_POST['city'] ?? '');
        $province = mysqli_real_escape_string($conn, $_POST['province'] ?? '');
        $homepage_url = mysqli_real_escape_string($conn, $_POST['homepage_url'] ?? '');
        $tier   = trim((string)($_POST['tier'] ?? 'startup'));
        $start_date = trim((string)($_POST['start_date'] ?? ''));
        $duration = (int)($_POST['duration'] ?? 12);
        
        if ($username === '') {
            throw new Exception("Username is required for clinic registration.");
        }
        if (!preg_match('/^[A-Za-z0-9_\-]+$/', $username)) {
            throw new Exception("Username may only contain letters, numbers, hyphens, and underscores.");
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format. Please use a valid email address.");
        }
        
        // Validate tier
        if (!isValidTier($tier)) {
            throw new Exception("Invalid subscription tier selected.");
        }
        
        // Validate start date
        if ($start_date === '') {
            $start_date = date('Y-m-d');
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            throw new Exception("Invalid start date format. Use YYYY-MM-DD.");
        }
        
        // Validate duration
        if ($duration < 1 || $duration > 120) {
            throw new Exception("Duration must be between 1 and 120 months.");
        }

        // REQUIRED: Validate at least one clinic document uploaded
        $validDocsCount = 0;
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $maxSizeMB = 5;
        $maxSizeBytes = $maxSizeMB * 1024 * 1024;

        if (isset($_FILES['documents']) && is_array($_FILES['documents']['tmp_name'])) {
            foreach ($_FILES['documents']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK &&
                    $_FILES['documents']['size'][$key] <= $maxSizeBytes) {
                    $ext = strtolower(pathinfo($_FILES['documents']['name'][$key], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed)) {
                        $validDocsCount++;
                    }
                }
            }
        }

        if ($validDocsCount === 0) {
            throw new Exception("Please upload at least one valid clinic document (PDF, DOC, DOCX, JPG, PNG; max " . $maxSizeMB . "MB each) before registering the tenant.");
        }
        
        // 1. Generate Auto-Password
        $temp_password = substr(bin2hex(random_bytes(4)), 0, 8);
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // 2. Duplicate Check (clinic name, username, email)
        $checkQuery = "SELECT company_name, username, contact_email FROM tenants WHERE company_name = ? OR username = ? OR contact_email = ? LIMIT 1";
        $stmtCheck = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($stmtCheck, "sss", $clinicName, $username, $email);
        mysqli_stmt_execute($stmtCheck);
        $resultCheck = mysqli_stmt_get_result($stmtCheck);

        if ($row = mysqli_fetch_assoc($resultCheck)) {
            mysqli_stmt_close($stmtCheck);
            if ($row['company_name'] === $clinicName) {
                throw new Exception("Clinic name already exists.");
            }
            if ($row['username'] === $username) {
                throw new Exception("Clinic username is already taken. Please choose a different username.");
            }
            if ($row['contact_email'] === $email) {
                throw new Exception("Email address is already registered. Please use a different email.");
            }
        }
        mysqli_stmt_close($stmtCheck);

        // 3. Generate Slug and Tenant Code
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $clinicName))) . '-' . substr(uniqid(), -4);
        $tenant_code = generateUniqueTenantCode($conn);
        $homepage_url = "Landing Page/tenant_homepage.php?tenant=" . $slug;

        // 4. Insert clinic into database
        $initial_status = ($tier === 'trial') ? 'active' : 'inactive';
        
        $sql = "INSERT INTO tenants (company_name, owner_name, username, contact_email, password, phone, address, city, province, subdomain_slug, homepage_url, tenant_code, status, subscription_tier, subscription_start_date, subscription_duration) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssssssssssssi", $clinicName, $ownerName, $username, $email, $hashed_password, $phone, $address, $city, $province, $slug, $homepage_url, $tenant_code, $initial_status, $tier, $start_date, $duration);

        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($conn);
            
            // Log activity (single instance)
            logSuperAdminActivity($conn, 'Registration', "Registered: $clinicName (Tier: $tier, Status: $initial_status)", $email, 'Super Admin');
            
            // Record initial subscription payment for the tenant
            $tier_data = getTierByKey($tier);
            $monthly_amount = $tier_data['price_min'] ?? 0;
            $total_amount = $monthly_amount * $duration;
            
            $billing_period_start = $start_date . ' 00:00:00';
            $billing_period_end = date('Y-m-d 23:59:59', strtotime('+' . max(1, $duration) . ' months -1 day', strtotime($start_date)));
            $payment_date = date('Y-m-d H:i:s');
            
            $procedures_json = json_encode([
                'item' => 'Initial Subscription',
                'tier' => $tier,
                'billing_period_start' => $billing_period_start,
                'billing_period_end' => $billing_period_end,
                'duration' => $duration
            ]);

            $paymongo_url = null;
            $paymongo_session_id = null;
            $payment_status = ($tier === 'trial' || $total_amount <= 0) ? 'paid' : 'pending';

            // Generate PayMongo link if it's a paid tier
            if ($payment_status === 'pending') {
                $pm_config = require __DIR__ . '/config/paymongo.php';
                $secret = $pm_config['secret_key'] ?? '';
                
                if ($secret) {
                    $auth = base64_encode($secret . ':');
                    $amount_centavos = (int) round($total_amount * 100);
                    $description = "OralSync Subscription: " . ucfirst($tier) . " ($duration months)";
                    
                    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                    $base_url = rtrim($base_url, '/') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                    
                    $payload = json_encode([
                        'data' => [
                            'attributes' => [
                                'payment_method_types' => ['gcash', 'card', 'paymaya', 'grab_pay'],
                                'line_items' => [[
                                    'currency'    => 'PHP',
                                    'amount'      => $amount_centavos,
                                    'description' => $description,
                                    'name'        => 'OralSync - ' . ucfirst($tier) . ' Plan',
                                    'quantity'    => 1,
                                ]],
                                'description' => $description,
                                'send_email_receipt' => true,
                                'metadata' => [
                                    'tenant_id' => (string)$new_id,
                                    'tier_key' => $tier,
                                    'type' => 'initial_registration',
                                    'duration' => (string)$duration
                                ]
                            ],
                        ],
                    ]);

                    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => $payload,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER     => [
                            'Authorization: Basic ' . $auth,
                            'Content-Type: application/json',
                            'Accept: application/json',
                        ],
                    ]);

                    $pm_response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($http_code === 200) {
                        $pm_data = json_decode($pm_response, true);
                        $paymongo_url = $pm_data['data']['attributes']['checkout_url'] ?? null;
                        $paymongo_session_id = $pm_data['data']['id'] ?? null;
                    }
                }
            }

            $revenue_sql = "INSERT INTO payment (tenant_id, amount, status, payment_date, procedures_json, paymongo_link_id) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $revenue_stmt = mysqli_prepare($conn, $revenue_sql);
            if ($revenue_stmt) {
                mysqli_stmt_bind_param($revenue_stmt, "idssss", $new_id, $total_amount, $payment_status, $payment_date, $procedures_json, $paymongo_session_id);
                mysqli_stmt_execute($revenue_stmt);
                mysqli_stmt_close($revenue_stmt);
            }

            // Handle file uploads
            if (isset($_FILES['documents']) && is_array($_FILES['documents']['tmp_name'])) {
                $upload_dir = __DIR__ . '/uploads/tenant_docs/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['documents']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                        $original_name = mysqli_real_escape_string($conn, $_FILES['documents']['name'][$key]);
                        $file_type = $_FILES['documents']['type'][$key];
                        $file_size = $_FILES['documents']['size'][$key];
                        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                        
                        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                        if (in_array($ext, $allowed)) {
                            $safe_name = uniqid('doc_' . $new_id . '_') . '.' . $ext;
                            $dest_path = $upload_dir . $safe_name;
                            
                            if (move_uploaded_file($tmp_name, $dest_path)) {
                                $db_path = 'uploads/tenant_docs/' . $safe_name;
                                $doc_sql = "INSERT INTO tenant_documents (tenant_id, document_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)";
                                $doc_stmt = mysqli_prepare($conn, $doc_sql);
                                mysqli_stmt_bind_param($doc_stmt, "isssi", $new_id, $original_name, $db_path, $file_type, $file_size);
                                mysqli_stmt_execute($doc_stmt);
                                mysqli_stmt_close($doc_stmt);
                            }
                        }
                    }
                }
            }
            
            // 5. Initialize Default Clinic Schedule
            $defaultScheds = [
                ['Monday', '09:00:00', '17:00:00', 0],
                ['Tuesday', '09:00:00', '17:00:00', 0],
                ['Wednesday', '09:00:00', '17:00:00', 0],
                ['Thursday', '09:00:00', '17:00:00', 0],
                ['Friday', '09:00:00', '17:00:00', 0],
                ['Saturday', '09:00:00', '13:00:00', 0],
                ['Sunday', '09:00:00', '17:00:00', 1]
            ];
            $schedSql = "INSERT INTO clinic_schedules (tenant_id, day_of_week, opening_time, closing_time, is_closed) VALUES (?, ?, ?, ?, ?)";
            $schedStmt = mysqli_prepare($conn, $schedSql);
            if ($schedStmt) {
                foreach ($defaultScheds as $ds) {
                    mysqli_stmt_bind_param($schedStmt, "isssi", $new_id, $ds[0], $ds[1], $ds[2], $ds[3]);
                    mysqli_stmt_execute($schedStmt);
                }
                mysqli_stmt_close($schedStmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception("Database error: " . mysqli_error($conn));
        }

        // 6. Conditional Onboarding Email
        $email_sent = false;
        $login_url = buildTenantLoginUrl($slug);
        
        // Only send email immediately if it's a trial (already active)
        if ($initial_status === 'active') {
            $emailResult = sendTenantOnboardingEmail([
                'clinic_name' => $clinicName,
                'owner_name' => $ownerName,
                'owner_email' => $email,
                'temp_password' => $temp_password,
                'login_url' => $login_url
            ]);
            $email_sent = (bool)($emailResult['sent'] ?? false);
        }

        $response = [
            'success' => true, 
            'message' => ($initial_status === 'active') ? 'Clinic registered successfully!' : 'Clinic registered. Waiting for payment.',
            'slug' => $slug,
            'tenant_code' => $tenant_code,
            'status' => $initial_status,
            'checkout_url' => $paymongo_url,
            'email_sent' => $email_sent
        ];
    }
} catch (Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    $response['message'] = "Error: " . $e->getMessage();
    $response['success'] = false;
    echo json_encode($response);
    exit;
}

if (ob_get_level()) ob_end_clean();
echo json_encode($response);
exit;
?>

