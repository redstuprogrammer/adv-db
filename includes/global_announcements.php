<?php
// Ensure this doesn't crash if included somewhere without conn
if (isset($conn) && $conn instanceof mysqli) {
    // Determine tenant ID if available
    $globalAnnTenantId = null;
    if (class_exists('SessionManager')) {
        $sm = SessionManager::getInstance();
        if ($sm->getTenantId()) {
            $globalAnnTenantId = $sm->getTenantId();
        }
    } else if (isset($_SESSION['tenant_id'])) {
        $globalAnnTenantId = $_SESSION['tenant_id'];
    }

    // Prepare query for active announcements
    $announcementsData = [];
    if ($globalAnnTenantId) {
        $stmt = $conn->prepare("SELECT id, title, content, category, tenant_id, publish_date FROM announcements WHERE (tenant_id = ? OR tenant_id IS NULL) AND status = 'active' AND publish_date <= NOW() ORDER BY publish_date DESC");
        if ($stmt) {
            $stmt->bind_param("i", $globalAnnTenantId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $announcementsData[] = $r;
            }
            $stmt->close();
        }
    } else {
        // Superadmin or no tenant context
        $stmt = $conn->prepare("SELECT id, title, content, category, tenant_id, publish_date FROM announcements WHERE tenant_id IS NULL AND status = 'active' AND publish_date <= NOW() ORDER BY publish_date DESC");
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $announcementsData[] = $r;
            }
            $stmt->close();
        }
    }

    $billingAlertsData = [];
    if ($globalAnnTenantId) {
        $result = $conn->query("SHOW TABLES LIKE 'subscription_renewal_attempts'");
        if ($result && $result->num_rows > 0) {
            $stmt = $conn->prepare("SELECT id, attempt_count, status, response, next_retry_at, is_read, created_at FROM subscription_renewal_attempts WHERE tenant_id = ? AND status != 'success' ORDER BY created_at DESC");
            if ($stmt) {
                $stmt->bind_param("i", $globalAnnTenantId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $billingAlertsData[] = $r;
                }
                $stmt->close();
            }
        }
    }

    ?>
    <script>
        // Global toggle function
        function toggleNotificationSidebar() {
            const sidebar = document.getElementById('globalNotificationSidebar');
            if (sidebar) {
                if (sidebar.style.transform === 'translateX(100%)') {
                    sidebar.style.transform = 'translateX(0)';
                    sidebar.style.boxShadow = '-10px 0 30px rgba(15, 23, 42, 0.1)';
                } else {
                    sidebar.style.transform = 'translateX(100%)';
                    sidebar.style.boxShadow = 'none';
                }
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            const announcements = <?php echo json_encode($announcementsData); ?> || [];
            const billingAlerts = <?php echo json_encode($billingAlertsData); ?> || [];
            
            const hiddenAnns = JSON.parse(sessionStorage.getItem('hiddenAnnouncements') || '[]');
            const readAlerts = JSON.parse(sessionStorage.getItem('readBillingAlerts') || '[]');

            const visibleAnnouncements = announcements.filter(a => !hiddenAnns.includes(a.id));
            const unreadAlerts = billingAlerts.filter(a => a.is_read == 0 && !readAlerts.includes(a.id));

            let totalUnread = visibleAnnouncements.length + unreadAlerts.length;

            // Update badge count
            const badge = document.getElementById('globalNotificationBadge');
            if (badge) {
                if (totalUnread > 0) {
                    badge.style.display = 'block';
                    badge.textContent = totalUnread > 9 ? '9+' : totalUnread;
                } else {
                    badge.style.display = 'none';
                }
            }

            // Create Sidebar
            const sidebar = document.createElement('div');
            sidebar.id = 'globalNotificationSidebar';
            sidebar.style.cssText = 'position: fixed; top: 0; right: 0; width: 400px; max-width: 100vw; height: 100vh; background: #f8fafc; z-index: 10000; transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; border-left: 1px solid #e2e8f0; font-family: inherit;';
            
            let sidebarHTML = `
                <div style="padding: 24px; background: white; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="margin: 0; font-size: 18px; color: #0d3b66; font-weight: 800;">Notifications</h2>
                        <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">${totalUnread} unread</p>
                    </div>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <button id="markAllReadBtn" style="background: none; border: none; color: #2563eb; font-size: 13px; font-weight: 600; cursor: pointer; padding: 4px 8px; border-radius: 6px;" onmouseover="this.style.background=\'#eff6ff\'" onmouseout="this.style.background=\'transparent\'">Mark all read</button>
                        <button onclick="toggleNotificationSidebar()" style="background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 16px; color: #475569;" onmouseover="this.style.background=\'#e2e8f0\'" onmouseout="this.style.background=\'#f1f5f9\'">✕</button>
                    </div>
                </div>
                <div style="flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 16px;">
            `;

            if (billingAlerts.length > 0) {
                billingAlerts.forEach(alert => {
                    const isRead = alert.is_read == 1 || readAlerts.includes(alert.id);
                    sidebarHTML += `
                        <div style="background: white; border: 1px solid ${isRead ? '#e2e8f0' : '#fecdd3'}; border-radius: 12px; padding: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); position: relative;">
                            ${!isRead ? '<div style="position: absolute; top: 16px; right: 16px; width: 8px; height: 8px; background: #ef4444; border-radius: 50%;"></div>' : ''}
                            <div style="font-size: 11px; font-weight: 700; color: #be123c; text-transform: uppercase; margin-bottom: 6px;">Billing Alert</div>
                            <strong style="color: #0d3b66; font-size: 14px; display: block; margin-bottom: 6px;">Renewal attempt ${alert.attempt_count} failed</strong>
                            <p style="margin: 0 0 10px; color: #475569; font-size: 13px;">Status: ${alert.status}</p>
                            ${alert.next_retry_at ? `<p style="margin: 0; color: #64748b; font-size: 12px;">Next retry: ${new Date(alert.next_retry_at).toLocaleString()}</p>` : ''}
                        </div>
                    `;
                });
            }

            if (announcements.length > 0) {
                announcements.forEach(ann => {
                    const isPlatform = ann.tenant_id === null;
                    const badgeText = isPlatform ? "Platform Update" : "Clinic Announcement";
                    const badgeBg = isPlatform ? "linear-gradient(135deg, #e11d48, #be123c)" : "linear-gradient(135deg, #0284c7, #0369a1)";
                    const isRead = hiddenAnns.includes(ann.id);
                    
                    sidebarHTML += `
                        <div style="background: white; border: 1px solid ${isRead ? '#e2e8f0' : '#bae6fd'}; border-radius: 12px; padding: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); position: relative;">
                            ${!isRead ? '<div style="position: absolute; top: 16px; right: 16px; width: 8px; height: 8px; background: #3b82f6; border-radius: 50%;"></div>' : ''}
                            <span style="display: inline-block; font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 3px 6px; border-radius: 4px; background: ${badgeBg}; color: white; margin-bottom: 8px;">${badgeText}</span>
                            <strong style="color: #0d3b66; font-size: 14px; display: block; margin-bottom: 6px;">${ann.title}</strong>
                            <p style="margin: 0 0 10px; color: #475569; font-size: 13px; line-height: 1.5; white-space: pre-line;">${ann.content}</p>
                            <p style="margin: 0; color: #94a3b8; font-size: 11px;">${new Date(ann.publish_date).toLocaleString()}</p>
                        </div>
                    `;
                });
            }

            if (billingAlerts.length === 0 && announcements.length === 0) {
                sidebarHTML += `
                    <div style="text-align: center; color: #94a3b8; padding: 40px 20px;">
                        <div style="font-size: 32px; margin-bottom: 12px;">📭</div>
                        <p style="margin: 0; font-size: 14px;">No notifications right now.</p>
                    </div>
                `;
            }

            sidebarHTML += `</div>`;
            sidebar.innerHTML = sidebarHTML;
            document.body.appendChild(sidebar);

            // Handle Mark All Read
            const markAllBtn = document.getElementById('markAllReadBtn');
            if (markAllBtn) {
                markAllBtn.onclick = function() {
                    announcements.forEach(a => hiddenAnns.push(a.id));
                    sessionStorage.setItem('hiddenAnnouncements', JSON.stringify(hiddenAnns));
                    
                    billingAlerts.forEach(a => readAlerts.push(a.id));
                    sessionStorage.setItem('readBillingAlerts', JSON.stringify(readAlerts));
                    
                    // Mark as read in DB if it's billing alerts (optional, but let's do it seamlessly via an image ping or similar if needed. For now just hide them).
                    // Update badge
                    if(badge) badge.style.display = 'none';
                    
                    // Remove blue/red unread dots
                    const dots = sidebar.querySelectorAll('div[style*="border-radius: 50%"][style*="width: 8px"]');
                    dots.forEach(d => d.style.display = 'none');

                    // Reset borders
                    const cards = sidebar.querySelectorAll('div[style*="border: 1px solid"]');
                    cards.forEach(c => {
                        if (c.style.borderColor === 'rgb(254, 205, 211)' || c.style.borderColor === 'rgb(186, 230, 253)' || c.style.borderColor.includes('#fecdd3') || c.style.borderColor.includes('#bae6fd')) {
                            c.style.borderColor = '#e2e8f0';
                        }
                    });

                    // Hide banner if visible
                    const banner = document.querySelector('.global-announcement-banner');
                    if (banner) banner.remove();
                };
            }

            // Dashboard Banner Logic
            const isDashboard = window.location.pathname.includes('dashboard.php') || window.location.pathname.includes('superadmin_dash.php');
            if (isDashboard && visibleAnnouncements.length > 0) {
                let mainContent = document.querySelector('.tenant-main-content') || document.querySelector('.main-content') || document.querySelector('.superadmin-content');
                if (!mainContent) {
                    mainContent = document.querySelector('.tenant-layout') || document.body;
                }

                const bannerContainer = document.createElement('div');
                bannerContainer.className = 'global-announcement-banner';
                bannerContainer.style.cssText = 'background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #f8fafc; padding: 28px 32px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.4); display: flex; flex-direction: column; gap: 16px; font-family: inherit; position: relative; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); animation: slideDown 0.4s ease-out; z-index: 100; width: 100%; box-sizing: border-box; min-height: 120px;';

                if (!document.getElementById('announcement-styles')) {
                    const style = document.createElement('style');
                    style.id = 'announcement-styles';
                    style.innerHTML = `
                        @keyframes slideDown {
                            from { opacity: 0; transform: translateY(-10px); }
                            to { opacity: 1; transform: translateY(0); }
                        }
                    `;
                    document.head.appendChild(style);
                }

                visibleAnnouncements.forEach(ann => {
                    const isPlatform = ann.tenant_id === null;
                    const badgeText = isPlatform ? "Platform Update" : "Clinic Announcement";
                    const badgeBg = isPlatform ? "linear-gradient(135deg, #e11d48, #be123c)" : "linear-gradient(135deg, #0284c7, #0369a1)";
                    
                    const item = document.createElement('div');
                    item.style.cssText = 'display: flex; flex-direction: column; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 16px; margin-bottom: 8px;';
                    
                    const header = document.createElement('div');
                    header.style.cssText = 'display: flex; align-items: center; gap: 12px;';
                    
                    const badge = document.createElement('span');
                    badge.style.cssText = `font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 6px 10px; border-radius: 6px; background: ${badgeBg}; color: white; letter-spacing: 0.5px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);`;
                    badge.textContent = badgeText;
                    
                    const title = document.createElement('strong');
                    title.style.cssText = 'font-size: 18px; font-weight: 800; color: #f8fafc; letter-spacing: 0.2px; line-height: 1.3;';
                    title.textContent = ann.title;

                    header.appendChild(badge);
                    header.appendChild(title);
                    
                    const content = document.createElement('div');
                    content.style.cssText = 'font-size: 15px; color: #cbd5e1; white-space: pre-line; line-height: 1.6; margin-left: 2px;';
                    content.textContent = ann.content;
                    
                    item.appendChild(header);
                    item.appendChild(content);
                    bannerContainer.appendChild(item);
                });

                if (bannerContainer.lastChild) {
                    bannerContainer.lastChild.style.borderBottom = 'none';
                    bannerContainer.lastChild.style.marginBottom = '0';
                    bannerContainer.lastChild.style.paddingBottom = '0';
                }

                const closeBtn = document.createElement('button');
                closeBtn.innerHTML = '✕';
                closeBtn.style.cssText = 'position: absolute; top: 12px; right: 16px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.1); color: white; font-size: 12px; font-weight: bold; cursor: pointer; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; z-index: 10;';
                closeBtn.title = "Dismiss";
                closeBtn.onclick = () => {
                    bannerContainer.style.opacity = '0';
                    bannerContainer.style.transform = 'translateY(-10px)';
                    bannerContainer.style.transition = 'all 0.3s ease';
                    setTimeout(() => bannerContainer.remove(), 300);
                    
                    visibleAnnouncements.forEach(a => hiddenAnns.push(a.id));
                    sessionStorage.setItem('hiddenAnnouncements', JSON.stringify(hiddenAnns));

                    totalUnread = announcements.filter(a => !hiddenAnns.includes(a.id)).length + unreadAlerts.length;
                    if(badge) {
                        if (totalUnread > 0) {
                            badge.style.display = 'block';
                            badge.textContent = totalUnread > 9 ? '9+' : totalUnread;
                        } else {
                            badge.style.display = 'none';
                        }
                    }

                    // Update sidebar styling for the announcements
                    const dots = sidebar.querySelectorAll('div[style*="background: rgb(59, 130, 246)"]');
                    dots.forEach(d => d.style.display = 'none');
                    const cards = sidebar.querySelectorAll('div[style*="border-color: rgb(186, 230, 253)"]');
                    cards.forEach(c => c.style.borderColor = '#e2e8f0');
                };
                closeBtn.onmouseover = () => {
                    closeBtn.style.background = 'rgba(255,255,255,0.2)';
                    closeBtn.style.transform = 'scale(1.1)';
                };
                closeBtn.onmouseout = () => {
                    closeBtn.style.background = 'rgba(255,255,255,0.1)';
                    closeBtn.style.transform = 'scale(1)';
                };
                
                bannerContainer.appendChild(closeBtn);

                const headerBar = mainContent.querySelector('.tenant-header-bar') || mainContent.querySelector('.sa-header-bar') || mainContent.querySelector('.dashboard-header') || mainContent.firstChild;
                if (headerBar && headerBar.parentNode === mainContent) {
                    mainContent.insertBefore(bannerContainer, headerBar.nextSibling || headerBar); // Insert after header bar if possible, or before
                } else {
                    mainContent.insertBefore(bannerContainer, mainContent.firstChild);
                }
            }
        });
    </script>
    <?php
}
?>
