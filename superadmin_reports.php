<?php
session_start();
require_once __DIR__ . '/security_headers.php';
if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadmin_login.php');
    exit;
}
require_once __DIR__ . '/connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Super Admin Reports</title>
    <link rel="stylesheet" href="style1.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --sa-primary: #0d3b66;
            --sa-muted: #64748b;
            --sa-border: #e2e8f0;
            --sa-bg: #f8fafc;
        }

        body {
            background-color: var(--sa-bg);
            color: #0f172a;
        }

        .sa-main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            margin-bottom: 10px;
        }

        .sa-main-header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--sa-primary);
            margin: 0;
        }

        .sa-main-header span {
            font-size: 0.85rem;
            color: var(--sa-muted);
        }

        .sa-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sa-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        .sa-profile span {
            font-weight: 600;
        }

        .sa-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .sa-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .sa-card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .sa-card-subtitle {
            font-size: 0.875rem;
            color: var(--sa-muted);
            margin: 0;
        }

        .sa-btn {
            background: var(--sa-primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
        }

        .sa-btn:hover {
            background: #0a2f52;
        }

        .sa-btn-outline {
            background: white;
            color: var(--sa-primary);
            border: 1px solid var(--sa-border);
        }

        .sa-btn-outline:hover {
            background: var(--sa-bg);
        }

        .sa-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .sa-metric {
            text-align: center;
        }

        .sa-metric-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--sa-primary);
            margin: 0;
        }

        .sa-metric-label {
            font-size: 0.875rem;
            color: var(--sa-muted);
            margin: 5px 0 0 0;
        }

        .sa-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .sa-form-group {
            display: flex;
            flex-direction: column;
        }

        .sa-form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 5px;
        }

        .sa-form-group input,
        .sa-form-group select {
            padding: 8px 12px;
            border: 1px solid var(--sa-border);
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .sa-form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .reports-section {
            margin-top: 20px;
        }

        .report-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="main-logo">
                    <rect width="32" height="32" rx="8" fill="#0d3b66"/>
                    <text x="16" y="22" font-size="20" font-weight="bold" fill="white" text-anchor="middle">O</text>
                </svg>
            </div>
            <nav class="menu">
                <a href="superadmin_dash.php" class="menu-item"><span>🛡️</span> Dashboard</a>
                <a href="superadmin_dash.php#tenant-section" class="menu-item"><span>🏥</span> Tenant List</a>
                <a href="superadmin_dash.php#register-section" class="menu-item"><span>➕</span> Register Clinic</a>
                <a href="superadmin_reports.php" class="menu-item active"><span>📊</span> Reports</a>
                <a href="superadmin_audit_logs.php" class="menu-item"><span>📋</span> Audit Logs</a>
                <a href="superadmin_settings.php" class="menu-item"><span>⚙️</span> Settings</a>
            </nav>
        </div>
        <div class="sidebar-bottom">
            <a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="sa-main-header">
            <div>
                <h1>Reports</h1>
                <span>Generate and view system reports</span>
            </div>
            <div class="sa-profile">
                <span>Welcome, <strong>Super Admin</strong></span>
                <div class="sa-profile-avatar">🛡️</div>
            </div>
        </header>

        <!-- Mini Dashboard -->
        <div class="sa-card">
            <div class="sa-card-header">
                <div>
                    <div class="sa-card-title">System Summary</div>
                    <div class="sa-card-subtitle">Key metrics and statistics</div>
                </div>
            </div>
            <div class="sa-grid">
                <?php
                try {
                    // Fetch tenant activities summary
                    $stmt = $pdo->query("SELECT COUNT(*) as total_activities FROM tenant_activity_logs WHERE DATE(log_date) = CURDATE()");
                    $today_activities = $stmt->fetch()['total_activities'];

                    // User registration reports - new tenants this month
                    $stmt = $pdo->query("SELECT COUNT(*) as new_tenants FROM tenants WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                    $new_tenants = $stmt->fetch()['new_tenants'];

                    // Usage statistics - total active tenants
                    $stmt = $pdo->query("SELECT COUNT(*) as active_tenants FROM tenants WHERE status = 'active'");
                    $active_tenants = $stmt->fetch()['active_tenants'];

                    // Total tenants
                    $stmt = $pdo->query("SELECT COUNT(*) as total_tenants FROM tenants");
                    $total_tenants = $stmt->fetch()['total_tenants'];
                } catch (Exception $e) {
                    $today_activities = $new_tenants = $active_tenants = $total_tenants = 0;
                }
                ?>
                <div class="sa-metric">
                    <div class="sa-metric-value"><?php echo $today_activities; ?></div>
                    <div class="sa-metric-label">Today's Activities</div>
                </div>
                <div class="sa-metric">
                    <div class="sa-metric-value"><?php echo $new_tenants; ?></div>
                    <div class="sa-metric-label">New Registrations (This Month)</div>
                </div>
                <div class="sa-metric">
                    <div class="sa-metric-value"><?php echo $active_tenants; ?>/<?php echo $total_tenants; ?></div>
                    <div class="sa-metric-label">Active Tenants</div>
                </div>
                <div class="sa-metric">
                    <div class="sa-metric-value"><?php echo $total_tenants; ?></div>
                    <div class="sa-metric-label">Total Tenants</div>
                </div>
            </div>
        </div>

        <!-- Report Generation Section -->
        <div class="sa-card reports-section">
            <div class="sa-card-header">
                <div>
                    <div class="sa-card-title">Generate Reports</div>
                    <div class="sa-card-subtitle">Create detailed reports with filters</div>
                </div>
            </div>
            <div class="report-buttons">
                <button class="sa-btn" onclick="generateReport('tenant_activity')">Tenant Activity Report</button>
                <button class="sa-btn" onclick="generateReport('user_registration')">User Registration Report</button>
                <button class="sa-btn" onclick="generateReport('usage_statistics')">Usage Statistics Report</button>
            </div>

            <!-- Filters -->
            <div class="sa-form-grid">
                <div class="sa-form-group">
                    <label for="date_from">Date From</label>
                    <input type="date" id="date_from">
                </div>
                <div class="sa-form-group">
                    <label for="date_to">Date To</label>
                    <input type="date" id="date_to">
                </div>
                <div class="sa-form-group">
                    <label for="tenant_filter">Tenant</label>
                    <select id="tenant_filter">
                        <option value="">All Tenants</option>
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT tenant_id, company_name FROM tenants ORDER BY company_name");
                            while ($tenant = $stmt->fetch()) {
                                echo "<option value='{$tenant['tenant_id']}'>{$tenant['company_name']}</option>";
                            }
                        } catch (Exception $e) {
                            // If database fails, just show "All Tenants" option
                        }
                        ?>
                    </select>
                </div>
                <div class="sa-form-group">
                    <label for="activity_type">Activity Type</label>
                    <select id="activity_type">
                        <option value="">All Types</option>
                        <option value="Patient Created">Patient Created</option>
                        <option value="Appointment Scheduled">Appointment Scheduled</option>
                        <option value="Payment Received">Payment Received</option>
                        <option value="Staff Added">Staff Added</option>
                        <option value="Clinical Notes">Clinical Notes</option>
                    </select>
                </div>
            </div>
            <div class="sa-form-actions">
                <button class="sa-btn" onclick="applyFilters()">Apply Filters</button>
                <button class="sa-btn sa-btn-outline" onclick="exportPDF()">Export PDF</button>
                <button class="sa-btn sa-btn-outline" onclick="exportCSV()">Export CSV</button>
            </div>

            <!-- Report Display Area -->
            <div id="report-results" style="margin-top: 20px;">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        let currentReportData = [];

        function generateReport(type) {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const tenantId = document.getElementById('tenant_filter').value;
            const activityType = document.getElementById('activity_type').value;

            fetch(`get_filtered_reports.php?type=${type}&date_from=${dateFrom}&date_to=${dateTo}&tenant_id=${tenantId}&activity_type=${activityType}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentReportData = data.data;
                        displayReportResults(data.data, type);
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to generate report');
                });
        }

        function displayReportResults(data, type) {
            const resultsDiv = document.getElementById('report-results');
            if (data.length === 0) {
                resultsDiv.innerHTML = '<p>No data found for the selected filters.</p>';
                return;
            }

            let html = '<table class="logs-table"><thead><tr>';
            // Headers
            Object.keys(data[0]).forEach(header => {
                html += `<th>${header}</th>`;
            });
            html += '</tr></thead><tbody>';

            // Rows
            data.forEach(row => {
                html += '<tr>';
                Object.values(row).forEach(cell => {
                    html += `<td>${cell}</td>`;
                });
                html += '</tr>';
            });
            html += '</tbody></table>';

            resultsDiv.innerHTML = html;
        }

        function applyFilters() {
            // Re-run the current report with new filters
            const activeButton = document.querySelector('.report-buttons button:focus');
            if (activeButton) {
                const type = activeButton.onclick.toString().match(/generateReport\('(\w+)'\)/)[1];
                generateReport(type);
            } else {
                alert('Please select a report type first');
            }
        }

        function exportPDF() {
            if (currentReportData.length === 0) {
                alert('Please generate a report first');
                return;
            }

            // Send data to PDF generator
            fetch('generate_pdf.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    data: currentReportData,
                    title: 'OralSync System Report'
                })
            })
            .then(response => {
                if (response.ok) {
                    return response.blob();
                } else {
                    throw new Error('PDF generation failed');
                }
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'oralsync_report.pdf';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to export PDF');
            });
        }

        function exportCSV() {
            if (currentReportData.length === 0) {
                alert('Please generate a report first');
                return;
            }

            let csv = '';
            // Headers
            csv += Object.keys(currentReportData[0]).join(',') + '\n';
            // Rows
            currentReportData.forEach(row => {
                csv += Object.values(row).map(value => `"${value}"`).join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'oralsync_report.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }
    </script>
    </main>
</body>
</html>