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
        $stmt = $conn->prepare("SELECT id, title, content, category, tenant_id FROM announcements WHERE (tenant_id = ? OR tenant_id IS NULL) AND status = 'active' AND publish_date <= NOW() ORDER BY publish_date DESC");
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
        $stmt = $conn->prepare("SELECT id, title, content, category, tenant_id FROM announcements WHERE tenant_id IS NULL AND status = 'active' AND publish_date <= NOW() ORDER BY publish_date DESC");
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $announcementsData[] = $r;
            }
            $stmt->close();
        }
    }

    if (!empty($announcementsData)) {
        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const announcements = <?php echo json_encode($announcementsData); ?>;
                if (!announcements || announcements.length === 0) return;

                const hiddenAnns = JSON.parse(sessionStorage.getItem('hiddenAnnouncements') || '[]');

                const visibleAnnouncements = announcements.filter(a => !hiddenAnns.includes(a.id));
                if (visibleAnnouncements.length === 0) return;

                // Find the main content area to prepend to
                let mainContent = document.querySelector('.tenant-main-content') || document.querySelector('.main-content') || document.querySelector('.superadmin-content');
                if (!mainContent) {
                    // Try layout containers
                    mainContent = document.querySelector('.tenant-layout') || document.body;
                }

                const bannerContainer = document.createElement('div');
                bannerContainer.className = 'global-announcement-banner';
                // Premium glassmorphism / vibrant style
                bannerContainer.style.cssText = 'background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #f8fafc; padding: 16px 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.4); display: flex; flex-direction: column; gap: 12px; font-family: inherit; position: relative; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); animation: slideDown 0.4s ease-out; z-index: 100;';

                // Add a micro-animation style
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
                    item.style.cssText = 'display: flex; flex-direction: column; gap: 6px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 12px; margin-bottom: 4px;';
                    
                    const header = document.createElement('div');
                    header.style.cssText = 'display: flex; align-items: center; gap: 10px;';
                    
                    const badge = document.createElement('span');
                    badge.style.cssText = `font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 4px 8px; border-radius: 6px; background: ${badgeBg}; color: white; letter-spacing: 0.5px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);`;
                    badge.textContent = badgeText;
                    
                    const title = document.createElement('strong');
                    title.style.cssText = 'font-size: 15px; font-weight: 700; color: #f8fafc; letter-spacing: 0.2px;';
                    title.textContent = ann.title;

                    header.appendChild(badge);
                    header.appendChild(title);
                    
                    const content = document.createElement('div');
                    content.style.cssText = 'font-size: 13.5px; color: #cbd5e1; white-space: pre-line; line-height: 1.5; margin-left: 2px;';
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

                // Add close button
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

                // Try to prepend into main content, just before the header bar if it exists
                const headerBar = mainContent.querySelector('.tenant-header-bar') || mainContent.querySelector('.sa-header-bar') || mainContent.querySelector('.dashboard-header') || mainContent.firstChild;
                if (headerBar && headerBar.parentNode === mainContent) {
                    mainContent.insertBefore(bannerContainer, headerBar);
                } else {
                    mainContent.insertBefore(bannerContainer, mainContent.firstChild);
                }
            });
        </script>
        <?php
    }
}
?>
