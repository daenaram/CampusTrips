<?php
// Start session to access user data
session_start();
require_once __DIR__ . '/../../assets/api/config/database.php';
require_once __DIR__ . '/../../assets/api/helpers/currencyHelper.php';
require_once __DIR__ . '/../../assets/api/helpers/notificationPreferenceHelper.php';

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}

//load the current preference
$notificationPrefs = getNotificationPreferences($pdo, (int) $_SESSION['user_id']);
// Trips the user can attach a reminder to.
$stmt = $pdo->prepare("SELECT id, title FROM trips WHERE user_id = ? ORDER BY start_date DESC");
$stmt->execute([(int) $_SESSION['user_id']]);
$userTrips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Reminders the user has already created.
$stmt = $pdo->prepare("SELECT id, reminder_type, title, remind_on FROM user_reminders WHERE user_id = ? ORDER BY remind_on ASC");
$stmt->execute([(int) $_SESSION['user_id']]);
$userReminders = $stmt->fetchAll(PDO::FETCH_ASSOC);


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
    <link rel="stylesheet" href="../../assets/css/hamburgerMenu.css?v=2">
    <script src="/AUT-Web-Based-Travel-Planner/assets/js/notificationBell.js" defer></script>

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

        .reminder-form {
            display: grid;
            gap: 12px;
            margin-top: 10px;
        }

        .reminder-form-row {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .reminder-form-row label {
            font-size: 13px;
            color: #555;
        }

        .reminder-list {
            list-style: none;
            margin: 15px 0 0;
            padding: 0;
        }

        .reminder-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #e5e5e5;
        }

        .reminder-item:last-child {
            border-bottom: none;
        }

        .reminder-item em {
            color: #0072ac;
            font-style: normal;
            font-size: 13px;
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
    <button type="button" class="back-to-dashboard" onclick="location.href='Dashboard.php'"
        aria-label="Back to dashboard">← Back to Dashboard</button>

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
                <button type="button"
                    onclick="location.href='/AUT-Web-Based-Travel-Planner/assets/api/auth/signout.php'">
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
            <p>Choose which reminders you want to receive. Changes take effect the next time a reminder is due.</p>

            <div class="settings-option">
                <div class="settings-option-text">
                    <strong>Push notifications on this device</strong>
                    <span>Allow CampusTrips to send push notifications to this browser — even when the app isn't
                        open.</span>
                    <div class="push-status" id="pushStatus" role="status" aria-live="polite">Checking notification
                        status…</div>
                    <p class="push-feedback" id="pushFeedback" role="status" aria-live="polite" hidden></p>
                    <button type="button" class="settings-button secondary push-test" id="pushTest" hidden>Send test
                        notification</button>
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

            <h3 class="settings-subheading">Reminder types</h3>
            <p class="settings-subheading-hint">
                Turn off any reminder you don't want. Turning everything off keeps the rest of the app working exactly
                as normal.
            </p>

            <div class="settings-option">
                <div class="settings-option-text">
                    <strong>All notifications</strong>
                    <span>Master switch — turns every reminder type below on or off at once.</span>
                </div>
                <input type="checkbox" id="notifyAll" <?php echo $notificationPrefs['all'] ? 'checked' : ''; ?>>
            </div>

            <?php foreach (REMINDER_TYPES as $type => $def): ?>
                <div class="settings-option">
                    <div class="settings-option-text">
                        <strong><?php echo htmlspecialchars($def['label']); ?></strong>
                        <span><?php echo htmlspecialchars($def['description']); ?></span>
                    </div>
                    <input type="checkbox" class="notify-type" id="notify_<?php echo htmlspecialchars($type); ?>"
                        data-type="<?php echo htmlspecialchars($type); ?>" <?php echo !empty($notificationPrefs[$type]) ? 'checked' : ''; ?>>
                </div>

            <?php endforeach; ?>

            <h3 class="settings-subheading">Create a reminder</h3>
            <p class="settings-subheading-hint">
                Add your own visa deadline or custom reminder. You'll be reminded the day before and on the day.
            </p>

            <div class="reminder-form" id="reminderForm">
                <div class="reminder-form-row">
                    <label for="reminderType">Type</label>
                    <select id="reminderType" class="settings-select">
                        <option value="visa">Visa deadline</option>
                        <option value="custom">Custom reminder</option>
                    </select>
                </div>

                <div class="reminder-form-row">
                    <label for="reminderTitle">Title</label>
                    <input type="text" id="reminderTitle" class="settings-select" maxlength="150"
                        placeholder="e.g. Japan visa application">
                </div>

                <div class="reminder-form-row">
                    <label for="reminderDate">Remind me on</label>
                    <input type="date" id="reminderDate" class="settings-select" value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="reminder-form-row">
                    <label for="reminderTrip">Trip (optional)</label>
                    <select id="reminderTrip" class="settings-select">
                        <option value="">No trip</option>
                        <?php foreach ($userTrips as $trip): ?>
                            <option value="<?php echo (int) $trip['id']; ?>">
                                <?php echo htmlspecialchars($trip['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="reminder-form-row">
                    <label for="reminderBody">Notes (optional)</label>
                    <input type="text" id="reminderBody" class="settings-select" maxlength="500">
                </div>

                <button type="button" class="settings-button primary" id="addReminder">Add reminder</button>
                <p class="push-feedback" id="reminderFeedback" role="status" aria-live="polite" hidden></p>
            </div>

            <!-- The reminders this user has already created. -->
            <?php if (!empty($userReminders)): ?>
                <ul class="reminder-list">
                    <?php foreach ($userReminders as $r): ?>
                        <li class="reminder-item">
                            <span>
                                <strong><?php echo htmlspecialchars($r['title']); ?></strong>
                                <em><?php echo $r['reminder_type'] === 'visa' ? 'Visa deadline' : 'Custom'; ?></em>
                                · <?php echo htmlspecialchars($r['remind_on']); ?>
                            </span>
                            <button type="button" class="settings-button secondary reminder-delete"
                                data-id="<?php echo (int) $r['id']; ?>">Delete</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
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

        // ---------- Notification type toggles ----------
        const notifyAll = document.getElementById('notifyAll');
        const notifyTypes = Array.from(document.querySelectorAll('.notify-type'));

        // The master switch is on only while at least one type is on.
        function syncMasterToggle() {
            if (!notifyAll) return;
            notifyAll.checked = notifyTypes.some(function (cb) { return cb.checked; });
        }

        if (notifyAll) {
            // Turning the master switch changes every type below it.
            notifyAll.addEventListener('change', function () {
                notifyTypes.forEach(function (cb) { cb.checked = notifyAll.checked; });
            });

            // Changing a type updates the master switch.
            notifyTypes.forEach(function (cb) {
                cb.addEventListener('change', syncMasterToggle);
            });

            syncMasterToggle();
        }

        // Collects the current checkbox states into one object.
        function collectNotificationPrefs() {
            const prefs = { all: notifyAll ? notifyAll.checked : true };
            notifyTypes.forEach(function (cb) { prefs[cb.dataset.type] = cb.checked; });
            return prefs;
        }

        if (saveSettings) {
            saveSettings.addEventListener('click', function () {
                const formData = new FormData();
                formData.append('currency', document.getElementById('currencySetting').value);
                formData.append('notifications', JSON.stringify(collectNotificationPrefs()));

                saveSettings.disabled = true;
                fetch('save_settings.php', { method: 'POST', body: formData })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.status === 'success') {
                            alert('Settings saved.');
                            window.location.reload();
                        } else {
                            alert(data.message || 'Could not save settings.');
                        }
                    })
                    .catch(function () { alert('Error saving settings.'); })
                    .finally(function () { saveSettings.disabled = false; });
            });
        }

        if (resetSettings) {
            resetSettings.addEventListener('click', function () {
                document.getElementById('languageSetting').value = 'en';
                document.getElementById('currencySetting').value = 'NZD';
                document.getElementById('travelTypeSetting').value = '';
                document.getElementById('accommodationSetting').value = '';
                document.getElementById('travelUpdates').checked = true;

                // Turn every reminder back on.
                if (notifyAll) {
                    notifyAll.checked = true;
                    notifyTypes.forEach(function (cb) { cb.checked = true; });
                }
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
        document.getElementById('saveSettings').addEventListener('click', function () {
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
                    if (data.status === 'success') {
                        alert('Settings saved! Currency set to ' + selectedCurrency);
                        window.location.reload(); // Reload to apply changes globally
                    }
                })
                .catch(err => alert('Error saving settings.'));
        });
        // ---------- Create a reminder ----------
        const addReminder = document.getElementById('addReminder');
        const reminderFeedback = document.getElementById('reminderFeedback');

        function showReminderFeedback(message, isError) {
            reminderFeedback.textContent = message;
            reminderFeedback.classList.toggle('error', Boolean(isError));
            reminderFeedback.hidden = !message;
        }

        if (addReminder) {
            addReminder.addEventListener('click', function () {
                const formData = new FormData();
                formData.append('action', 'create');
                formData.append('reminder_type', document.getElementById('reminderType').value);
                formData.append('title', document.getElementById('reminderTitle').value);
                formData.append('remind_on', document.getElementById('reminderDate').value);
                formData.append('trip_id', document.getElementById('reminderTrip').value);
                formData.append('body', document.getElementById('reminderBody').value);

                addReminder.disabled = true;
                fetch('save_reminder.php', { method: 'POST', body: formData })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.status === 'success') {
                            window.location.reload(); // Show the new reminder.
                        } else {
                            showReminderFeedback(data.message || 'Could not add reminder.', true);
                        }
                    })
                    .catch(function () { showReminderFeedback('Error adding reminder.', true); })
                    .finally(function () { addReminder.disabled = false; });
            });
        }

        // ---------- Delete a reminder ----------
        document.querySelectorAll('.reminder-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete this reminder?')) return;

                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', btn.dataset.id);

                fetch('save_reminder.php', { method: 'POST', body: formData })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.status === 'success') {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Could not delete reminder.');
                        }
                    })
                    .catch(function () { alert('Error deleting reminder.'); });
            });
        });



    </script>
</body>

</html>