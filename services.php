<?php
// Extend session timeout
ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
session_set_cookie_params(['lifetime' => 86400 * 7, 'samesite' => 'Lax']);

session_start();
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';

// Role Check Implementation - Ensure user is logged in
if (!isset($_SESSION['role'])) {
    header("Location: tenant_login.php");
    exit();
}

// Role Check Implementation - Ensure user is an Admin
if ($_SESSION['role'] !== 'Admin') {
    header("Location: tenant_login.php");
    exit();
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$tenantSlug = trim((string)($_GET['tenant'] ?? ''));
requireTenantLogin($tenantSlug);

$tenantName = getCurrentTenantName();
$tenantId = getCurrentTenantId();

// Ensure service category column exists for the dropdown support
$serviceTableResult = mysqli_query($conn, "SHOW COLUMNS FROM service LIKE 'category'");
$categoryColumnExists = ($serviceTableResult && mysqli_num_rows($serviceTableResult) > 0);
if (!$categoryColumnExists) {
    @mysqli_query($conn, "ALTER TABLE service ADD COLUMN category varchar(100) DEFAULT 'General'");
    $serviceTableResult = mysqli_query($conn, "SHOW COLUMNS FROM service LIKE 'category'");
    $categoryColumnExists = ($serviceTableResult && mysqli_num_rows($serviceTableResult) > 0);
}

// Handle form submissions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_service'])) {
        $serviceName = trim($_POST['service_name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? 'General') ?: 'General';
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($serviceName) && $price > 0) {
            if ($categoryColumnExists) {
                $stmt = mysqli_prepare($conn, "INSERT INTO service (tenant_id, service_name, price, category, description) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "isdss", $tenantId, $serviceName, $price, $category, $description);
                }
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO service (tenant_id, service_name, price, description) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "isds", $tenantId, $serviceName, $price, $description);
                }
            }
            if ($stmt) {
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Service added successfully!';
                } else {
                    $message = 'Error adding service.';
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = 'Error preparing service insert.';
            }
        } else {
            $message = 'Please provide valid service name and price.';
        }
    } elseif (isset($_POST['delete_service'])) {
        $serviceId = intval($_POST['service_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM service WHERE service_id = ? AND tenant_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $serviceId, $tenantId);
            mysqli_stmt_execute($stmt);
            $message = 'Service deleted successfully!';
        }
    }
}

// Fetch services
$services = [];
$selectSql = "SELECT service_id, service_name, price, description";
if ($categoryColumnExists) {
    $selectSql .= ", category";
}
$selectSql .= " FROM service WHERE tenant_id = ? ORDER BY service_name";
$stmt = mysqli_prepare($conn, $selectSql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $tenantId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $services[] = $row;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenantName); ?> | Services</title>
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

      .btn-danger {
        background: #ef4444;
        color: white;
        padding: 6px 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        transition: background 0.2s ease;
      }

      .btn-danger:hover {
        background: #dc2626;
      }

      .module-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        margin-bottom: 24px;
      }

      .form-group {
        margin-bottom: 16px;
      }

      .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
        color: var(--accent);
      }

      .form-group input,
      .form-group select,
      .form-group textarea {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid var(--border);
        border-radius: 12px;
        font-size: 14px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        box-sizing: border-box;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
      }

      .form-group select {
        appearance: none;
        background-color: white;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%2364788b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2'%3E%3Cpolyline points='6 8 8 10 10 8'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        background-size: 14px;
      }

      .form-group textarea {
        min-height: 120px;
        resize: vertical;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
      }

      .form-group input:focus,
      .form-group select:focus,
      .form-group textarea:focus {
        border-color: rgba(13, 59, 102, 0.65);
        box-shadow: 0 0 0 4px rgba(13, 59, 102, 0.08);
        outline: none;
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

      .message {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-weight: 600;
      }

      .message.success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
      }

      .message.error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
      }

      .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.3s ease;
      }

      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }

      .modal-content {
        background: white;
        margin: 5% auto;
        padding: 0;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        max-width: 600px;
        animation: slideIn 0.3s ease;
      }

      @keyframes slideIn {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
      }

      .modal-header {
        background: var(--accent);
        color: white;
        padding: 20px;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 18px;
        font-weight: 700;
      }

      .modal-body {
        padding: 24px;
        max-height: 70vh;
        overflow-y: auto;
      }

      .modal-footer {
        padding: 20px 24px;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        background: #f8fafc;
        border-radius: 0 0 12px 12px;
      }

      .close {
        color: white;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        border: none;
        background: none;
        padding: 0;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: background 0.2s ease;
      }

      .close:hover {
        background: rgba(255, 255, 255, 0.2);
      }

      .service-detail-row {
        margin-bottom: 20px;
      }

      .service-detail-label {
        font-weight: 700;
        color: var(--accent);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
      }

      .service-detail-value {
        font-size: 14px;
        color: #334155;
        padding: 8px 12px;
        background: #f8fafc;
        border-radius: 6px;
        border-left: 3px solid var(--accent);
      }

      .btn-close {
        background: #64748b;
        color: white;
        padding: 10px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        transition: background 0.2s ease;
      }

      .btn-close:hover {
        background: #475569;
      }
    </style>
</head>
<body>
  <div class="tenant-layout">
    <?php include __DIR__ . '/includes/sidebar_main.php'; ?>

    <main class="tenant-main-content">
      <div class="tenant-header-bar">
        <div class="tenant-header-title">Services Management</div>
        <div style="display: flex; align-items: center; gap: 16px;">
          <div class="tenant-header-date"><?php echo date('l, M d, Y'); ?></div>
          <div id="liveClock" class="live-clock-badge">00:00:00 AM</div>
        </div>
      </div>

      <?php if (!empty($message)): ?>
      <div class="message <?php echo strpos($message, 'success') !== false ? 'success' : 'error'; ?>">
        <?php echo h($message); ?>
      </div>
      <?php endif; ?>

      <div class="module-card">
        <h2>Add New Service</h2>
        <form method="post">
          <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category" required>
              <option value="Preventive">Preventive</option>
              <option value="Pediatric">Pediatric</option>
              <option value="Prosthodontics">Prosthodontics</option>
              <option value="Cosmetic">Cosmetic</option>
              <option value="Orthodontics">Orthodontics</option>
              <option value="Surgery">Surgery</option>
              <option value="Restorative">Restorative</option>
              <option value="Others">Others</option>
            </select>
          </div>
          <div class="form-group">
            <label for="service_name">Service Name</label>
            <input type="text" id="service_name" name="service_name" required>
          </div>
          <div class="form-group">
            <label for="price">Price (₱)</label>
            <input type="number" id="price" name="price" step="0.01" min="0" required>
          </div>
          <div class="form-group">
            <label for="description">Full Description</label>
            <textarea id="description" name="description" rows="4" placeholder="Describe the service in detail"></textarea>
          </div>
          <button type="submit" name="add_service" class="btn-primary">Add Service</button>
        </form>
      </div>

      <div class="module-card">
        <h2>Existing Services</h2>
        <?php if (empty($services)): ?>
        <p>No services added yet.</p>
        <?php else: ?>
        <table class="module-table">
          <thead>
            <tr>
              <th>Service Name</th>
              <th>Category</th>
              <th>Price</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($services as $service): ?>
            <tr>
              <td><?php echo h($service['service_name']); ?></td>
              <td><?php echo h($service['category'] ?? 'General'); ?></td>
              <td>₱<?php echo number_format($service['price'], 2); ?></td>
              <td>
                <button type="button" class="btn-primary" style="margin-right: 8px;" onclick="viewService(<?php echo $service['service_id']; ?>, '<?php echo addslashes($service['service_name']); ?>', '<?php echo addslashes($service['description'] ?? ''); ?>', '<?php echo addslashes($service['category'] ?? 'General'); ?>', <?php echo $service['price']; ?>)">View</button>
                <form method="post" style="display: inline;">
                  <input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">
                  <button type="submit" name="delete_service" class="btn-danger" onclick="return confirm('Are you sure you want to delete this service?')">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <!-- Service Details Modal -->
  <div id="serviceModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <span id="serviceModalTitle">Service Details</span>
        <button class="close" onclick="closeServiceModal()">&times;</button>
      </div>
      <div class="modal-body">
        <div class="service-detail-row">
          <div class="service-detail-label">Service Name</div>
          <div class="service-detail-value" id="serviceModalName"></div>
        </div>
        <div class="service-detail-row">
          <div class="service-detail-label">Category</div>
          <div class="service-detail-value" id="serviceModalCategory"></div>
        </div>
        <div class="service-detail-row">
          <div class="service-detail-label">Price</div>
          <div class="service-detail-value" id="serviceModalPrice"></div>
        </div>
        <div class="service-detail-row">
          <div class="service-detail-label">Description</div>
          <div class="service-detail-value" id="serviceModalDescription"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-close" onclick="closeServiceModal()">Close</button>
      </div>
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

    function viewService(id, name, description, category, price) {
      document.getElementById('serviceModalTitle').textContent = name;
      document.getElementById('serviceModalName').textContent = name;
      document.getElementById('serviceModalCategory').textContent = category || 'General';
      document.getElementById('serviceModalPrice').textContent = '₱' + price.toFixed(2);
      document.getElementById('serviceModalDescription').textContent = description || 'No description provided.';
      document.getElementById('serviceModal').style.display = 'block';
    }

    function closeServiceModal() {
      document.getElementById('serviceModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      const modal = document.getElementById('serviceModal');
      if (event.target == modal) {
        modal.style.display = 'none';
      }
    }
  </script>
</body>
</html>


