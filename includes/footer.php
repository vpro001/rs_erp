<?php
// includes/footer.php
?>
</main>

<!-- Standardized Footer Markup -->
<footer
    class="h-14 border-t border-slate-200  flex items-center justify-between px-6 bg-white  text-xs text-slate-500 ">
    <div>
        &copy; <?php echo date('Y'); ?> <span class="font-semibold">Royal Solutions ERP</span>. All rights reserved.
    </div>
    <div class="flex space-x-4">
        <a href="#" class="hover:text-slate-800  transition-colors">Privacy Policy</a>
        <a href="#" class="hover:text-slate-800  transition-colors">Terms of Service</a>
        <a href="#" class="hover:text-slate-800  transition-colors">Support Desk</a>
    </div>
</footer>

</div> <!-- End Main Content Area -->
</div> <!-- End App Container Layout -->

<!-- Script controls -->
<script>
    // Initialize Lucide Icons
    lucide.createIcons();

    // Elements
    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
    const mobileSidebarClose = document.getElementById('mobile-sidebar-close');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    const notificationsToggle = document.getElementById('notifications-toggle');
    const notificationsPanel = document.getElementById('notifications-panel');
    const profileToggle = document.getElementById('profile-toggle');
    const profilePanel = document.getElementById('profile-panel');

    // Mobile Sidebar Toggles
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', () => {
            sidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.remove('hidden');
        });
    }

    if (mobileSidebarClose) {
        mobileSidebarClose.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });
    }

    // Toggle Dropdowns with outside clicks handler
    if (notificationsToggle && notificationsPanel) {
        notificationsToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationsPanel.classList.toggle('hidden');
            if (profilePanel) profilePanel.classList.add('hidden');
        });
    }

    if (profileToggle && profilePanel) {
        profileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            profilePanel.classList.toggle('hidden');
            if (notificationsPanel) notificationsPanel.classList.add('hidden');
        });
    }

    document.addEventListener('click', (e) => {
        if (notificationsPanel && !notificationsPanel.classList.contains('hidden')) {
            if (!notificationsPanel.contains(e.target) && e.target !== notificationsToggle) {
                notificationsPanel.classList.add('hidden');
            }
        }
        if (profilePanel && !profilePanel.classList.contains('hidden')) {
            if (!profilePanel.contains(e.target) && e.target !== profileToggle) {
                profilePanel.classList.add('hidden');
            }
        }
    });

    // Mark all read dummy listener
    const markAllReadBtn = document.getElementById('mark-all-read');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', () => {
            const apiUrl = (window.BASE_URL || '') + '/api/notifications_mark_read.php';
            fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                })
                .catch(err => {
                    console.error("Error marking read:", err);
                });
        });
    }
</script>

<!-- Real-time Toast Notification Container -->
<div id="toast-container" class="fixed bottom-5 right-5 z-[9999] flex flex-col gap-3 w-80"></div>

<script>
    // EventSource Real-time SSE Notifications
    <?php if (current_user()): ?>
            (function () {
                const streamUrl = (window.BASE_URL || '') + '/api/notifications_stream.php';
                const eventSource = new EventSource(streamUrl);

                eventSource.onmessage = function (event) {
                    const notif = JSON.parse(event.data);
                    if (notif.error) return;

                    // Play double synthesizer chime
                    playNotificationChime();

                    // Show toast
                    showNotificationToast(notif);

                    // Increment badge and insert into list
                    const badge = document.getElementById('notifications-badge');
                    if (badge) badge.classList.remove('hidden');

                    const list = document.getElementById('notifications-list');
                    if (list) {
                        // Remove the "No notifications" placeholder if it exists
                        const placeholder = list.querySelector('.py-6');
                        if (placeholder) placeholder.remove();

                        const notifHTML = `
                    <a href="${notif.link ? escapeHTML(notif.link) : '#'}" class="block px-4 py-3 bg-blue-500/5  transition-colors">
                        <div class="flex justify-between items-start">
                            <span class="font-semibold text-xs text-slate-900 ">${escapeHTML(notif.title)}</span>
                            <span class="text-[10px] text-slate-400">Just Now</span>
                        </div>
                        <p class="text-xs text-slate-500  mt-0.5 line-clamp-2">${escapeHTML(notif.message)}</p>
                    </a>
                `;
                        list.insertAdjacentHTML('afterbegin', notifHTML);
                    }
                };

                function escapeHTML(str) {
                    return str.replace(/[&<>'"]/g,
                        tag => ({ '&': '&', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
                    );
                }

                function showNotificationToast(notif) {
                    const container = document.getElementById('toast-container');
                    if (!container) return;

                    const toast = document.createElement('div');
                    toast.className = "p-4 bg-white/95  backdrop-blur-md border border-slate-200  rounded-xl shadow-lg flex items-start gap-3 transition-all duration-300 transform translate-y-2 opacity-0";

                    toast.innerHTML = `
                <div class="bg-blue-500/10 p-2 rounded-lg text-blue-600  flex-shrink-0">
                    <i data-lucide="bell" class="w-4 h-4"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h5 class="text-xs font-bold text-slate-900 ">${escapeHTML(notif.title)}</h5>
                    <p class="text-[10px] text-slate-500  mt-0.5 line-clamp-2">${escapeHTML(notif.message)}</p>
                </div>
                <button class="text-slate-400 hover:text-slate-600  transition-colors" onclick="this.parentElement.remove()">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                </button>
            `;

                    container.appendChild(toast);
                    lucide.createIcons({ node: toast });

                    // Animate in
                    setTimeout(() => {
                        toast.classList.remove('translate-y-2', 'opacity-0');
                    }, 10);

                    // Auto dismiss after 6 seconds
                    setTimeout(() => {
                        toast.classList.add('opacity-0', 'translate-y-2');
                        setTimeout(() => toast.remove(), 300);
                    }, 6000);
                }

                function playNotificationChime() {
                    try {
                        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = audioCtx.createOscillator();
                        const gain = audioCtx.createGain();

                        osc.connect(gain);
                        gain.connect(audioCtx.destination);

                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(523.25, audioCtx.currentTime); // C5
                        gain.gain.setValueAtTime(0.2, audioCtx.currentTime);

                        osc.start();
                        osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.12); // A5
                        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.25);

                        setTimeout(() => {
                            osc.stop();
                            audioCtx.close();
                        }, 250);
                    } catch (e) {
                        console.warn("Audio Context chime block:", e);
                    }
                }
            })();
    <?php endif; ?>
</script>
</body>

</html>