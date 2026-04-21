<?php
function renderDateClock(): void {
    echo '<div class="date-clock-group" style="display:flex;align-items:center;gap:16px;">
            <div class="tenant-header-date">' . date('l, M d, Y') . '</div>
            <div id="liveClock" class="live-clock-badge">00:00:00 AM</div>
          </div>';
}

function printDateClockScript(): void {
    echo '<script>
        function initializeLiveClock(clockElementId = "liveClock") {
            const updateClock = () => {
                const now = new Date();
                const timeString = now.toLocaleTimeString("en-US", {
                    hour: "2-digit",
                    minute: "2-digit",
                    second: "2-digit",
                    hour12: true
                });
                const element = document.getElementById(clockElementId);
                if (element) {
                    element.textContent = timeString;
                }
            };
            
            // Update immediately
            updateClock();
            
            // Update every second
            setInterval(updateClock, 1000);
        }

        // Run immediately if DOM is ready, or on load
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", function() {
                initializeLiveClock();
            });
        } else {
            initializeLiveClock();
        }
    </script>';
}
