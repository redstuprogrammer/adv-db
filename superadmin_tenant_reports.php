<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadmin_login.php');
    exit;
}
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/tenant_utils.php';
require_once __DIR__ . '/settings.php';

// Load settings for logo display
try {
    $currentSettings = getAllSettings();
} catch (Exception $e) {
    $currentSettings = [];
}
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
            background: #f8fafc;
            padding: 24px;
            border-radius: 8px;
            border: 1px solid var(--sa-border);
        }

        .sa-metric-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--sa-primary);
            margin: 0 0 12px 0;
        }

        .sa-metric-label {
            font-size: 0.875rem;
            color: var(--sa-muted);
            margin: 0;
            line-height: 1.5;
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

        /* Tab styles */
        .sa-tabs {
            display: flex;
            border-bottom: 1px solid var(--sa-border);
            margin-bottom: 20px;
        }

        .sa-tab {
            padding: 12px 20px;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--sa-muted);
            transition: all 0.2s;
        }

        .sa-tab.active {
            color: var(--sa-primary);
            border-bottom-color: var(--sa-primary);
        }

        .sa-tab:hover {
            color: var(--sa-primary);
        }

        .sa-tab-content {
            display: none;
        }

        .sa-tab-content.active {
            display: block;
        }

        .sa-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .sa-table th {
            text-align: left;
            padding: 12px 16px;
            border-bottom: 1px solid var(--sa-border);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--sa-muted);
            background: #f9fafb;
        }

        .sa-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .sa-table tbody tr:hover {
            background: #f8fafc;
        }

        .sa-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .sa-pill-active {
            background: #dcfce7;
            color: #166534;
        }

        .sa-pill-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            margin-top: 16px;
        }

        .logs-table thead {
            background: #f9fafb;
        }

        .logs-table th {
            text-align: left;
            padding: 12px 16px;
            border-bottom: 1px solid var(--sa-border);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--sa-muted);
            font-weight: 600;
        }

        .logs-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .logs-table tbody tr:hover {
            background: #f8fafc;
        }

        /* Dropdown Menu Styles */
        .menu-dropdown {
            position: relative;
            width: 100%;
        }

        .menu-dropdown-toggle {
            width: 100%;
            background: none;
            border: none;
            color: #ffffff;
            padding: 12px 16px;
            text-align: left;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            transition: background-color 0.2s;
            margin: 0;
            font-family: inherit;
        }

        .menu-dropdown-toggle:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .menu-dropdown-toggle::after {
            content: '▸';
            margin-left: auto;
            transition: transform 0.2s ease;
        }

        .menu-dropdown-toggle.active::after {
            transform: rotate(90deg);
        }

        .menu-dropdown-items {
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 3px solid #22c55e;
            overflow: hidden;
        }

        .menu-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            color: #ffffff;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background-color 0.15s;
        }

        .menu-dropdown-item:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }
    </style>
</head>
<body>

<div class="container">
    <?php include __DIR__ . '/includes/sidebar_superadmin.php'; ?>

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

        <!-- Report Generation Section -->

        <!-- Report Generation Section -->
        <div class="sa-card reports-section">
            <div class="sa-card-header">
                <div>
                    <div class="sa-card-title">Generate Reports</div>
                    <div class="sa-card-subtitle">Create detailed reports with filters</div>
                </div>
            </div>
            <div class="report-buttons">
                <button id="report-tenant-activity" class="sa-btn" onclick="selectReportType('tenant_activity')">Tenant Activity Report</button>
                <button id="report-user-registration" class="sa-btn" onclick="selectReportType('user_registration')">User Registration Report</button>
                <button id="report-usage-statistics" class="sa-btn" onclick="selectReportType('usage_statistics')">Usage Statistics Report</button>
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

            <!-- Report Display Area -->
            <div id="report-results" style="margin-top: 20px;">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Close dropdown when clicking outside or on external links
        document.addEventListener('click', function(e) {
            const dropdownToggle = document.querySelector('.menu-dropdown-toggle');
            const dropdownItems = document.querySelector('.menu-dropdown-items');
            const dropdown = document.querySelector('.menu-dropdown');
            
            if (dropdown && !dropdown.contains(e.target)) {
                dropdownItems.style.display = 'none';
                if (dropdownToggle) dropdownToggle.classList.remove('active');
            }
        });

        // Close dropdown when clicking on external links
        document.querySelectorAll('a:not([data-section])').forEach(link => {
            if (link.hasAttribute('href') && !link.classList.contains('menu-dropdown-item')) {
                link.addEventListener('click', function() {
                    const dropdownItems = document.querySelector('.menu-dropdown-items');
                    const dropdownToggle = document.querySelector('.menu-dropdown-toggle');
                    if (dropdownItems) dropdownItems.style.display = 'none';
                    if (dropdownToggle) dropdownToggle.classList.remove('active');
                });
            }
        });

        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.sa-tab');
            const tabContents = document.querySelectorAll('.sa-tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    // Add active class to clicked tab
                    this.classList.add('active');

                    // Hide all tab contents
                    tabContents.forEach(content => content.classList.remove('active'));

                    // Show the corresponding tab content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });

            const dateFromEl = document.getElementById('date_from');
            const dateToEl = document.getElementById('date_to');

            if (dateFromEl && dateToEl) {
                dateFromEl.addEventListener('change', function() {
                    dateToEl.min = this.value || '';
                    if (dateToEl.value && this.value && dateToEl.value < this.value) {
                        dateToEl.value = this.value;
                    }
                });

                dateToEl.addEventListener('change', function() {
                    if (dateFromEl.value && this.value && this.value < dateFromEl.value) {
                        alert('Date To cannot be earlier than Date From.');
                        this.value = dateFromEl.value;
                    }
                });
            }

            const reportsDropdownToggle = document.querySelector('.menu-dropdown-toggle');
            const reportsDropdownItems = document.querySelector('.menu-dropdown-items');
            if (reportsDropdownToggle && reportsDropdownItems) {
                reportsDropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const open = reportsDropdownItems.style.display === 'flex';
                    reportsDropdownItems.style.display = open ? 'none' : 'flex';
                    reportsDropdownToggle.classList.toggle('active');
                });
                
                // Expand dropdown since we're on a reports page
                reportsDropdownItems.style.display = 'flex';
                reportsDropdownToggle.classList.add('active');
            }

            const tenantFilter = document.getElementById('tenant_filter');
            const activityTypeEl = document.getElementById('activity_type');
            [dateFromEl, dateToEl, tenantFilter, activityTypeEl].forEach(el => {
                if (!el) return;
                el.addEventListener('change', function() {
                    generateReport(selectedReportType);
                });
            });

            generateReport(selectedReportType);
        });

        let currentReportData = [];
        let selectedReportType = 'tenant_activity';

        function isValidDateRange() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            if (dateFrom && dateTo && dateTo < dateFrom) {
                alert('Date To cannot be earlier than Date From.');
                return false;
            }
            return true;
        }

        function selectReportType(type) {
            selectedReportType = type;
            document.querySelectorAll('.report-buttons .sa-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.report-buttons .sa-btn').forEach(btn => btn.style.opacity = '0.9');
            const idMap = {
                'tenant_activity': 'report-tenant-activity',
                'user_registration': 'report-user-registration',
                'usage_statistics': 'report-usage-statistics'
            };
            const activeBtn = document.getElementById(idMap[type]);
            if (activeBtn) {
                activeBtn.classList.add('active');
                activeBtn.style.opacity = '1';
            }
            generateReport(type);
        }

        function generateReport(type) {
            if (!isValidDateRange()) {
                return;
            }

            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const tenantId = document.getElementById('tenant_filter').value;
            const activityType = document.getElementById('activity_type').value;

            fetch(`/get_filtered_reports.php?type=${type}&date_from=${dateFrom}&date_to=${dateTo}&tenant_id=${tenantId}&activity_type=${activityType}`)
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

        function getReportTitle(type) {
            const titles = {
                'tenant_activity': 'Tenant Activity Report',
                'user_registration': 'User Registration Report',
                'usage_statistics': 'Usage Statistics Report',
                'revenue': 'Sales Revenue Report'
            };
            return titles[type] || 'OralSync System Report';
        }

        function exportPDF() {
            if (currentReportData.length === 0) {
                alert('Please generate a report first');
                return;
            }

            // Determine PDF type based on report type
            let pdfType = 'standard';
            if (selectedReportType === 'revenue') {
                pdfType = 'sales';
            }

            // Send data to PDF generator
            fetch('generate_pdf.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    data: currentReportData,
                    title: getReportTitle(selectedReportType),
                    type: pdfType
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
            if (!selectedReportType) {
                alert('Please select a report type first');
                return;
            }

            // Build query parameters
            const params = new URLSearchParams({
                type: selectedReportType,
                date_from: document.getElementById('date-from').value,
                date_to: document.getElementById('date-to').value,
                tenant_id: document.getElementById('tenant-filter').value,
                activity_type: document.getElementById('activity-type-filter').value
            });

            // Redirect to server-side CSV generation
            window.location.href = 'generate_csv.php?' + params.toString();
        }

        function exportTable(tableBodyId, format) {
            const tbody = document.getElementById(tableBodyId);
            if (!tbody) {
                alert('Table not found');
                return;
            }

            const rows = tbody.querySelectorAll('tr');
            if (rows.length === 0) {
                alert('No data to export');
                return;
            }

            let data = [];
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    const rowData = Array.from(cells).map(cell => cell.textContent.trim());
                    data.push(rowData);
                }
            });

            if (format === 'csv') {
                let csv = '';
                data.forEach(row => {
                    // Properly escape CSV values
                    const escapedRow = row.map(cell => {
                        // Convert to string and handle special characters
                        let value = String(cell).replace(/"/g, '""'); // Escape quotes
                        // Wrap in quotes if contains comma, quote, or newline
                        if (value.includes(',') || value.includes('"') || value.includes('\n')) {
                            value = '"' + value + '"';
                        }
                        return value;
                    });
                    csv += escapedRow.join(',') + '\n';
                });

                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'oralsync_table_export.csv';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else if (format === 'pdf') {
                // For PDF, we'll use a simple approach - send to server
                fetch('generate_pdf.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        tableData: data,
                        title: 'OralSync Table Export'
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
                    a.download = 'oralsync_table_export.pdf';
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
        }
    </script>
    </main>
</body>
</html>
