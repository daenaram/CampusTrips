/**
 * CampusTrips service worker.
 *
 * Scope is the app root (this file is served from
 * /AUT-Web-Based-Travel-Planner/sw.js, so by default it controls
 * everything under /AUT-Web-Based-Travel-Planner/).
 *
 * Strategy:
 *  - Static assets (css/js/images/fonts): cache-first, for fast repeat
 *    loads and to keep the app shell available offline.
 *  - Page navigations (Dashboard.php, budget.php, etc.): network-first —
 *    always prefer live, session-aware content; only fall back to the
 *    last cached copy of that exact page (or the offline page) when the
 *    network is unreachable.
 *  - Everything else (POST requests, cross-origin calls like Firebase)
 *    is left alone entirely — this worker never touches writes.
 *
 * Bump CACHE_VERSION to roll out a new app-shell precache and drop old ones.
 */

const CACHE_VERSION = 'campustrips-v1';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const PAGES_CACHE = `${CACHE_VERSION}-pages`;
const OFFLINE_URL = '/AUT-Web-Based-Travel-Planner/offline.html';

const APP_SHELL = [
    '/AUT-Web-Based-Travel-Planner/',
    '/AUT-Web-Based-Travel-Planner/index.html',
    OFFLINE_URL,
    '/AUT-Web-Based-Travel-Planner/manifest.json',
    '/AUT-Web-Based-Travel-Planner/assets/css/indexStyles.css',
    '/AUT-Web-Based-Travel-Planner/assets/css/dashboard.css',
    '/AUT-Web-Based-Travel-Planner/assets/css/hamburgerMenu.css',
    '/AUT-Web-Based-Travel-Planner/assets/css/settingsbutton.css',
    '/AUT-Web-Based-Travel-Planner/assets/css/calendar.css',
    '/AUT-Web-Based-Travel-Planner/assets/css/conflictAlert.css',
    '/AUT-Web-Based-Travel-Planner/assets/css/budget.css',
    '/AUT-Web-Based-Travel-Planner/assets/css/loginformStyles.css',
    '/AUT-Web-Based-Travel-Planner/assets/js/pwa.js',
    '/AUT-Web-Based-Travel-Planner/assets/images/campustripslogo.png',
    '/AUT-Web-Based-Travel-Planner/assets/images/icons/icon-192.png',
    '/AUT-Web-Based-Travel-Planner/assets/images/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(APP_SHELL))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith('campustrips-') && key !== STATIC_CACHE && key !== PAGES_CACHE)
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

// Lets a page ask the worker to drop any cached page HTML — used on sign
// out so a stale, personalized dashboard is never served to whoever uses
// the browser next. Static assets (css/js/images) are left cached; they
// carry no user data.
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'CLEAR_PAGES_CACHE') {
        event.waitUntil(caches.delete(PAGES_CACHE));
    }
});

function isStaticAsset(url) {
    return /\.(css|js|png|jpe?g|svg|webp|gif|ico|woff2?|ttf)$/i.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Only ever handle same-origin GET requests. Form posts, budget/share
    // AJAX writes, and cross-origin calls (Firebase auth, Google fonts)
    // are left completely untouched.
    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    return cached;
                }
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                });
            })
        );
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(PAGES_CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
                .catch(() => caches.match(request).then((cached) => cached || caches.match(OFFLINE_URL)))
        );
    }
});
