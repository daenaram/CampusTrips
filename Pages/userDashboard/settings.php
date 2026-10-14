<?php
// Start session to access user data
session_start();
require_once __DIR__ . '/../../assets/api/config/database.php';
require_once __DIR__ . '../../../assets/api/helpers/currencyHelper.php';


// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="/AUT-Web-Based-Travel-Planner/assets/js/pwa.js"></script>
    <script src="/AUT-Web-Based-Travel-Planner/assets/js/pushNotifications.js?v=2"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CampusTrips</title>

    <!-- Hamburger menu CSS -->
    <link rel="stylesheet" href="../../assets/css/hamburgerMenu.css">

    <!-- Settings button CSS -->
    <link rel="stylesheet" href="../../assets/css/settingsbutton.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        .settings-page {
            max-width: 1000px;
            margin: 0 auto;
            padding: 100px 30px 50px;
        }

        .settings-header {
            margin-bottom: 30px;
        }

        .settings-header h1 {
            margin: 0 0 8px;
            font-size: 32px;
        }

        .settings-header p {
            margin: 0;
            color: #666;
        }

        .settings-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .settings-card h2 {
            margin-top: 0;
            margin-bottom: 10px;
        }

        .settings-card p {
            color: #666;
        }

        .settings-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 18px 0;
            border-bottom: 1px solid #e5e5e5;
        }

        .settings-option:last-child {
            border-bottom: none;
        }

        .settings-option-text {
            flex: 1;
        }

        .settings-option-text strong {
            display: block;
            margin-bottom: 5px;
        }

        .settings-option-text span {
            color: #777;
            font-size: 14px;
        }

        .settings-select {
            min-width: 180px;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: white;
            font-size: 14px;
        }

        .settings-button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .settings-button.primary {
            background: #0072ac;
            color: white;
        }

        .settings-button.secondary {
            background: #e9e9e9;
            color: #333;
        }

        .push-status {
            margin: 10px 0 0;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 14px;
            background: #e8f4fb;
            color: #0b4f75;
        }

        .push-status.active {
            background: #e7f6ec;
            color: #1d6b38;
            font-weight: bold;
        }

        .push-status.active::before {
            content: '';
            display: inline-block;
            width: 9px;
            height: 9px;
            margin-right: 8px;
            border-radius: 50%;
            background: #22a04b;
        }

        .push-status.off {
            background: #f1f1f1;
            color: #555;
        }

        .push-status.blocked {
            background: #fff6e0;
            color: #6b4e00;
        }

        .push-status p {
            margin: 0;
            color: inherit;
        }

        .push-status ol {
            margin: 8px 0 0;
            padding-left: 20px;
        }

        .push-status li {
            margin-top: 4px;
        }

        .push-feedback {
            margin: 8px 0 0;
            font-size: 14px;
            color: #0b4f75;
        }

        .push-feedback.error {
            color: #8a1c13;
        }

        .push-test {
            margin-top: 10px;
        }

        @media (max-width: 600px) {
            .settings-page {
                padding: 90px 15px 30px;
            }

            .settings-option {
                flex-direction: column;
                align-items: flex-start;
            }

            .settings-select {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <!-- Back to dashboard (top left) -->
    <button type="button" class="back-to-dashboard" onclick="location.href='Dashboard.php'" aria-label="Back to dashboard">← Back to Dashboard</button>

    <!-- Hamburger menu icon (top right) -->
    <button class="menu-toggle" id="menuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="menuPanel">
        <span class="bar"></span>
        <span class="bar"></span>
        <span class="bar"></span>
    </button>

    <div class="menu-backdrop" id="menuBackdrop"></div>

    <nav class="menu-panel" id="menuPanel" aria-hidden="true">
        <div class="menu-panel-header">
            <?php if (isset($_SESSION['name'])): ?>
                <p>Hi, <?php echo htmlspecialchars($_SESSION['name']); ?></p>
            <?php else: ?>
                <p>Menu</p>
            <?php endif; ?>
        </div>

        <ul class="menu-list">
            <li>
                <button type="button" onclick="location.href='Dashboard.php'">
                    Dashboard
                </button>
            </li>
            <li>
                <button type="button" onclick="location.href='budget.php'">
                    Budget
                </button>
            </li>
            <li>
                <button type="button" onclick="location.href='userProfile.php'">
                    User Profile
                </button>
            </li>
            <li>
                <button type="button" onclick="location.href='helpDesk.php'">
                    Contact us
                </button>
            </li>
            <li>
                <button type="button" onclick="location.href='/AUT-Web-Based-Travel-Planner/assets/api/auth/signout.php'">
                    Sign Out
                </button>
            </li>
        </ul>
    </nav>

    <!-- SETTINGS PAGE CONTENT -->
    <main class="settings-page">

        <div class="settings-header">
            <h1>Settings</h1>

            <?php if (isset($_SESSION['name'])): ?>
                <p>Manage your CampusTrips preferences, <?php echo htmlspecialchars($_SESSION['name']); ?>.</p>
            <?php else: ?>
                <p>Manage your CampusTrips preferences.</p>
            <?php endif; ?>
        </div>

        <!-- General Settings -->
        <section class="settings-card">
            <h2>General Settings</h2>
            <p>Manage your general travel planner preferences.</p>

            <div class="settings-option">
                <div class="settings-option-text">
                    <strong>Language</strong>
                    <span>Select the language used throughout the application.</span>
                </div>
                <select class="settings-select" id="languageSetting">
                    <option value="en">English</option>
                </select>
            </div>

            <div class="settings-option">
    <div class="settings-option-text">
        <strong>Currency</strong>
        <span>Choose the currency used when displaying travel costs.</span>
    </div>
    <select class="settings-select" id="currencySetting">
        <?php
                    // Get the list from the helper
                    $currencies = getSupportedCurrencies();
                    // Get user's current choice from session (default to NZD)
                    $current = $_SESSION['currency'] ?? 'NZD';

                    foreach ($currencies as $code) {
                        $selected = ($code === $current) ? 'selected' : '';
                        echo "<option value=\"$code\" $selected>$code</option>";
                    }
                    ?>
                </select>
            </div>
        </section>

        <!-- Travel Preferences -->
        <section class="settings-card">
            <h2>Travel Preferences</h2>
            <p>Set your preferred travel options.</p>

            <div class="settings-option">
                <div class="settings-option-text">
                    <strong>Preferred Travel Type</strong>
                    <span>Choose your preferred type of travel.</span>
                </div>
                <select class="settings-select" id="travelTypeSetting">
                    <option value="">Select an option</option>
                    <option value="budget">Budget</option>
                    <option value="standard">Standard</option>
                    <option value="luxury">Luxury</option>
                </select>
            </div>

            <div class="settings-option">
                <div class="settings-option-text">
                    <strong>Preferred Accommodation</strong>
                    <span>Choose your preferred accommodation type.</span>
                </div>
                <select class="settings-select" id="accommodationSetting">
                    <option value="">Select an option</option>
                    <option value="hotel">Hotel</option>
                    <option value="hostel">Hostel</option>
                    <option value="apartment">Apartment</option>
                </select>
            </div>
        </section>

        <!-- Notifications -->
        <section class="settings-card">
            <h2>Notifications</h2>
            <p>Manage your travel planner notifications.</p>

            <div class="settings-option">
                <div class="settings-option-text">
                    <strong>Trip Reminders</strong>
                    <span>Get push notifications on this device about upcoming trips and activities — even when CampusTrips isn't open.</span>
                    <div class="push-status" id="pushStatus" role="status" aria-live="polite">Checking notification status…</div>
                    <p class="push-feedback" id="pushFeedback" role="status" aria-live="polite" hidden></p>
                    <button type="button" class="settings-button secondary push-test" id="pushTest" hidden>Send test notification</button>
                </div>
                <input type="checkbox" id="tripReminders" aria-describedby="pushStatus">
            </div>

            <div class="settings-option">
                <div class="settings-option-text">
                    <strong>Travel Updates</strong>
                    <span>Receive important updates relating to your travel plans.</span>
                </div>
                <input type="checkbox" id="travelUpdates" checked>
            </div>

            <!-- TODO (notification preferences): add the options for which
                 reminders a user wants (e.g. trips, activities, flights,
                 accommodation, custom reminders) here. The server-side check
                 that skips unwanted reminders goes in sendDueReminders() in
                 assets/api/helpers/reminderHelper.php. -->
        </section>

        <!-- Save Settings -->
        <section class="settings-card">
            <button type="button" class="settings-button primary" id="saveSettings">Save Settings</button>
            <button type="button" class="settings-button secondary" id="resetSettings">Reset</button>
        </section>

    </main>

    <script>
        // ---------- Hamburger menu behaviour (same as userProfile) ----------
        const menuToggle = document.getElementById('menuToggle');
        const menuPanel = document.getElementById('menuPanel');
        const menuBackdrop = document.getElementById('menuBackdrop');

        function openMenu() {
            menuToggle.classList.add('open');
            menuToggle.setAttribute('aria-expanded', 'true');
            menuToggle.setAttribute('aria-label', 'Close menu');
            menuPanel.classList.add('open');
            menuPanel.setAttribute('aria-hidden', 'false');
            menuBackdrop.classList.add('visible');
        }

        function closeMenu() {
            menuToggle.classList.remove('open');
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-label', 'Open menu');
            menuPanel.classList.remove('open');
            menuPanel.setAttribute('aria-hidden', 'true');
            menuBackdrop.classList.remove('visible');
        }

        menuToggle.addEventListener('click', function () {
            menuPanel.classList.contains('open') ? closeMenu() : openMenu();
        });

        menuBackdrop.addEventListener('click', closeMenu);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });

        // ---------- Settings buttons ----------
        const saveSettings = document.getElementById('saveSettings');
        const resetSettings = document.getElementById('resetSettings');

        if (saveSettings) {
            saveSettings.addEventListener('click', function () {
                alert('Settings saved.');
            });
        }

        if (resetSettings) {
            resetSettings.addEventListener('click', function () {
                document.getElementById('languageSetting').value = 'en';
                document.getElementById('currencySetting').value = 'NZD';
                document.getElementById('travelTypeSetting').value = '';
                document.getElementById('accommodationSetting').value = '';
                document.getElementById('travelUpdates').checked = true;
            });
        }

        // ---------- Push notifications (Trip Reminders) ----------
        // Takes effect immediately rather than on "Save Settings", since it
        // needs the browser's permission prompt and is per-device.
        const tripReminders = document.getElementById('tripReminders');
        const pushStatus = document.getElementById('pushStatus');
        const pushFeedback = document.getElementById('pushFeedback');
        const pushTest = document.getElementById('pushTest');

        function addParagraph(parent, text) {
            const p = document.createElement('p');
            p.textContent = text;
            parent.appendChild(p);
        }

        // Shows this browser/device's push state: 'enabled', 'off',
        // 'denied' (with steps to undo the block) or 'unsupported'.
        // `lead` is an optional sentence shown above the state.
        function renderPushState(state, lead) {
            pushStatus.className = 'push-status';
            pushStatus.textContent = '';
            if (lead) {
                addParagraph(pushStatus, lead);
            }

            tripReminders.checked = state === 'enabled';
            tripReminders.disabled = state === 'denied';
            pushTest.hidden = state !== 'enabled';

            if (state === 'enabled') {
                pushStatus.classList.add('active');
                addParagraph(pushStatus, 'Push notifications are active on this browser and device.');
            } else if (state === 'denied') {
                const help = CampusPush.blockedHelp();
                pushStatus.classList.add('blocked');
                addParagraph(pushStatus, 'Notifications are blocked for CampusTrips in ' + help.browser + '. To turn them back on:');
                const steps = document.createElement('ol');
                help.steps.forEach(function (step) {
                    const item = document.createElement('li');
                    item.textContent = step;
                    steps.appendChild(item);
                });
                pushStatus.appendChild(steps);
            } else if (state === 'unsupported') {
                pushStatus.classList.add('blocked');
                addParagraph(pushStatus, CampusPush.unsupportedMessage());
            } else {
                pushStatus.classList.add('off');
                addParagraph(pushStatus, 'Push notifications are off on this browser and device.');
            }
        }

        function showPushFeedback(message, isError) {
            pushFeedback.textContent = message;
            pushFeedback.classList.toggle('error', Boolean(isError));
            pushFeedback.hidden = !message;
        }

        CampusPush.getState()
            .then(function (state) { renderPushState(state); })
            .catch(function () { renderPushState('off'); });

        tripReminders.addEventListener('change', function () {
            const wantsEnabled = tripReminders.checked;
            showPushFeedback('');

            if (wantsEnabled && !CampusPush.isSupported()) {
                renderPushState('unsupported');
                return;
            }

            tripReminders.disabled = true;
            pushStatus.className = 'push-status';
            pushStatus.textContent = wantsEnabled ? 'Enabling push notifications…' : 'Turning off push notifications…';

            (wantsEnabled ? CampusPush.enable() : CampusPush.disable())
                .then(function () {
                    renderPushState(wantsEnabled ? 'enabled' : 'off');
                })
                .catch(function (err) {
                    if (err.code === 'denied') {
                        renderPushState('denied', 'No problem — CampusTrips works fine without notifications.');
                    } else if (err.code === 'dismissed') {
                        renderPushState('off');
                    } else {
                        renderPushState(wantsEnabled ? 'off' : 'enabled');
                        showPushFeedback(err.message, true);
                    }
                });
        });

        pushTest.addEventListener('click', function () {
            pushTest.disabled = true;
            CampusPush.sendTest()
                .then(function () {
                    showPushFeedback('Test notification sent. Click it to open your next trip.');
                })
                .catch(function (err) {
                    showPushFeedback(err.message, true);
                })
                .finally(function () {
                    pushTest.disabled = false;
                });
        });
        document.getElementById('saveSettings').addEventListener('click', function() {
    const selectedCurrency = document.getElementById('currencySetting').value;

    // Create a request to save the currency to the session
    const formData = new FormData();
    formData.append('currency', selectedCurrency);

    fetch('save_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            alert('Settings saved! Currency set to ' + selectedCurrency);
            window.location.reload(); // Reload to apply changes globally
        }
    })
    .catch(err => alert('Error saving settings.'));
});


        
    </script>
</body>
</html>