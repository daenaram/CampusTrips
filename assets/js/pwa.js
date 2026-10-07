/**
 * PWA wiring shared by every page: manifest link, icon/theme meta tags,
 * and service worker registration — in one file so each page only needs
 * <script src="/AUT-Web-Based-Travel-Planner/assets/js/pwa.js"></script>
 * instead of duplicating a block of <link>/<meta> tags everywhere. Uses
 * absolute paths throughout since pages live at different folder depths.
 */
(function () {
    const APP_ROOT = '/AUT-Web-Based-Travel-Planner';

    function addHeadTag(tagName, attrs) {
        const el = document.createElement(tagName);
        Object.keys(attrs).forEach(function (key) {
            el.setAttribute(key, attrs[key]);
        });
        document.head.appendChild(el);
    }

    addHeadTag('link', { rel: 'manifest', href: APP_ROOT + '/manifest.json' });
    addHeadTag('meta', { name: 'theme-color', content: '#0078d4' });

    // iOS/Safari doesn't read the manifest for install behaviour — it needs its own tags.
    addHeadTag('meta', { name: 'apple-mobile-web-app-capable', content: 'yes' });
    addHeadTag('meta', { name: 'apple-mobile-web-app-status-bar-style', content: 'default' });
    addHeadTag('meta', { name: 'apple-mobile-web-app-title', content: 'CampusTrips' });
    addHeadTag('link', { rel: 'apple-touch-icon', href: APP_ROOT + '/assets/images/icons/apple-touch-icon.png' });

    addHeadTag('link', { rel: 'icon', type: 'image/png', sizes: '32x32', href: APP_ROOT + '/assets/images/icons/favicon-32.png' });
    addHeadTag('link', { rel: 'icon', type: 'image/png', sizes: '16x16', href: APP_ROOT + '/assets/images/icons/favicon-16.png' });

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker
                .register(APP_ROOT + '/sw.js', { scope: APP_ROOT + '/' })
                .catch(function (err) {
                    console.warn('Service worker registration failed:', err);
                });
        });

        // Drop any cached page HTML on sign-out, so a stale, personalized
        // dashboard can never be served to the next person on this device.
        // Static assets (css/js/images) stay cached — they carry no user data.
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('a[href*="signout.php"], button[onclick*="signout.php"]');
            if (trigger && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({ type: 'CLEAR_PAGES_CACHE' });
            }
        }, true);
    }
})();
