/**
 * Browser side of push notifications: checks Web Push support, subscribes
 * this device through the service worker registered by pwa.js, and tells
 * the server about it so reminders can be sent while the app is closed.
 *
 * Exposes window.CampusPush:
 *   isSupported()        -> boolean
 *   unsupportedMessage() -> string explaining why, with a browser to try instead
 *   getState()           -> Promise<'unsupported'|'denied'|'enabled'|'off'> for this browser/device
 *   blockedHelp()        -> { browser, steps[] } for re-allowing notifications after a denial
 *   isEnabled()          -> Promise<boolean> (this device is subscribed for the signed-in user)
 *   enable()             -> Promise; rejects with an Error whose message is user-facing and
 *                           whose .code is 'unsupported', 'denied', 'dismissed' or 'failed'
 *   disable()            -> Promise
 *   sendTest()           -> Promise; rejects with a user-facing Error message
 *   markPrompted()       -> Promise; records that the dashboard's first-login prompt was answered
 */
(function () {
    const API = '/AUT-Web-Based-Travel-Planner/assets/api/notifications';

    function pushError(message, code) {
        const error = new Error(message);
        error.code = code;
        return error;
    }

    function isIos() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // iPadOS reports as a Mac
    }

    function isInstalledApp() {
        return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    }

    function isSupported() {
        return window.isSecureContext
            && 'serviceWorker' in navigator
            && 'PushManager' in window
            && 'Notification' in window;
    }

    function unsupportedMessage() {
        if (!window.isSecureContext) {
            return 'Push notifications need a secure connection. Open CampusTrips over HTTPS (or via localhost) to enable them.';
        }
        // iOS/iPadOS Safari only exposes Web Push to sites added to the Home Screen.
        if (isIos() && !isInstalledApp()) {
            return 'Push notifications are not supported in this browser on iPhone or iPad. '
                + 'Update to iOS 16.4 or later, tap Share → "Add to Home Screen", then open CampusTrips from your Home Screen to enable them.';
        }
        return 'Push notifications are not supported on your current browser. '
            + 'Please try the latest version of Google Chrome, Microsoft Edge, Mozilla Firefox or Safari.';
    }

    function urlBase64ToUint8Array(base64Url) {
        const padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
        const raw = atob((base64Url + padding).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(raw, (char) => char.charCodeAt(0));
    }

    async function postJson(path, body) {
        const response = await fetch(`${API}/${path}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body || {}),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.error || 'Something went wrong. Please try again.');
        }
        return data;
    }

    async function getSubscription() {
        const registration = await navigator.serviceWorker.ready;
        return registration.pushManager.getSubscription();
    }

    async function isEnabled() {
        if (!isSupported() || Notification.permission !== 'granted') {
            return false;
        }
        const subscription = await getSubscription();
        if (!subscription) {
            return false;
        }
        const status = await postJson('checkSubscription.php', { endpoint: subscription.endpoint });
        return status.active === true;
    }

    async function getState() {
        if (!isSupported()) {
            return 'unsupported';
        }
        if (Notification.permission === 'denied') {
            return 'denied';
        }
        return (await isEnabled()) ? 'enabled' : 'off';
    }

    // Once a site is blocked, browsers never show the permission prompt
    // again — the user has to undo it themselves, so tell them where.
    function blockedHelp() {
        const ua = navigator.userAgent;
        const reload = 'Reload this page, then turn on Trip Reminders again.';

        if (isIos()) {
            return {
                browser: 'iPhone / iPad',
                steps: ['Open the Settings app and tap Notifications.', 'Tap CampusTrips and turn on Allow Notifications.', 'Reopen CampusTrips from your Home Screen.'],
            };
        }
        if (/Edg\//.test(ua)) {
            return {
                browser: 'Microsoft Edge',
                steps: ['Click the padlock icon to the left of the web address.', 'Under "Permissions for this site", set Notifications to Allow.', reload],
            };
        }
        if (/Firefox\//.test(ua)) {
            return {
                browser: 'Mozilla Firefox',
                steps: ['Click the permissions icon to the left of the web address.', 'Next to "Send Notifications", click the × to clear the block.', reload],
            };
        }
        if (/Chrome\//.test(ua) && !/OPR\//.test(ua)) {
            return {
                browser: 'Google Chrome',
                steps: ['Click the site settings icon to the left of the web address.', 'Turn Notifications on (or set it to Allow).', reload],
            };
        }
        if (/Safari\//.test(ua)) {
            return {
                browser: 'Safari',
                steps: ['In the menu bar, choose Safari → Settings → Websites.', `Select Notifications, find ${location.host} and set it to Allow.`, reload],
            };
        }
        return {
            browser: 'your browser',
            steps: ['Open your browser\'s site settings for this website (usually via the icon to the left of the web address).', 'Set Notifications to Allow.', reload],
        };
    }

    async function enable() {
        if (!isSupported()) {
            throw pushError(unsupportedMessage(), 'unsupported');
        }

        const permission = await Notification.requestPermission();
        if (permission === 'denied') {
            throw pushError('Notifications are blocked for CampusTrips in this browser.', 'denied');
        }
        if (permission !== 'granted') {
            // Closed the browser prompt without choosing — it can be asked again later.
            throw pushError('Notifications were not turned on.', 'dismissed');
        }

        try {
            await subscribeThisDevice();
        } catch (err) {
            throw pushError(err.message, 'failed');
        }
    }

    async function subscribeThisDevice() {
        const keyResponse = await fetch(`${API}/vapidPublicKey.php`, { credentials: 'same-origin' });
        const keyData = await keyResponse.json().catch(() => ({}));
        if (!keyResponse.ok || !keyData.publicKey) {
            throw new Error(keyData.error || 'Push notifications are unavailable right now.');
        }
        const serverKey = urlBase64ToUint8Array(keyData.publicKey);

        const registration = await navigator.serviceWorker.ready;
        let subscription = await registration.pushManager.getSubscription();

        // A subscription made with a different server key (e.g. the keys were
        // regenerated) can't receive our pushes — replace it.
        if (subscription && subscription.options.applicationServerKey) {
            const existingKey = new Uint8Array(subscription.options.applicationServerKey);
            if (existingKey.length !== serverKey.length || existingKey.some((byte, i) => byte !== serverKey[i])) {
                await subscription.unsubscribe();
                subscription = null;
            }
        }

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: serverKey,
            });
        }

        await postJson('subscribe.php', subscription.toJSON());
    }

    async function disable() {
        if (!isSupported()) {
            return;
        }
        const subscription = await getSubscription();
        if (!subscription) {
            return;
        }
        await postJson('unsubscribe.php', { endpoint: subscription.endpoint });
        await subscription.unsubscribe();
    }

    function sendTest() {
        return postJson('sendTest.php');
    }

    function markPrompted() {
        return postJson('markPrompted.php').catch(() => {});
    }

    window.CampusPush = { isSupported, unsupportedMessage, getState, blockedHelp, isEnabled, enable, disable, sendTest, markPrompted };
})();
