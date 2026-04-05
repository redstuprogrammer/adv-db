<?php
session_start();
require_once __DIR__ . '/includes/security_headers.php';
if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadmin_login.php');
    exit;
}
require_once __DIR__ . '/../settings.php';

// Load settings for logo display
try {
    $currentSettings = getAllSettings();
} catch (Exception $e) {
    $currentSettings = [];
}
// Removed connect.php require since data is loaded via AJAX
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Super Admin Audit Logs</title>
    <link rel="stylesheet" href="/style1.css">
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

        .sa-form-group input {
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

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .logs-table th,
        .logs-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--sa-border);
        }

        .logs-table th {
            background: var(--sa-bg);
            font-weight: 600;
            color: #374151;
        }

        .logs-table tr:hover {
            background: #f8fafc;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination button {
            padding: 8px 12px;
            border: 1px solid var(--sa-border);
            background: white;
            cursor: pointer;
            border-radius: 4px;
        }

        .pagination button:disabled {
            background: var(--sa-bg);
            color: var(--sa-muted);
            cursor: not-allowed;
        }

        .pagination button.active {
            background: var(--sa-primary);
            color: white;
        }
    </style>
</head>
<body>

<div class="container">
    <?php include __DIR__ . '/includes/sidebar_superadmin.php'; ?>

    <main class="main-content">
        <header class="sa-main-header">
            <div>
                <h1>Audit Logs</h1>
                <span>View system activity records</span>
            </div>
            <div class="sa-profile">
                <span>Welcome, <strong>Super Admin</strong></span>
                <div class="sa-profile-avatar">🛡️</div>
            </div>
        </header>

        <div class="sa-card">
            <div class="sa-card-header">
                <div>
                    <div class="sa-card-title">System Audit Logs</div>
                    <div class="sa-card-subtitle">Records of all system activities</div>
                </div>
                <button class="sa-btn sa-btn-outline" onclick="exportCSV()">Export CSV</button>
            </div>

            <!-- Filters -->
            <div class="sa-form-grid">
                <div class="sa-form-group">
                    <label for="search_date">Search by Date</label>
                    <input type="date" id="search_date">
                </div>
                <div class="sa-form-group">
                    <label for="search_user">Search by User/Admin</label>
                    <input type="text" id="search_user" placeholder="Enter username">
                </div>
                <div class="sa-form-group">
                    <label for="search_action">Search by Action</label>
                    <input type="text" id="search_action" placeholder="Enter action type">
                </div>
            </div>
            <div class="sa-form-actions">
                <button class="sa-btn" onclick="applyFilters()">Apply Filters</button>
                <button class="sa-btn sa-btn-outline" onclick="clearFilters()">Clear Filters</button>
            </div>

            <!-- Logs Table -->
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User/Admin</th>
                        <th>Action Type</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody id="logs-tbody">
                    <!-- Data will be loaded via AJAX -->
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination" id="pagination-controls">
                <!-- Pagination controls will be generated via JavaScript -->
            </div>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let totalPages = 1;
        const limit = 20;

        // Load initial data
        document.addEventListener('DOMContentLoaded', function() {
            loadLogs();
        });

        function loadLogs(page = 1) {
            currentPage = page;
            const date = document.getElementById('search_date').value;
            const user = document.getElementById('search_user').value;
            const action = document.getElementById('search_action').value;

            const params = new URLSearchParams({
                page: page,
                limit: limit,
                date: date,
                user: user,
                action: action
            });

            fetch(`get_audit_logs.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayLogs(data.data);
                        updatePagination(data.pagination);
                    } else {
                        console.error('Error:', data.error);
                        document.getElementById('logs-tbody').innerHTML = '<tr><td colspan="4">Error loading logs</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('logs-tbody').innerHTML = '<tr><td colspan="4">Failed to load logs</td></tr>';
                });
        }

        function displayLogs(logs) {
            const tbody = document.getElementById('logs-tbody');
            if (logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4">No logs found</td></tr>';
                return;
            }

            tbody.innerHTML = logs.map(log => `
                <tr>
                    <td>${log.timestamp}</td>
                    <td>${log.user}</td>
                    <td>${log.action_type}</td>
                    <td>${log.details}</td>
                </tr>
            `).join('');
        }

        function updatePagination(pagination) {
            totalPages = pagination.total_pages;
            const paginationControls = document.getElementById('pagination-controls');
            
            let html = '';
            
            // Previous button
            html += `<button onclick="changePage(-1)" ${currentPage <= 1 ? 'disabled' : ''}>Previous</button>`;
            
            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<button onclick="loadLogs(${i})" class="${i === currentPage ? 'active' : ''}">${i}</button>`;
            }
            
            // Next button
            html += `<button onclick="changePage(1)" ${currentPage >= totalPages ? 'disabled' : ''}>Next</button>`;
            
            paginationControls.innerHTML = html;
        }

        function applyFilters() {
            loadLogs(1); // Reset to first page when applying filters
        }

        function clearFilters() {
            document.getElementById('search_date').value = '';
            document.getElementById('search_user').value = '';
            document.getElementById('search_action').value = '';
            loadLogs(1);
        }

        function exportCSV() {
            const date = document.getElementById('search_date').value;
            const user = document.getElementById('search_user').value;
            const action = document.getElementById('search_action').value;

            // For CSV export, we'll get all filtered records (or first 1000 for performance)
            const params = new URLSearchParams({
                page: 1,
                limit: 1000, // Export up to 1000 records
                date: date,
                user: user,
                action: action
            });

            fetch(`get_audit_logs.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        let csv = 'Timestamp,User/Admin,Action Type,Details\n';
                        data.data.forEach(log => {
                            csv += `"${log.timestamp}","${log.user}","${log.action_type}","${log.details.replace(/"/g, '""')}"\n`;
                        });

                        const blob = new Blob([csv], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'oralsync_audit_logs.csv';
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                    } else {
                        alert('No data to export');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to export CSV');
                });
        }

        function changePage(direction) {
            const newPage = currentPage + direction;
            if (newPage >= 1 && newPage <= totalPages) {
                loadLogs(newPage);
            }
        }

        // Dropdown toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.querySelector('.menu-dropdown-toggle');
            const dropdownItems = document.querySelector('.menu-dropdown-items');
            const dropdown = document.querySelector('.menu-dropdown');

            if (dropdownToggle) {
                dropdownToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (dropdownItems.style.display === 'none' || dropdownItems.style.display === '') {
                        dropdownItems.style.display = 'flex';
                        dropdownToggle.classList.add('active');
                    } else {
                        dropdownItems.style.display = 'none';
                        dropdownToggle.classList.remove('active');
                    }
                });
            }

            // Prevent dropdown from closing when clicking dropdown items
            if (dropdownItems) {
                dropdownItems.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (dropdown && !dropdown.contains(e.target)) {
                    if (dropdownItems) dropdownItems.style.display = 'none';
                    if (dropdownToggle) dropdownToggle.classList.remove('active');
                }
            });
            
            // Expand dropdown if on a reports page
            const currentPage = window.location.pathname;
            if ((currentPage.includes('superadmin_reports') || currentPage.includes('superadmin_sales_report')) && dropdownToggle && dropdownItems) {
                dropdownItems.style.display = 'flex';
                dropdownToggle.classList.add('active');
            }
        });
    </script>
    </main>
</body>
</html>
