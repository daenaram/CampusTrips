<?php
// Start session to access user data
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}
?>

<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CampusTrips</title>

    <!-- Hamburger menu CSS -->
<link rel="stylesheet" href="../../assets/css/hamburgerMenu.css">

<!-- Add your existing settings CSS here if you have one -->
<link rel="stylesheet" href="../../assets/css/settingsbutton.css">

<style>
    {
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
<!-- Hamburger menu icon -->
<button
    class="menu-toggle"
    id="menuToggle"
    aria-label="Open menu"
    aria-expanded="false"
    aria-controls="menuPanel">

    <span class="bar"></span>
    <span class="bar"></span>
    <span class="bar"></span>
</button>


<!-- Dark backdrop behind menu -->
<div class="menu-backdrop" id="menuBackdrop"></div>


<!-- Side menu -->
<nav
    class="menu-panel"
    id="menuPanel"
    aria-hidden="true">

    <div class="menu-panel-header">

        <?php if (isset($_SESSION['name'])): ?>

            <p>
                Hi,
                <?php echo htmlspecialchars($_SESSION['name']); ?>
            </p>

        <?php else: ?>

            <p>Menu</p>

        <?php endif; ?>

    </div>


    <ul class="menu-list">

        <li>
            <button type="button" onclick="location.href='Dashboard.php'">
                ← Back to Dashboard
            </button>
        </li>

        <!-- User Profile -->
        <li>
            <button
                type="button"
                onclick="location.href='userProfile.php'">

                User Profile

            </button>
        </li>


        <!-- Settings -->
        <li>
            <button
                type="button"
                onclick="location.href='settings.php'">

                Settings

            </button>
        </li>


        <!-- Sign Out -->
        <li>
            <button
                type="button"
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

            <p>
                Manage your CampusTrips preferences,
                <?php echo htmlspecialchars($_SESSION['name']); ?>.
            </p>

        <?php else: ?>

            <p>
                Manage your CampusTrips preferences.
            </p>

        <?php endif; ?>

    </div>


    <!-- General Settings -->
    <section class="settings-card">

        <h2>General Settings</h2>

        <p>
            Manage your general travel planner preferences.
        </p>


        <div class="settings-option">

            <div class="settings-option-text">

                <strong>Language</strong>

                <span>
                    Select the language used throughout the application.
                </span>

            </div>

            <select
                class="settings-select"
                id="languageSetting">

                <option value="en">
                    English
                </option>

            </select>

        </div>


        <div class="settings-option">

            <div class="settings-option-text">

                <strong>Currency</strong>

                <span>
                    Choose the currency used when displaying travel costs.
                </span>

            </div>

            <select
                class="settings-select"
                id="currencySetting">

                <option value="NZD">
                    NZD - New Zealand Dollar
                </option>

            </select>

        </div>

    </section>


    <!-- Travel Preferences -->
    <section class="settings-card">

        <h2>Travel Preferences</h2>

        <p>
            Set your preferred travel options.
        </p>


        <div class="settings-option">

            <div class="settings-option-text">

                <strong>Preferred Travel Type</strong>

                <span>
                    Choose your preferred type of travel.
                </span>

            </div>

            <select
                class="settings-select"
                id="travelTypeSetting">

                <option value="">
                    Select an option
                </option>

                <option value="budget">
                    Budget
                </option>

                <option value="standard">
                    Standard
                </option>

                <option value="luxury">
                    Luxury
                </option>

            </select>

        </div>


        <div class="settings-option">

            <div class="settings-option-text">

                <strong>Preferred Accommodation</strong>

                <span>
                    Choose your preferred accommodation type.
                </span>

            </div>

            <select
                class="settings-select"
                id="accommodationSetting">

                <option value="">
                    Select an option
                </option>

                <option value="hotel">
                    Hotel
                </option>

                <option value="hostel">
                    Hostel
                </option>

                <option value="apartment">
                    Apartment
                </option>

            </select>

        </div>

    </section>


    <!-- Notifications -->
    <section class="settings-card">

        <h2>Notifications</h2>

        <p>
            Manage your travel planner notifications.
        </p>


        <div class="settings-option">

            <div class="settings-option-text">

                <strong>Trip Reminders</strong>

                <span>
                    Receive reminders about upcoming trips and itinerary items.
                </span>

            </div>

            <input
                type="checkbox"
                id="tripReminders"
                checked>

        </div>


        <div class="settings-option">

            <div class="settings-option-text">

                <strong>Travel Updates</strong>

                <span>
                    Receive important updates relating to your travel plans.
                </span>

            </div>

            <input
                type="checkbox"
                id="travelUpdates"
                checked>

        </div>

    </section>


    <!-- Save Settings -->
    <section class="settings-card">

        <button
            type="button"
            class="settings-button primary"
            id="saveSettings">

            Save Settings

        </button>

        <button
            type="button"
            class="settings-button secondary"
            id="resetSettings">

            Reset

        </button>

    </section>

</main>


<!-- =========================================================
     HAMBURGER MENU JAVASCRIPT
     Same behaviour as the dashboard
     ========================================================= -->

<script>

    // ---------- Hamburger menu behaviour ----------

    const menuToggle = document.getElementById('menuToggle');

    const menuPanel = document.getElementById('menuPanel');

    const menuBackdrop = document.getElementById('menuBackdrop');


    // Open hamburger menu
    function openMenu() {

        menuToggle.classList.add('open');

        menuToggle.setAttribute(
            'aria-expanded',
            'true'
        );

        menuToggle.setAttribute(
            'aria-label',
            'Close menu'
        );


        menuPanel.classList.add('open');

        menuPanel.setAttribute(
            'aria-hidden',
            'false'
        );


        menuBackdrop.classList.add('visible');

    }


    // Close hamburger menu
    function closeMenu() {

        menuToggle.classList.remove('open');

        menuToggle.setAttribute(
            'aria-expanded',
            'false'
        );

        menuToggle.setAttribute(
            'aria-label',
            'Open menu'
        );


        menuPanel.classList.remove('open');

        menuPanel.setAttribute(
            'aria-hidden',
            'true'
        );


        menuBackdrop.classList.remove('visible');

    }
    // Toggle menu when hamburger is clicked
    menuToggle.addEventListener(
        'click',
        function () {

            menuPanel.classList.contains('open')
                ? closeMenu()
                : openMenu();

        }
    );
    // Close menu when backdrop is clicked
    menuBackdrop.addEventListener(
        'click',
        closeMenu
    );
    // Close menu when Escape is pressed
    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Escape') {

                closeMenu();

            }

        }
    );
    // ---------- Settings buttons ----------
    const saveSettings =
        document.getElementById('saveSettings');
    const resetSettings =
        document.getElementById('resetSettings');
    if (saveSettings) {

        saveSettings.addEventListener(
            'click',
            function () {

                alert('Settings saved.');

            }
        );
    }
    if (resetSettings) {

        resetSettings.addEventListener(
            'click',
            function () {
                document.getElementById('languageSetting').value = 'en';
                document.getElementById('currencySetting').value = 'NZD';
                document.getElementById('travelTypeSetting').value = '';
                document.getElementById('accommodationSetting').value = '';
                document.getElementById('tripReminders').checked = true;
                document.getElementById('travelUpdates').checked = true;
            }
        );
    }

</script>
</body>
</html>
