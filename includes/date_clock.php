<?php
function renderDateClock(): void {
    echo '<div class="date-clock-group" style="display:flex;align-items:center;gap:16px;">
            <div class="tenant-header-date">' . date('l, M d, Y') . '</div>
            <div id="liveClock" class="live-clock-badge">00:00:00 AM</div>
            <div id="globalNotificationBell" style="position:relative; cursor:pointer; width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.8); display:flex; align-items:center; justify-content:center; box-shadow:0 2px 5px rgba(0,0,0,0.05); transition:all 0.2s ease;" onmouseover="this.style.background=\'white\';this.style.boxShadow=\'0 4px 10px rgba(0,0,0,0.1)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.8)\';this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.05)\'" onclick="if(typeof toggleNotificationSidebar === \'function\') toggleNotificationSidebar();">
                <span style="font-size:18px;">🔔</span>
                <span id="globalNotificationBadge" style="position:absolute; top:-4px; right:-4px; background:#ef4444; color:white; font-size:11px; font-weight:800; border-radius:999px; padding:2px 6px; display:none; border: 2px solid #f8fafc;">0</span>
            </div>
          </div>';
}

function printDateClockScript(): void {
    echo '
        document.addEventListener("DOMContentLoaded", function() {
            const updateClock = () => {
                const now = new Date();
                const timeString = now.toLocaleTimeString("en-US", {
                    hour: "2-digit",
                    minute: "2-digit",
                    second: "2-digit",
                    hour12: true
                });
                const element = document.getElementById("liveClock");
                if (element) {
                    element.textContent = timeString;
                }
            };
            updateClock();
            setInterval(updateClock, 1000);
            console.log("Live clock initialized");
        });
    ';
}

