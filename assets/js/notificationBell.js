/**
 * Notification bell, shown just left of the hamburger menu button.
 *
 * Clicking it opens a small pop-up listing the user's recent notifications
 * (the same reminders that are pushed to their devices — see
 * assets/api/helpers/reminderHelper.php). The red badge counts unread ones;
 * opening the pop-up marks them all as read.
 *
 * Include this script on any page that has the #menuToggle hamburger button,
 * after hamburgerMenu.css (which holds the bell's styles).
 */
(function () {
    const APP_ROOT = '/AUT-Web-Based-Travel-Planner';
    const API = `${APP_ROOT}/assets/api/notifications`;

    function init() {
        const menuToggle = document.getElementById('menuToggle');
        if (!menuToggle) {
            return;
        }

        const bell = document.createElement('button');
        bell.type = 'button';
        bell.className = 'notification-bell';
        bell.setAttribute('aria-label', 'Notifications');
        bell.setAttribute('aria-expanded', 'false');
        bell.setAttribute('aria-controls', 'notificationPopup');
        bell.innerHTML =
            '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">' +
            '<path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.5-6.84V3.5a1.5 1.5 0 0 0-3 0v.66A7 7 0 0 0 5 11v5l-2 2v1h18v-1l-2-2Z"/>' +
            '</svg>' +
            '<span class="notification-badge" hidden></span>';
        const badge = bell.querySelector('.notification-badge');

        const popup = document.createElement('div');
        popup.className = 'notification-popup';
        popup.id = 'notificationPopup';
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-label', 'Recent notifications');
        popup.hidden = true;
        popup.innerHTML =
            '<div class="notification-popup-header">Notifications</div>' +
            '<ul class="notification-list"></ul>';
        const list = popup.querySelector('.notification-list');

        menuToggle.before(bell);
        document.body.appendChild(popup);

        function setBadge(count) {
            badge.hidden = count === 0;
            badge.textContent = count > 9 ? '9+' : String(count);
            bell.setAttribute('aria-label', count ? `Notifications (${count} unread)` : 'Notifications');
        }

        function timeAgo(seconds) {
            const diff = Math.max(0, Date.now() / 1000 - seconds);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return `${Math.floor(diff / 60)} min ago`;
            if (diff < 86400) return `${Math.floor(diff / 3600)} h ago`;
            if (diff < 7 * 86400) return `${Math.floor(diff / 86400)} d ago`;
            return new Date(seconds * 1000).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
        }

        function showMessage(text) {
            list.innerHTML = '';
            const item = document.createElement('li');
            item.className = 'notification-empty';
            item.textContent = text;
            list.appendChild(item);
        }

        // Built with textContent so reminder text (trip names etc.) can never inject markup.
        function render(notifications) {
            if (!notifications.length) {
                showMessage('No notifications yet. Reminders about your upcoming flights, stays and activities will appear here.');
                return;
            }
            list.innerHTML = '';
            notifications.forEach((n) => {
                const item = document.createElement('li');
                const link = document.createElement('a');
                link.className = 'notification-item' + (n.unread ? ' unread' : '');
                // Only ever link within the app.
                link.href = n.url.startsWith(`${APP_ROOT}/`) ? n.url : `${APP_ROOT}/Pages/userDashboard/Dashboard.php`;

                const title = document.createElement('span');
                title.className = 'notification-title';
                title.textContent = n.title;
                const body = document.createElement('span');
                body.className = 'notification-body';
                body.textContent = n.body;
                const time = document.createElement('span');
                time.className = 'notification-time';
                time.textContent = timeAgo(n.created_ts);

                link.append(title, body, time);
                item.appendChild(link);
                list.appendChild(item);
            });
        }

        function fetchRecent() {
            return fetch(`${API}/recent.php`, { credentials: 'same-origin', cache: 'no-store' })
                .then((response) => (response.ok ? response.json() : Promise.reject(response)));
        }

        function refreshBadge() {
            fetchRecent().then((data) => setBadge(data.unread)).catch(() => {});
        }

        function open() {
            popup.hidden = false;
            bell.setAttribute('aria-expanded', 'true');
            showMessage('Loading…');
            fetchRecent()
                .then((data) => {
                    render(data.notifications);
                    if (data.unread > 0) {
                        // Items stay highlighted as new until the pop-up is next opened.
                        fetch(`${API}/markRead.php`, { method: 'POST', credentials: 'same-origin' })
                            .then((response) => { if (response.ok) setBadge(0); })
                            .catch(() => {});
                    }
                })
                .catch(() => showMessage('Unable to load notifications right now.'));
        }

        function close() {
            popup.hidden = true;
            bell.setAttribute('aria-expanded', 'false');
        }

        bell.addEventListener('click', (event) => {
            event.stopPropagation();
            popup.hidden ? open() : close();
        });

        // Close when clicking anywhere outside the pop-up, or pressing Escape.
        document.addEventListener('click', (event) => {
            if (!popup.hidden && !popup.contains(event.target)) {
                close();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !popup.hidden) {
                close();
                bell.focus();
            }
        });

        // The open menu panel slides over the bell's spot, so step aside for it.
        new MutationObserver(() => {
            const menuOpen = menuToggle.classList.contains('open');
            bell.hidden = menuOpen;
            if (menuOpen) close();
        }).observe(menuToggle, { attributes: true, attributeFilter: ['class'] });

        refreshBadge();
        // Pick up reminders that arrived while the tab was in the background.
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') refreshBadge();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
