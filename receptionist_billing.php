<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_utils.php';

// Role Check Implementation - Ensure user is logged in as receptionist
$sessionManager = SessionManager::getInstance();
$sessionManager->requireTenantUser('receptionist');

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/includes/date_clock.php';

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
// requireTenantLogin is now handled by session manager above

$tenantName = $sessionManager->getTenantData()['tenant_name'] ?? '';
$tenantId = $sessionManager->getTenantId();
$receptionistName = $sessionManager->getUsername() ?? 'Receptionist';

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

$tenantConfig = getTenantConfig($tenantId);
$bookingDepositAmount = isset($tenantConfig['booking_deposit_amount']) ? (float)$tenantConfig['booking_deposit_amount'] : 0.0;
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

        .service-multi-input {
            position: relative;
        }
        .service-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        .service-tags {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 8px;
            min-height: 40px;
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }
        .service-tag {
            background: #0d3b66;
            color: white;
            padding: 4px 8px;
            border-radius: 16px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .tag-remove {
            background: none;
            border: none;
            color: white;
            font-size: 14px;
            cursor: pointer;
            padding: 0 2px;
        }
        #toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #ef4444;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(400px);
            transition: transform 0.3s ease;
            z-index: 10000;
            max-width: 300px;
        }
        #toast.show {
            transform: translateX(0);
        }
        .floor-info {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
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

        <section style="display: flex; justify-content: space-between; align-items: center; padding: 20px 0; gap: 12px; flex-wrap: wrap;">
            <input type="text" id="tableSearch" placeholder="Search patient..." onkeyup="filterMainTable()" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px; width: 300px;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <button class="add-btn-main" onclick="openDepositModal()" style="background: #14b8a6;">Set Booking Downpayment</button>
                <button class="add-btn-main" onclick="openAddModal()">+ Create Invoice</button>
            </div>
        </section>
        <div style="padding: 12px 0 0; color: #0f172a; font-size: 14px;">
            Current booking downpayment: <strong><?php echo $bookingDepositAmount > 0 ? '₱' . number_format($bookingDepositAmount, 2) : 'None configured'; ?></strong>
        </div>

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
        
        <form action="process_payment.php?tenant=<?php echo rawurlencode($tenantSlug); ?>" method="POST" id="paymentForm">
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
                <label>Services (multi-select searchable)</label>
                <div class="service-multi-input">
                    <input type="text" id="service_input" class="service-input" placeholder="Type service name, press Enter or , to add..." />
                    <datalist id="service-list">
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo h($service['service_name']); ?>" data-id="<?php echo (int)$service['service_id']; ?>" data-price="<?php echo (float)$service['price']; ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <div id="service-tags" class="service-tags">
                        <p id="cart-empty" style="color: #64748b; margin: 0;">No services added yet.</p>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Related Appointment <span style="color: red;">*</span></label>
                <select name="appointment_id" id="appointment_dropdown" required onchange="updateTotal()">
                    <option value="">-- Choose Patient First --</option>
                </select>
            </div>

    <input type="hidden" name="procedures_json" id="procedures_json">

    <div class="form-group">
        <label>Downpayment Applied</label>
        <input type="text" id="deposit_info" readonly value="<?php echo $bookingDepositAmount > 0 ? 'Clinic deposit: ₱' . number_format($bookingDepositAmount, 2) : 'No deposit configured'; ?>">
    </div>

    <div id="toast"></div>

            <div class="form-group">
                <label>Total Amount (₱) <span style="color: red;">*</span></label>
                <input type="number" name="amount" id="amount_input" step="0.01" min="0" required oninput="validateTotal()">
                <div id="floor-info" class="floor-info"></div>
            </div>

            <input type="hidden" name="status" id="status" value="Pending">

            <button type="submit" class="add-btn-main" style="width:100%; margin-top:10px;">Save Transaction</button>
        </form>
    </div>
</div>

<div id="depositModal" class="modal">
    <div class="modal-content">
        <span class="close-x" onclick="closeDepositModal()">&times;</span>
        <h3 style="color: #0d3b66; margin:0 0 20px 0;">Clinic Booking Downpayment</h3>
        <p style="color: #64748b; margin-bottom: 20px;">Set the amount that will be deducted from invoices when the appointment was requested by the patient.</p>
        <div class="form-group">
            <label>Downpayment Amount (₱)</label>
            <input type="number" id="booking_deposit_amount" step="0.01" min="0.00" value="<?php echo number_format($bookingDepositAmount, 2, '.', ''); ?>">
        </div>
        <button type="button" class="add-btn-main" onclick="saveDepositConfig()" style="width:100%;">Save Downpayment</button>
        <div id="depositModalMessage" style="margin-top: 12px; color: #0d3b66;"></div>
    </div>
</div>

<script>
    <?php printDateClockScript(); ?>
</script>

<script>
    let bookingDepositAmount = <?php echo json_encode($bookingDepositAmount); ?>;
    let cart = [];

    function getSelectedAppointmentDeposit() {
        const apptSelect = document.getElementById('appointment_dropdown');
        const selectedOption = apptSelect.selectedOptions[0];
        if (!selectedOption || !selectedOption.value) return 0;
        const requestedBy = selectedOption.dataset.requestedBy || '';
        return requestedBy.toLowerCase() === 'patient' ? Number(bookingDepositAmount || 0) : 0;
    }

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
        cartList.innerHTML = '';

        if (cart.length === 0) {
            cartList.innerHTML = '<p id="cart-empty" style="color: #64748b; margin: 0;">No services added yet.</p>';
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
        const depositAmount = getSelectedAppointmentDeposit();
        const amountInput = document.getElementById('amount_input');
        const depositInfo = document.getElementById('deposit_info');
        const amountDue = parseFloat(Math.max(total - depositAmount, 0).toFixed(2));

        amountInput.min = amountDue.toFixed(2);
        amountInput.value = amountDue.toFixed(2);
        document.getElementById('procedures_json').value = JSON.stringify(cart);

        if (depositAmount > 0) {
            depositInfo.value = `Deposit available: ₱${depositAmount.toFixed(2)} will be applied to this invoice.`;
        } else if (bookingDepositAmount > 0) {
            depositInfo.value = 'Deposit configured but not applicable to the selected appointment.';
        } else {
            depositInfo.value = 'No clinic downpayment configured';
        }
    }

    function validateAmount() {
        const amountInput = document.getElementById('amount_input');
        const total = cart.reduce((sum, item) => sum + item.price, 0);
        const depositAmount = getSelectedAppointmentDeposit();
        const minAmount = parseFloat(Math.max(total - depositAmount, 0).toFixed(2));
        let value = parseFloat(amountInput.value);
        if (isNaN(value) || value < minAmount) {
            amountInput.value = minAmount.toFixed(2);
        }
    }

// NEW MULTI-SERVICE LOGIC
    let cart = [];
    const serviceInput = document.getElementById('service_input');
    const serviceTags = document.getElementById('service-tags');
    const serviceList = document.getElementById('service-list');
    const proceduresJson = document.getElementById('procedures_json');

    // Service add multi
    serviceInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const value = serviceInput.value.trim();
        if (value) {
          const option = [...serviceList.options].find(opt => opt.value.toLowerCase() === value.toLowerCase());
          if (option) {
            cart.push({
              service_id: option.dataset.id,
              name: option.value,
              price: parseFloat(option.dataset.price)
            });
            renderTags();
            updateTotal();
            serviceInput.value = '';
          } else {
            alert('Service not found');
          }
        }
      }
    });

    function renderTags() {
      if (cart.length === 0) {
        serviceTags.innerHTML = '<p style="color: #64748b; margin: 0;">No services added</p>';
        return;
      }
      serviceTags.innerHTML = cart.map(item => `
        <span class="service-tag">
          ${item.name} <small>₱${item.price.toFixed(2)}</small>
          <button class="tag-remove" onclick="removeFromCart(${item.service_id})">&times;</button>
        </span>
      `).join('');
    }

    window.removeFromCart = (id) => {
      cart = cart.filter(item => item.service_id != id);
      renderTags();
      updateTotal();
    };

    function getSelectedAppointmentDeposit() {
      const opt = document.getElementById('appointment_dropdown').selectedOptions[0];
      if (!opt) return 0;
      const requestedBy = opt.dataset.requestedBy || '';
      const arrived = opt.dataset.arrived === 'true';
      return (requestedBy === 'patient' && arrived) ? bookingDepositAmount : 0;
    }

    function updateTotal() {
      const subtotal = cart.reduce((sum, item) => sum + item.price, 0);
      const deposit = getSelectedAppointmentDeposit();
      const floor = subtotal - deposit;
      const amountInput = document.getElementById('amount_input');
      const floorInfo = document.getElementById('floor-info');
      const depositInfo = document.getElementById('deposit_info');

      amountInput.min = floor;
      if (parseFloat(amountInput.value || 0) < floor) amountInput.value = floor.toFixed(2);
      proceduresJson.value = JSON.stringify(cart);

      floorInfo.textContent = `Floor: ₱${floor.toFixed(2)} (${subtotal.toFixed(2)} services - ${deposit.toFixed(2)} deposit)`;
      depositInfo.value = deposit > 0 ? `Deducted: ₱${deposit.toFixed(2)} (patient appt arrived)` : 'No deduction';
    }

    function validateTotal() {
      const amountInput = document.getElementById('amount_input');
      const floor = parseFloat(amountInput.min);
      const value = parseFloat(amountInput.value);
      if (value < floor) {
        amountInput.value = floor.toFixed(2);
        showToast('Price cannot be lower than the base service total (floor protected).');
      }
    }

    function showToast(msg) {
      const toast = document.getElementById('toast');
      toast.textContent = msg;
      toast.classList.add('show');
      setTimeout(() => toast.classList.remove('show'), 4000);
    }

    // Enhance loadPatientAppointments for arrived
    const oldLoadAppts = loadPatientAppointments;
    loadPatientAppointments = (patientId, selectedApptId) => {
      oldLoadAppts(patientId, selectedApptId);
      // Assume get_patient_services.php returns status, requested_by
    };

// Verification log
    console.log('Billing multi-service + floor protection enabled');
    console.log('UI Parity Active - Version 2.0');

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

    function openDepositModal() {
      document.getElementById('booking_deposit_amount').value = bookingDepositAmount.toFixed(2);
      document.getElementById('depositModalMessage').textContent = '';
      document.getElementById('depositModal').style.display = 'flex';
    }

    function closeDepositModal() {
      document.getElementById('depositModal').style.display = 'none';
    }

    async function saveDepositConfig() {
      const amountField = document.getElementById('booking_deposit_amount');
      const rawAmount = amountField.value;
      const amount = parseFloat(rawAmount);

      if (isNaN(amount) || amount < 0) {
        document.getElementById('depositModalMessage').textContent = 'Please enter a valid non-negative amount.';
        return;
      }

      const response = await fetch('api/save_deposit_config.php?tenant=' + encodeURIComponent('<?php echo rawurlencode($tenantSlug); ?>'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ booking_deposit_amount: amount })
      });

      const result = await response.json();
      if (result.success) {
        document.getElementById('depositModalMessage').textContent = result.message;
        document.getElementById('deposit_info').value = amount > 0 ? `Clinic deposit configured: ₱${amount.toFixed(2)}` : 'No clinic downpayment configured';
        document.getElementById('depositModalMessage').style.color = '#166534';
        document.getElementById('depositModal').style.display = 'none';
        window.bookingDepositAmount = amount;
      } else {
        document.getElementById('depositModalMessage').textContent = result.message || 'Unable to save deposit config.';
        document.getElementById('depositModalMessage').style.color = '#b91c1c';
      }
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
            opt.dataset.requestedBy = item.requested_by || '';
            if(selectedApptId && item.appointment_id == selectedApptId) opt.selected = true;
            apptSelect.appendChild(opt);
          });
          updateTotal();
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
        if (e.target.id === 'depositModal') closeDepositModal();
    }
</script>
</body>
</html>

