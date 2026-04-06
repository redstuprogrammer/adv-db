<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';

// Role Check Implementation - Ensure user is a Receptionist
if (!isset($_SESSION['role'])) {
    header("Location: tenant_login.php");
    exit();
}

if ($_SESSION['role'] !== 'Receptionist') {
    header("Location: tenant_login.php");
    exit();
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function getServiceNamesFromJson(string $json): string {
    $procedures = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($procedures)) {
        return 'General Service';
    }
    $names = array_column($procedures, 'name');
    return implode(', ', $names) ?: 'General Service';
}

function formatTenantPatientId($tenant_patient_id) {
    return '#' . str_pad($tenant_patient_id, 4, '0', STR_PAD_LEFT);
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();
$receptionistName = $_SESSION['username'] ?? 'Receptionist';

/* =========================================
   2. DATA FETCHING (Billing List)
========================================= */

$query = "SELECT 
            py.payment_id, 
            p.patient_id,
            p.first_name, 
            p.last_name, 
            py.amount, 
            py.mode, 
            py.status,
            a.appointment_id,
            a.appointment_date,
            py.procedures_json
          FROM payment py
          LEFT JOIN appointment a ON py.appointment_id = a.appointment_id
          LEFT JOIN patient p ON a.patient_id = p.patient_id
          WHERE py.tenant_id = ?
          ORDER BY py.payment_id DESC";

$result = null;
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $tenantId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

$services = [];
$serviceStmt = mysqli_prepare($conn, "SELECT service_id, service_name, price FROM service WHERE tenant_id = ? ORDER BY service_name ASC");
if ($serviceStmt) {
    mysqli_stmt_bind_param($serviceStmt, 'i', $tenantId);
    mysqli_stmt_execute($serviceStmt);
    $serviceResult = mysqli_stmt_get_result($serviceStmt);
    while ($serviceRow = mysqli_fetch_assoc($serviceResult)) {
        $services[] = $serviceRow;
    }
    mysqli_stmt_close($serviceStmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Billing Management</title>
    <link rel="stylesheet" href="tenant_style.css">
    <style>
        /* UI Elements */
        .content-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-top: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .data-table th { background: #f8fafc; color: #0d3b66; padding: 12px; text-align: left; font-size: 13px; font-weight: 700; border-bottom: 1px solid #e2e8f0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        
        /* Status Pills */
        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .paid { background: #dcfce7; color: #166534; }
        .installment { background: #fef9c3; color: #854d0e; }

        /* Buttons */
        .btn-action { text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; border: 1px solid transparent; }
        .btn-print { background: #f8fafc; color: #0d3b66; border-color: #0d3b66; }
        .btn-print:hover { background: #0d3b66; color: #fff; }
        .btn-edit { background: #ecfdf5; color: #059669; border-color: #059669; margin-left: 5px; }
        .btn-edit:hover { background: #059669; color: #fff; }
        .add-btn-main { background: #0d3b66; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; }

        /* Modal Logic */
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal-content { background: white; padding: 30px; border-radius: 15px; width: 450px; position: relative; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .close-x { position: absolute; right: 20px; top: 15px; cursor: pointer; font-size: 24px; color: #64748b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #0d3b66; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }

        .live-clock-badge {
            background: linear-gradient(135deg, rgba(13, 59, 102, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
            border: 2px solid #0d3b66;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 700;
            color: #0d3b66;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
            white-space: nowrap;
        }
    </style>
</head>
<body>

<div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <div class="tenant-main-content">
        <div class="tenant-header-bar">
            <div>
                <h1 style="color: #0d3b66; margin:0;">Billing & Payments</h1>
                <p style="color: #64748b; margin: 6px 0 0;">Manage invoices and transaction records</p>
            </div>
            <?php renderDateClock(); ?>
        </div>

        <section style="display: flex; justify-content: space-between; align-items: center; padding: 20px 0;">
            <input type="text" id="tableSearch" placeholder="Search patient..." onkeyup="filterMainTable()" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px; width: 300px;">
            <button class="add-btn-main" onclick="openAddModal()">+ Create Invoice</button>
        </section>

        <!-- Message Display -->
        <?php if (isset($_SESSION['success'])): ?>
            <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
                <?php echo h($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['errors']) && is_array($_SESSION['errors'])): ?>
            <div style="background: #fef2f2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca;">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($_SESSION['errors'] as $error): ?>
                        <li><?php echo h($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php unset($_SESSION['errors']); ?>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <table class="data-table" id="paymentTable">
                <thead>
                    <tr>
                        <th>Inv #</th>
                        <th>Patient Name</th>
                        <th>Service</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                            <?php if($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= str_pad($row['payment_id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                                <td><?= h(($row['first_name'] ?? '') . " " . ($row['last_name'] ?? '')) ?></td>
                                <td><?= h(getServiceNamesFromJson($row['procedures_json'] ?? '')) ?></td>
                                <td style="font-weight: 600;">₱<?= number_format($row['amount'], 2) ?></td>
                                <td><span class="status-pill <?= strtolower(str_replace(' ', '', $row['status'] ?? '')) ?>"><?= h($row['status'] ?? '') ?></span></td>
                                <td>
                                    <a href="print_invoice.php?tenant=<?php echo rawurlencode($tenantSlug); ?>&id=<?= $row['payment_id'] ?>" class="action-link" target="_blank">Print</a>
                                    <a onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="action-link">Edit</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px; color:#94a3b8;">No payment records found.</td></tr>
                        <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="close-x" onclick="closeModal()">&times;</span>
        <h3 id="modalTitle" style="color: #0d3b66; margin:0 0 20px 0;">Create Invoice</h3>
        
        <form action="process_payment.php" method="POST" id="paymentForm">
            <input type="hidden" name="tenant_id" value="<?php echo $tenantId; ?>">
            <input type="hidden" name="payment_id" id="payment_id">
            
            <div class="form-group">
                <label>Patient <span style="color: red;">*</span></label>
                <select name="patient_id" id="patient_dropdown" onchange="loadPatientAppointments(this.value)" required>
                    <option value="">-- Select Patient --</option>
                    <?php 
                    $pStmt = mysqli_prepare($conn, "SELECT patient_id, tenant_patient_id, first_name, last_name FROM patient WHERE tenant_id = ? ORDER BY last_name ASC");
                    if ($pStmt) {
                        mysqli_stmt_bind_param($pStmt, "i", $tenantId);
                        mysqli_stmt_execute($pStmt);
                        $pResult = mysqli_stmt_get_result($pStmt);
                        while($p = mysqli_fetch_assoc($pResult)) {
                            echo "<option value='".$p['patient_id']."'>".h(formatTenantPatientId($p['tenant_patient_id']) . ' - ' . ($p['first_name'] ?? '')." ".($p['last_name'] ?? ''))."</option>";
                        }
                        mysqli_stmt_close($pStmt);
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Add Services to Cart</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <select id="service_dropdown">
                        <option value="">-- Select Service --</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo (int)$service['service_id']; ?>" data-name="<?php echo h($service['service_name']); ?>" data-price="<?php echo number_format((float)$service['price'], 2, '.', ''); ?>"><?php echo h($service['service_name']); ?> — ₱<?php echo number_format((float)$service['price'], 2); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="addToCart()" style="padding: 8px 16px; background: #0d3b66; color: white; border: none; border-radius: 6px; cursor: pointer;">Add to Cart</button>
                </div>
            </div>

            <div class="form-group">
                <label>Selected Services</label>
                <div id="cart-list" style="border: 1px solid #e2e8f0; border-radius: 8px; min-height: 100px; padding: 10px; background: #f8fafc;">
                    <p id="cart-empty" style="color: #64748b; margin: 0;">No services added yet.</p>
                </div>
            </div>

            <div class="form-group">
                <label>Related Appointment <span style="color: red;">*</span></label>
                <select name="appointment_id" id="appointment_dropdown" required>
                    <option value="">-- Choose Patient First --</option>
                </select>
            </div>

            <input type="hidden" name="procedures_json" id="procedures_json">

            <div class="form-group">
                <label>Total Amount (₱) <span style="color: red;">*</span></label>
                <input type="number" name="amount" id="amount_input" step="0.01" min="0.01" required readonly>
            </div>

            <div class="form-group">
                <label>Payment Mode <span style="color: red;">*</span></label>
                <select name="mode" id="mode" required>
                    <option value="">-- Select --</option>
                    <option value="Cash">Cash</option>
                    <option value="GCash">GCash</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Check">Check</option>
                </select>
            </div>

            <div class="form-group">
                <label>Status <span style="color: red;">*</span></label>
                <select name="status" id="status" required>
                    <option value="">-- Select --</option>
                    <option value="Paid">Fully Paid</option>
                    <option value="Installment">Installment</option>
                    <option value="Pending">Pending</option>
                </select>
            </div>

            <button type="submit" class="add-btn-main" style="width:100%; margin-top:10px;">Save Transaction</button>
        </form>
    </div>
</div>

<script>
    <?php printDateClockScript(); ?>
</script>

<script>
    let cart = [];

    function addToCart() {
        const serviceSelect = document.getElementById('service_dropdown');
        const selectedOption = serviceSelect.selectedOptions[0];
        if (!selectedOption || !selectedOption.value) return;

        const serviceId = selectedOption.value;
        const serviceName = selectedOption.dataset.name;
        const price = parseFloat(selectedOption.dataset.price);

        // Check if already in cart
        if (cart.some(item => item.service_id == serviceId)) {
            alert('Service already in cart.');
            return;
        }

        cart.push({ service_id: serviceId, name: serviceName, price: price });
        updateCartDisplay();
        updateTotal();
        serviceSelect.value = '';
    }

    function removeFromCart(serviceId) {
        cart = cart.filter(item => item.service_id != serviceId);
        updateCartDisplay();
        updateTotal();
    }

    function updateCartDisplay() {
        const cartList = document.getElementById('cart-list');
        const cartEmpty = document.getElementById('cart-empty');
        cartList.innerHTML = '';

        if (cart.length === 0) {
            cartList.appendChild(cartEmpty);
            return;
        }

        cart.forEach(item => {
            const div = document.createElement('div');
            div.style.display = 'flex';
            div.style.justifyContent = 'space-between';
            div.style.alignItems = 'center';
            div.style.padding = '5px 0';
            div.innerHTML = `
                <span>${item.name} - ₱${item.price.toFixed(2)}</span>
                <button type="button" onclick="removeFromCart(${item.service_id})" style="background: #dc3545; color: white; border: none; border-radius: 4px; padding: 2px 6px; cursor: pointer;">Remove</button>
            `;
            cartList.appendChild(div);
        });
    }

    function updateTotal() {
        const total = cart.reduce((sum, item) => sum + item.price, 0);
        document.getElementById('amount_input').value = total.toFixed(2);
        document.getElementById('procedures_json').value = JSON.stringify(cart);
    }

    // Verification log
    console.log('UI Parity Active - Version 2.0');
    console.log('Receptionist Billing Page Initialized');
    console.log('FINAL UI SYNC COMPLETE');
    console.log('Anti-Crash System Active - V2');

    // 2. Modal Toggle
    function openAddModal() {
      document.getElementById('paymentForm').reset();
      document.getElementById('payment_id').value = "";
      document.getElementById('modalTitle').innerText = "Create New Invoice";
      cart = [];
      updateCartDisplay();
      updateTotal();
      document.getElementById("paymentModal").style.display = "flex";
    }

    function closeModal() {
      document.getElementById("paymentModal").style.display = "none";
    }

    // 3. Edit Modal Trigger - Simplified for now
    function openEditModal(data) {
        alert('Editing existing invoices with cart system is not yet implemented. Please create a new invoice.');
        return;
        // Future: Load cart from procedures_json
    }

    // 4. Dynamic Appointment Loading
    function loadPatientAppointments(patientId, selectedApptId = null) {
      const apptSelect = document.getElementById('appointment_dropdown');
      apptSelect.innerHTML = '<option value="">-- Select Appointment --</option>';
      
      if (!patientId) return;

      const params = new URLSearchParams({
        patient_id: patientId,
        tenant_id: <?php echo $tenantId; ?>
      });
      
      fetch('get_patient_services.php?' + params)
        .then(res => res.json())
        .then(data => {
          data.forEach(item => {
            let opt = document.createElement('option');
            opt.value = item.appointment_id;
            opt.textContent = "Appt: " + new Date(item.appointment_date).toLocaleDateString();
            if(selectedApptId && item.appointment_id == selectedApptId) opt.selected = true;
            apptSelect.appendChild(opt);
          });
        })
        .catch(err => console.error('Error loading appointments:', err));
    }

    // 5. Search Filter
    function filterMainTable() {
        let q = document.getElementById('tableSearch').value.toLowerCase();
        let rows = document.querySelectorAll('#paymentTable tbody tr');
        rows.forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    }

    // Close modal if clicking outside
    window.onclick = function(e) {
        if (e.target.id === 'paymentModal') closeModal();
    }
</script>
</body>
</html>

