<?php
function renderCustomModal(): void {
    echo <<<EOF
<div id="oralsyncModal" class="custom-modal" aria-hidden="true">
            <div class="custom-modal-card" role="dialog" aria-modal="true" aria-labelledby="customModalTitle">
                <button type="button" class="custom-modal-close" onclick="closeCustomModal()">&times;</button>
                <div class="custom-modal-body">
                    <h2 id="customModalTitle">Notice</h2>
                    <p id="customModalMessage"></p>
                </div>
                <div class="custom-modal-actions">
                    <button type="button" class="custom-modal-button btn-primary" onclick="closeCustomModal()">OK</button>
                </div>
            </div>
          </div>
          <style>
            .custom-modal {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(10, 14, 24, 0.85);
                backdrop-filter: blur(6px);
                align-items: center;
                justify-content: center;
                z-index: 9999;
            }
            .custom-modal.show {
                display: flex;
            }
            .custom-modal-card {
                width: min(95%, 420px);
                background: #111827;
                border: 1px solid rgba(148, 163, 184, 0.28);
                border-radius: 18px;
                padding: 24px;
                color: #f8fafc;
                box-shadow: 0 24px 60px rgba(15, 23, 42, 0.35);
            }
            .custom-modal-close {
                position: absolute;
                right: 18px;
                top: 18px;
                border: none;
                background: transparent;
                color: #cbd5e1;
                font-size: 22px;
                cursor: pointer;
            }
            .custom-modal-body h2 {
                margin: 0 0 12px;
                font-size: 1.3rem;
                color: #f8fafc;
            }
            .custom-modal-body p {
                margin: 0;
                color: #cbd5e1;
                line-height: 1.7;
            }
            .custom-modal-actions {
                margin-top: 24px;
                display: flex;
                justify-content: flex-end;
            }
            .custom-modal-button {
                background: #0d3b66;
                color: #f8fafc;
                border: 1px solid transparent;
                border-radius: 10px;
                padding: 10px 20px;
                cursor: pointer;
                font-weight: 700;
            }
            .custom-modal-button:hover {
                background: #112f5a;
            }
          </style>
          <script>
            function showCustomAlert(message, title = 'Notice') {
                const dialog = document.getElementById('oralsyncModal');
                const titleEl = document.getElementById('customModalTitle');
                const messageEl = document.getElementById('customModalMessage');
                if (!dialog || !messageEl || !titleEl) return;
                titleEl.textContent = title;
                messageEl.textContent = message;
                dialog.classList.add('show');
                dialog.setAttribute('aria-hidden', 'false');
            }

            function closeCustomModal() {
                const dialog = document.getElementById('oralsyncModal');
                if (!dialog) return;
                dialog.classList.remove('show');
                dialog.setAttribute('aria-hidden', 'true');
            }

            document.addEventListener('click', function(event) {
                const dialog = document.getElementById('oralsyncModal');
                if (!dialog || !dialog.classList.contains('show')) return;
                if (event.target === dialog) {
                    closeCustomModal();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeCustomModal();
                }
            });
          </script>
EOF;
}
