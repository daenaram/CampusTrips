<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}

$message = '';
$messageType = '';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message_content = trim($_POST['message'] ?? '');

    if (!empty($name) && !empty($email) && !empty($subject) && !empty($message_content)) {
        // In a real application, you would send an email here
        $message = "Thank you for contacting us! We'll get back to you soon.";
        $messageType = 'success';
    } else {
        $message = "Please fill in all fields.";
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - CampusTrips</title>
    <link rel="stylesheet" href="../../assets/css/settingsbutton.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/hamburgerMenu.css">
    <link rel="stylesheet" href="../../assets/css/helpDesk.css">
</head>
<body>

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
            <button type="button" onclick="location.href='userProfile.php'">
                User Profile
            </button>
        </li>
        <li>
            <button type="button" onclick="location.href='settings.php'">
                Settings
            </button>
        </li>
        <li>
            <button type="button" onclick="location.href='/AUT-Web-Based-Travel-Planner/assets/api/auth/signout.php'">
                Sign Out
            </button>
        </li>
    </ul>
</nav>

<div class="helpdesk-container">
    <a href="Dashboard.php" class="back-button">← Back to Dashboard</a>

    <div class="helpdesk-hero">
        <h1>Contact Us</h1>
        <p>We're here to help! Get in touch with our support team</p>
    </div>

    <div class="contact-grid">
        <div class="contact-card">
            
            <h3>Email Support</h3>
            <p>Get in touch via email and we'll respond within 24 hours</p>
            <a href="mailto:support@campustrips.co.nz">support@campustrips.co.nz</a>
        </div>

        <div class="contact-card">
            
            <h3>Phone Support</h3>
            <p>Call our support team Monday to Friday, 9AM - 5PM NZST</p>
            <a href="tel:+64-9-921-8765">+64 9 921 8765</a>
        </div>

        <div class="contact-card">
            
            <h3>Live Chat</h3>
            <p>Chat with our support team in real-time (Limited hours)</p>
            <a href="#" onclick="alert('Live chat is currently unavailable'); return false;">Start Chat</a>
        </div>
    </div>

    <div class="contact-form-section">
        <h2>Send us a Message</h2>

        <?php if (!empty($message)): ?>
            <div class="message-alert <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" required placeholder="Your name">
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" required placeholder="your.email@example.com">
            </div>

            <div class="form-group">
                <label for="subject">Subject *</label>
                <input type="text" id="subject" name="subject" required placeholder="What is this about?">
            </div>

            <div class="form-group">
                <label for="message">Message *</label>
                <textarea id="message" name="message" required placeholder="Tell us how we can help..."></textarea>
            </div>

            <button type="submit" name="send_message" class="form-submit">Send Message</button>
        </form>
    </div>
</div>

<script>
    // ---------- Hamburger menu behaviour ----------
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
</script>

</body>
</html>
