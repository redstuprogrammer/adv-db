<?php
session_start();
require_once __DIR__ . '/security_headers.php';
if (empty($_SESSION['superadmin_authed'])) {
    header('Location: superadmin_login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OralSync | Super Admin</title>
    <link rel="stylesheet" href="style1.css">
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
            font-size: 0.9rem;
            color: var(--sa-muted);
        }

        .sa-profile-avatar {
            width: 35px;
            height: 35px;
            border-radius: 999px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sa-section {
            display: none;
        }

        .sa-section.active-section {
            display: block;
        }

        .sa-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--sa-border);
            padding: 24px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
        }

        .sa-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .sa-card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--sa-primary);
        }

        .sa-card-subtitle {
            font-size: 0.8rem;
            color: var(--sa-muted);
            margin-top: 4px;
        }

        .sa-empty-state {
            padding: 40px 0;
            text-align: center;
            color: var(--sa-muted);
            font-size: 0.9rem;
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

        .sa-tenant-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .sa-search-wrapper {
            position: relative;
            min-width: 260px;
            max-width: 360px;
            flex: 1;
        }

        .sa-search-wrapper input {
            width: 100%;
            padding: 10px 36px 10px 32px;
            border-radius: 999px;
            border: 1px solid var(--sa-border);
            font-size: 0.85rem;
            outline: none;
        }

        .sa-search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.9rem;
            color: var(--sa-muted);
        }

        .sa-filter-select {
            padding: 9px 12px;
            border-radius: 999px;
            border: 1px solid var(--sa-border);
            font-size: 0.8rem;
            background: #ffffff;
        }

        .sa-tenant-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .sa-tenant-table th {
            text-align: left;
            padding: 10px 14px;
            border-bottom: 1px solid var(--sa-border);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--sa-muted);
            background: #f9fafb;
        }

        .sa-tenant-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .sa-tenant-name {
            font-weight: 600;
            color: #0f172a;
        }

        .sa-tenant-meta {
            display: block;
            font-size: 0.75rem;
            color: var(--sa-muted);
        }

        .sa-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 7px 14px;
        }

        .sa-btn-outline {
            background: #ffffff;
            border: 2px solid var(--sa-border);
            border-color:rgb(178, 181, 190);
            color: #0f172a;
        }

        .sa-btn-danger {
            background: #fee2e2;
            color: #b91c1c;
            border: 2px solid #f87171;
        }

        .sa-btn-success {
            background: #dcfce7;
            color: #166534;
            border: 2px solid #22c55e;
        }

        .sa-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #0f172a;
            color: #f9fafb;
            padding: 12px 18px;
            border-radius: 999px;
            font-size: 0.8rem;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.25);
            opacity: 0;
            transform: translateY(-10px);
            pointer-events: none;
            transition: all 0.2s ease;
            z-index: 50;
        }

        .sa-toast.show {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .sa-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px 20px;
            margin-top: 16px;
        }

        .sa-form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .sa-form-group input,
        .sa-form-group select,
        .sa-form-group textarea {
            width: 100%;
            padding: 9px 11px;
            border-radius: 10px;
            border: 1px solid var(--sa-border);
            border-color:rgb(178, 181, 190);
            font-size: 0.85rem;
            outline: none;
        }

        .sa-form-group textarea {
            resize: vertical;
            min-height: 70px;
        }

        .sa-form-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .sa-badge-required {
            color: #b91c1c;
        }

        .sa-success-panel {
            margin-top: 20px;
            padding: 16px 18px;
            border-radius: 12px;
            border: 1px solid #bbf7d0;
            background: #ecfdf3;
            font-size: 0.85rem;
            display: none;
        }

        .sa-success-panel strong {
            color: #166534;
        }

        .sa-success-actions {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .sa-link-sample {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.8rem;
            color: #0f172a;
            background: #f9fafb;
            padding: 6px 8px;
            border-radius: 8px;
            display: inline-block;
            margin-top: 4px;
        }

        .sa-note {
            font-size: 0.75rem;
            color: var(--sa-muted);
            margin-top: 4px;
        }
        .sa-tenant-link {
            text-decoration: none;
            color: inherit; /* Keeps the text color from your dashboard theme */
            transition: color 0.2s ease;
        }

        .sa-tenant-link:hover .sa-tenant-name {
            color: #007bff; /* Or your preferred brand primary color */
            text-decoration: underline;
        }

        .sa-tenant-info {
            display: flex;
            flex-direction: column;
                    }
                    .clickable-row {
                cursor: pointer;
                transition: background 0.2s ease;
            }

            .clickable-row:hover {
                background-color: #f1f5f9 !important; /* Matches your UI's slate theme */
            }
            .sa-modal-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.7);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000;
}

.modal-content {
    width: 90%; max-width: 600px;
    background: white;
    border-radius: 12px;
    overflow: hidden;
}

.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    padding: 20px;
}

.close-modal {
    background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;
}
#details-modal {
    display: none; /* JS toggles this to 'flex' */
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.7);
    z-index: 999;
    align-items: center;
    justify-content: center;
}.clickable-row { cursor: pointer; transition: background 0.2s; }
.clickable-row:hover { background-color: #f8fafc; }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="logo-white-box">
                <img src="oral logo.png" alt="OralSync" class="main-logo">
            </div>
            <nav class="menu">
                <a href="#" class="menu-item active" data-section="dashboard-section"><span>🛡️</span> Dashboard</a>
                <a href="#" class="menu-item" data-section="tenant-section"><span>🏥</span> Tenant List</a>
                <a href="#" class="menu-item" data-section="register-section"><span>➕</span> Register Clinic</a>
            </nav>
        </div>
        <div class="sidebar-bottom">
            <a href="logout.php" class="sign-out"><span>🚪</span> Sign Out</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="sa-main-header">
            <div>
                <h1>Super Admin Control</h1>
                <span>Manage clinics and onboard new tenants for OralSync.</span>
            </div>
            <div class="sa-profile">
                <span>Welcome, <strong>Super Admin</strong></span>
                <div class="sa-profile-avatar">🛡️</div>
            </div>
        </header>

        <!-- Dashboard (empty for now) -->
        <section id="dashboard-section" class="sa-section active-section">
            <div class="sa-card">
                <div class="sa-card-header">
                    <div>
                        <div class="sa-card-title">Overview</div>
                        <div class="sa-card-subtitle">This space is reserved for future multi-tenant insights.</div>
                    </div>
                </div>
                <div class="sa-empty-state">
                    Dashboard widgets for tenant metrics will appear here in the next phase.
                </div>
            </div>
        </section>

        <!-- Tenant List -->
        <section id="tenant-section" class="sa-section">
            <div class="sa-card">
                <div class="sa-card-header">
                    <div>
                        <div class="sa-card-title">Tenant Clinics</div>
                        <div class="sa-card-subtitle">Monitor all registered clinics and their current status.</div>
                    </div>
                </div>

                <div class="sa-tenant-toolbar">
                    <div class="sa-search-wrapper">
                        <span class="sa-search-icon">🔍</span>
                        <input type="text" id="clinic-search" placeholder="Search by clinic name, owner, or email...">
                    </div>
                    <div>
                        <select id="status-filter" class="sa-filter-select">
                            <option value="all">All statuses</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div style="overflow-x:auto;">
                 <table class="sa-tenant-table" id="tenant-table">
                <thead>
                    </thead>
                    <tbody id="tenant-table-body"></tbody>
                </table>
                </div>
            </div>
        </section>

        <!-- Register Clinic -->
        <section id="register-section" class="sa-section">
            <div class="sa-card">
                <div class="sa-card-header">
                    <div>
                        <div class="sa-card-title">Register New Clinic</div>
                        <div class="sa-card-subtitle">Capture clinic and owner details. Email delivery is simulated for now.</div>
                    </div>
                </div>

                <form id="register-form">
                    <div class="sa-form-grid">
                        <div class="sa-form-group">
                            <label for="clinic-name">Clinic Name <span class="sa-badge-required">*</span></label>
                            <input type="text" id="clinic-name" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="owner-name">Owner Name <span class="sa-badge-required">*</span></label>
                            <input type="text" id="owner-name" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="owner-email">Clinic / Owner Email <span class="sa-badge-required">*</span></label>
                            <input type="email" id="owner-email" required>
                            <div class="sa-note">For this demo, use a Gmail address so the onboarding email can be delivered.</div>
                        </div>
                        <div class="sa-form-group">
                            <label for="clinic-phone">Clinic Phone Number <span class="sa-badge-required">*</span></label>
                            <input type="tel" id="clinic-phone" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="clinic-address">Clinic Address <span class="sa-badge-required">*</span></label>
                            <input type="text" id="clinic-address" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="clinic-city">City / Municipality <span class="sa-badge-required">*</span></label>
                            <input type="text" id="clinic-city" required>
                        </div>
                        <div class="sa-form-group">
                            <label for="clinic-province">Province / Area (Luzon only) <span class="sa-badge-required">*</span></label>
                            <select id="clinic-province" required>
                                <option value="">Select province</option>
                                <option>Metro Manila</option>
                                <option>Bulacan</option>
                                <option>Pampanga</option>
                                <option>Tarlac</option>
                                <option>Bataan</option>
                                <option>Nueva Ecija</option>
                                <option>Zambales</option>
                                <option>Cavite</option>
                                <option>Laguna</option>
                                <option>Batangas</option>
                                <option>Rizal</option>
                                <option>Quezon</option>
                                <option>Benguet</option>
                                <option>Ilocos Norte</option>
                                <option>Ilocos Sur</option>
                                <option>La Union</option>
                                <option>Pangasinan</option>
                                <option>Cagayan</option>
                                <option>Isabela</option>
                                <option>Abra</option>
                            </select>
                        </div>
                        <div class="sa-form-group" style="grid-column: 1 / -1;">
                            <label for="clinic-notes">Notes / Special Instructions</label>
                            <textarea id="clinic-notes" placeholder="Optional notes about billing, onboarding preferences, or setup requirements."></textarea>
                            <div class="sa-note">You can later extend this to include billing preferences, add-ons, or EHR integrations.</div>
                        </div>
                    </div>

                    <div class="sa-form-actions">
                        <button type="reset" class="sa-btn sa-btn-outline">Clear</button>
                        <button type="submit" class="sa-btn sa-btn-success">Register Clinic</button>
                    </div>
                </form>

                <div id="registration-success" class="sa-success-panel" style="display:none;">
    <div style="display: flex; align-items: center; gap: 8px; color: #10b981; font-weight: 600;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        Clinic Registered Successfully!
    </div>
    <div class="tenant-row clickable-row" data-href="view_tenant.php?id=<?php echo $row['tenant_id']; ?>">
    <div class="col"><strong><?php echo $row['company_name']; ?></strong></div>
    <div class="col"><?php echo $row['owner_name']; ?></div>
    <div class="col">
        <button class="btn-deactivate" onclick="event.stopPropagation(); deactivateTenant(<?php echo $row['tenant_id']; ?>);">
            Deactivate
        </button>
    </div>
</div>
    <div id="success-message-body" style="margin-top: 12px; font-size: 0.9rem; color: var(--sa-text-muted);">
        The clinic has been added to the database. Provide the credentials below to the owner.
    </div>

    <div class="sa-credential-box">
        <div class="sa-credential-item">
            <span class="sa-label">Temporary Password:</span>
            <div style="display: flex; align-items: center; gap: 10px;">
                <code id="display-temp-password" class="sa-temp-pass">Generating...</code>
                <button type="button" class="sa-copy-btn" onclick="copyPassword()">Copy</button>
            </div>
        </div>
        <div class="sa-credential-item">
            <span class="sa-label">Login URL:</span>
            <div id="sample-login-link" class="sa-link-sample"></div>
        </div>
    </div>

    <div class="sa-success-actions">
        <button id="btn-resend-email" class="sa-btn sa-btn-outline">Resend Email</button>
        <button id="btn-go-tenants" class="sa-btn sa-btn-success">Go to Tenant List</button>
    </div>
    <div id="resend-note" class="sa-note" style="display:none;">A resend has been simulated for this clinic's login email.</div>
</div>
            </div>
        </section>
    </main>
</div>
<div id="sa-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); z-index:100; align-items:center; justify-content:center;">
    <div class="sa-card" style="width: 100%; max-width: 500px; animation: modalSlide 0.3s ease;">
        <div class="sa-card-header">
            <div class="sa-card-title">Confirm Registration</div>
        </div>
        <p class="sa-note" style="margin-bottom: 20px;">Please verify these details with the tenant over the phone before proceeding.</p>
        
        <div id="modal-review-content" style="background: var(--sa-bg); padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem;">
            </div>

        <div class="sa-form-actions">
            <button id="modal-cancel" class="sa-btn sa-btn-outline">Edit Details</button>
            <button id="modal-confirm" class="sa-btn sa-btn-success">Finalize & Save</button>
        </div>
    </div>
</div>
<div id="details-modal" class="sa-modal-overlay" style="display:none;">
    <div class="sa-card modal-content">
        <div class="sa-card-header">
            <div class="sa-card-title" id="modal-clinic-name">Clinic Details</div>
            <button class="close-modal" onclick="closeDetailsModal()">×</button>
        </div>
        
        <div class="modal-body">
            <div class="details-grid">
                <div class="detail-item"><strong>Owner:</strong> <span id="dt-owner"></span></div>
                <div class="detail-item"><strong>Email:</strong> <span id="dt-email"></span></div>
                <div class="detail-item"><strong>Phone:</strong> <span id="dt-phone"></span></div>
                <div class="detail-item"><strong>Status:</strong> <span id="dt-status"></span></div>
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <strong>Address:</strong> <span id="dt-address"></span>
                </div>
            </div>
            <hr>
            <div class="sa-note">Registration Date: <span id="dt-date"></span></div>
        </div>

        <div class="sa-form-actions">
            <button class="sa-btn sa-btn-outline" onclick="closeDetailsModal()">Close</button>
            <button class="sa-btn sa-btn-success" id="btn-login-as"></button>
        </div>
    </div>
</div>
<style>
    @keyframes modalSlide {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    #sa-modal-overlay { display: none; }
    #sa-modal-overlay.active { display: flex; }
</style>
<div id="sa-toast" class="sa-toast"></div>

<script>
    // Sidebar navigation between sections
    (function () {
        const menuItems = document.querySelectorAll('.menu-item[data-section]');
        const sections = document.querySelectorAll('.sa-section');

        menuItems.forEach(item => {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                const target = this.getAttribute('data-section');

                menuItems.forEach(mi => mi.classList.remove('active'));
                this.classList.add('active');

                sections.forEach(sec => {
                    if (sec.id === target) {
                        sec.classList.add('active-section');
                    } else {
                        sec.classList.remove('active-section');
                    }
                });
            });
        });
    })();

    function showToast(message, durationMs = 4500) {
        const toast = document.getElementById('sa-toast');
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, durationMs);
    }

    /**
     * CORE FUNCTION: Fetches tenants and builds the table with 
     * required database hooks (data-id and sa-btn-toggle).
     */
    function refreshTenantList() {
    fetch('get_tenants.php')
    .then(response => response.json())
    .then(data => {
        const tbody = document.querySelector('#tenant-table tbody');
        if (!tbody) return;

        tbody.innerHTML = ''; 

        data.forEach(tenant => {
    const tr = document.createElement('tr');
    
    // 1. Add the necessary hooks for the click listener
    tr.classList.add('clickable-row'); 
    tr.setAttribute('data-href', `view_tenant.php?id=${tenant.tenant_id}`);
    
    tr.setAttribute('data-id', tenant.tenant_id); 
    tr.setAttribute('data-status', tenant.status);
    
    const createdDate = new Date(tenant.created_at).toLocaleDateString('en-PH', {
        month: 'short', day: '2-digit', year: 'numeric'
    });

    const isActive = (tenant.status || '').toLowerCase() === 'active';
    const baseUrl = window.location.origin;
    const tenantUrl = `${baseUrl}/tenant_login.php?tenant=${encodeURIComponent(tenant.subdomain_slug)}`;

    tr.innerHTML = `
        <td>
            <div class="sa-tenant-info">
                <a href="${tenantUrl}" target="_blank" class="sa-tenant-link" onclick="event.stopPropagation();">
                    <span class="sa-tenant-name">${tenant.company_name}</span>
                </a>
                <span class="sa-tenant-meta">${tenant.city}, ${tenant.province}</span>
            </div>
        </td>
        <td>${tenant.owner_name}</td>
        <td>${tenant.contact_email}</td>
        <td>${tenant.phone}</td>
        <td>
            <span class="sa-pill ${isActive ? 'sa-pill-active' : 'sa-pill-inactive'}">
                ${tenant.status}
            </span>
        </td>
        <td>${createdDate}</td>
        <td>
            <button class="sa-btn sa-btn-outline" onclick="viewTenantProfile(${tenant.tenant_id})">
                Tenant Profile
            </button>
        </td>
        <td>
            <button class="sa-btn ${isActive ? 'sa-btn-danger' : 'sa-btn-success'} sa-btn-toggle">
                ${isActive ? 'Deactivate' : 'Activate'}
            </button>
        </td>
    `;
    tbody.appendChild(tr);
});
    })
    .catch(err => console.error('Error loading tenants:', err));
}

    // Initialize list on load
    document.addEventListener('DOMContentLoaded', refreshTenantList);

    // Tenant list: search & status toggle logic
    (function () {
        const searchInput = document.getElementById('clinic-search');
        const statusFilter = document.getElementById('status-filter');
        const tbody = document.querySelector('#tenant-table tbody');

        function normalize(text) {
            return (text || '').toString().toLowerCase();
        }

        function applyFilters() {
            if (!tbody) return;
            const term = normalize(searchInput.value);
            const statusVal = statusFilter.value;

            Array.from(tbody.querySelectorAll('tr')).forEach(row => {
                const clinic = normalize(row.querySelector('.sa-tenant-name')?.textContent);
                const owner = normalize(row.cells[1]?.textContent);
                const email = normalize(row.cells[2]?.textContent);
                const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();

                const matchesTerm = !term || clinic.includes(term) || owner.includes(term) || email.includes(term);
                const matchesStatus = statusVal === 'all' || rowStatus === statusVal;

                row.style.display = (matchesTerm && matchesStatus) ? '' : 'none';
            });
        }

        if (searchInput && statusFilter && tbody) {
            searchInput.addEventListener('input', applyFilters);
            statusFilter.addEventListener('change', applyFilters);

            tbody.addEventListener('click', function (e) {
                const btn = e.target.closest('.sa-btn-toggle');
                if (!btn) return;

                const row = btn.closest('tr');
                const pill = row.querySelector('.sa-pill');
                const tenantId = row.getAttribute('data-id'); 
                const currentStatus = (row.getAttribute('data-status') || 'active').toLowerCase();
                
                const newStatus = (currentStatus === 'active') ? 'inactive' : 'active';

                const formData = new FormData();
                formData.append('tenant_id', tenantId);
                formData.append('status', newStatus);

                fetch('update_tenant_status.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        row.setAttribute('data-status', newStatus);
                        pill.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                        
                        if (newStatus === 'active') {
                            pill.classList.replace('sa-pill-inactive', 'sa-pill-active');
                            btn.textContent = 'Deactivate';
                            btn.classList.replace('sa-btn-success', 'sa-btn-danger');
                        } else {
                            pill.classList.replace('sa-pill-active', 'sa-pill-inactive');
                            btn.textContent = 'Activate';
                            btn.classList.replace('sa-btn-danger', 'sa-btn-success');
                        }
                        
                        showToast(`Clinic status updated to ${newStatus}.`);
                        applyFilters();
                    } else {
                        showToast('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    showToast('Failed to connect to the server.');
                });
            });
        }
    })();   

    // Register clinic form logic
    (function () {
        const form = document.getElementById('register-form');
        const successPanel = document.getElementById('registration-success');
        const sampleLinkEl = document.getElementById('sample-login-link');
        const resendBtn = document.getElementById('btn-resend-email');
        const goTenantsBtn = document.getElementById('btn-go-tenants');
        const resendNote = document.getElementById('resend-note');

        if (!form) return;

        function slugify(text) {
            return text.toString().toLowerCase().trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }

      (function () {
    const form = document.getElementById('register-form');
    const modalOverlay = document.getElementById('sa-modal-overlay');
    const modalReviewContent = document.getElementById('modal-review-content');
    const modalCancel = document.getElementById('modal-cancel');
    const modalConfirm = document.getElementById('modal-confirm');
    
    const successPanel = document.getElementById('registration-success');
    const sampleLinkEl = document.getElementById('sample-login-link');
    const resendNote = document.getElementById('resend-note');

    if (!form) return;

    // STEP 1: Intercept the form submission and show the modal
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Get values for review
        const reviewData = {
            'Clinic Name': document.getElementById('clinic-name').value,
            'Owner': document.getElementById('owner-name').value,
            'Email': document.getElementById('owner-email').value,
            'Phone': document.getElementById('clinic-phone').value,
            'Location': `${document.getElementById('clinic-city').value}, ${document.getElementById('clinic-province').value}`
        };

        // Build the review list inside the modal
        modalReviewContent.innerHTML = Object.entries(reviewData)
            .map(([label, value]) => `<p style="margin: 5px 0;"><strong>${label}:</strong> ${value}</p>`)
            .join('');

        // Show the modal
        modalOverlay.style.display = 'flex';
    });

    // STEP 2: Handle "Edit Details" (Cancel)
    modalCancel.addEventListener('click', () => {
        modalOverlay.style.display = 'none';
    });

    // STEP 3: Handle "Finalize & Save" (Confirm)
    modalConfirm.addEventListener('click', function () {
        // Disable button to prevent double submission
        modalConfirm.disabled = true;
        modalConfirm.textContent = 'Saving...';

        const formData = new FormData();
        formData.append('clinicName', document.getElementById('clinic-name').value.trim());
        formData.append('ownerName', document.getElementById('owner-name').value.trim());
        formData.append('email', document.getElementById('owner-email').value.trim());
        formData.append('phone', document.getElementById('clinic-phone').value.trim());
        formData.append('address', document.getElementById('clinic-address').value.trim());
        formData.append('city', document.getElementById('clinic-city').value.trim());
        formData.append('province', document.getElementById('clinic-province').value);

        fetch('register_clinic.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                refreshTenantList();

                if (sampleLinkEl) {
                    const baseUrl = window.location.origin;
                    sampleLinkEl.textContent = `${baseUrl}/tenant_login.php?tenant=${encodeURIComponent(data.slug)}`;
                }

                const passField = document.getElementById('display-temp-password');
                if (passField) {
                    passField.textContent = data.temp_password;
                }

                if (successPanel) {
                    successPanel.style.display = 'block';
                    if (resendNote) resendNote.style.display = 'none';
                }

                if (data.email_sent === false) {
                    showToast('Clinic saved, but email failed to send. Check console for error.', 6500);
                    if (data.email_error) console.warn('Email error:', data.email_error);
                } else {
                    showToast('Clinic saved! Email sent.');
                }
                form.reset();
                modalOverlay.style.display = 'none'; // Close modal on success
            } else {
                showToast('Database Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to connect to the server.');
        })
        .finally(() => {
            // Re-enable buttons for the next time
            modalConfirm.disabled = false;
            modalConfirm.textContent = 'Finalize & Save';
        });
    });
})();

        if (resendBtn && resendNote) {
            resendBtn.addEventListener('click', () => {
                resendNote.style.display = 'block';
                showToast('Resend email simulated.');
            });
        }

        if (goTenantsBtn) {
            goTenantsBtn.addEventListener('click', () => {
                document.querySelector('.menu-item[data-section="tenant-section"]')?.click();
            });
        }
    })();
   // Replace your existing tableBody click listener with this:
// Get reference to the dynamic body
const tenantTableBody = document.getElementById('tenant-table-body');

tenantTableBody.addEventListener("click", (e) => {
    const row = e.target.closest(".clickable-row");
    
    // Ignore clicks on the Deactivate button or the external Login link
    if (!row || e.target.closest('.sa-btn-toggle') || e.target.closest('.sa-tenant-link')) return;

    const tenantId = row.getAttribute('data-id');
    
    // Optional: Clear old data or show a spinner so the user knows it's loading
    document.getElementById('modal-clinic-name').textContent = "Loading...";

    fetch(`get_tenant_details.php?id=${tenantId}`)
        .then(res => res.json())
        .then(tenant => {
            // Fill the modal with fresh data from get_tenant_details.php
            document.getElementById('modal-clinic-name').textContent = tenant.company_name;
            document.getElementById('dt-owner').textContent = tenant.owner_name;
            document.getElementById('dt-email').textContent = tenant.contact_email;
            document.getElementById('dt-phone').textContent = tenant.phone;
            document.getElementById('dt-status').textContent = tenant.status;
            document.getElementById('dt-address').textContent = `${tenant.address}, ${tenant.city}, ${tenant.province}`;
            
            // Format date for the Philippine context
            const date = new Date(tenant.created_at).toLocaleDateString('en-PH', {
                month: 'long', day: 'numeric', year: 'numeric'
            });
            document.getElementById('dt-date').textContent = date;

            // Open the popup
            document.getElementById('details-modal').style.display = 'flex';
        })
        .catch(err => {
            console.error('Fetch error:', err);
            showToast('Error loading clinic details.');
        });
});

function closeDetailsModal() {
    document.getElementById('details-modal').style.display = 'none';
}

function viewTenantProfile(tenantId) {
    // Optional: Clear old data or show a spinner so the user knows it's loading
    document.getElementById('modal-clinic-name').textContent = "Loading...";

    fetch(`get_tenant_details.php?id=${tenantId}`)
        .then(res => res.json())
        .then(tenant => {
            // Fill the modal with fresh data from get_tenant_details.php
            document.getElementById('modal-clinic-name').textContent = tenant.company_name;
            document.getElementById('dt-owner').textContent = tenant.owner_name;
            document.getElementById('dt-email').textContent = tenant.contact_email;
            document.getElementById('dt-phone').textContent = tenant.phone;
            document.getElementById('dt-status').textContent = tenant.status;
            document.getElementById('dt-address').textContent = `${tenant.address}, ${tenant.city}, ${tenant.province}`;
            
            // Format date for the Philippine context
            const date = new Date(tenant.created_at).toLocaleDateString('en-PH', {
                month: 'long', day: 'numeric', year: 'numeric'
            });
            document.getElementById('dt-date').textContent = date;

            // Open the popup
            document.getElementById('details-modal').style.display = 'flex';
        })
        .catch(err => {
            console.error('Fetch error:', err);
            showToast('Error loading clinic details.');
        });
}
</script>

</body>
</html>

