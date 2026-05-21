<?php
/**
 * Global Toast Notification and File Validation Utility
 * Include this file in any page that needs toast notifications or file size validation.
 */
?>
<style>
    .toast-notification {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: #0f172a;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        z-index: 9999;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-size: 0.9rem;
        pointer-events: none;
        font-family: inherit;
    }
    .toast-notification.show {
        transform: translateY(0);
        opacity: 1;
    }
</style>
<div id="toast-notification" class="toast-notification"></div>
<script>
    if (typeof showToast !== 'function') {
        window.showToast = function(message, durationMs = 4500) {
            const toast = document.getElementById('toast-notification');
            if (!toast) {
                console.log('Toast: ' + message);
                return;
            }
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, durationMs);
        };
    }

    if (typeof validateFiles !== 'function') {
        window.validateFiles = function(files, maxMB = 50) {
            if (!files || files.length === 0) return true;
            const maxBytes = maxMB * 1024 * 1024;
            
            for (let i = 0; i < files.length; i++) {
                if (files[i].size > maxBytes) {
                    showToast('File size is too big. Max limit is ' + maxMB + 'MB per file.');
                    return false;
                }
            }
            return true;
        };
    }

    // Auto-attach validation to all file inputs on the page
    document.addEventListener('change', function(e) {
        if (e.target && e.target.type === 'file') {
            // Get max size from data attribute or default to 50MB
            const maxSize = e.target.dataset.maxSize || 50;
            if (!validateFiles(e.target.files, maxSize)) {
                e.target.value = ''; // Clear the input
            }
        }
    });
</script>

<?php include_once __DIR__ . '/global_announcements.php'; ?>
