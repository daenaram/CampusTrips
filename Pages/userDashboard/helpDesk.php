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
    <style>
        .helpdesk-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .helpdesk-hero {
           
            color: black;
            padding: 3rem 2rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 3rem;
            box-shadow: 0 4px 12px rgba(0, 120, 212, 0.2);
        }

        .helpdesk-hero h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2.5rem;
        }

        .helpdesk-hero p {
            margin: 0;
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .contact-card {
            background: white;
            border: 1px solid #e3e6ea;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            text-align: center;
        }

        .contact-card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .contact-card h3 {
            margin: 1rem 0 0.5rem 0;
            color: #222;
            font-size: 1.2rem;
        }

        .contact-card p {
            margin: 0;
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .contact-card a {
            color: #0078d4;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.75rem;
        }

        .contact-card a:hover {
            text-decoration: underline;
        }

        .contact-form-section {
            background: white;
            border: 1px solid #e3e6ea;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            max-width: 1200px;
            margin: 0 auto;
        }

        .contact-form-section h2 {
            margin: 0 0 1.5rem 0;
            color: #222;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0078d4;
            box-shadow: 0 0 0 3px rgba(0, 120, 212, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }

        .form-submit {
            background: #0078d4;
            color: white;
            border: none;
            padding: 0.85rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.2s;
            width: 100%;
        }

        .form-submit:hover {
            background: #005a9e;
        }

        .form-submit:active {
            transform: scale(0.98);
        }

        .message-alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .message-alert.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .message-alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .back-button {
            display: inline-block;
            margin-bottom: 2rem;
            padding: 0.5rem 1rem;
            background: #f0f0f0;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            color: #222;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }

        .back-button:hover {
            background: #e0e0e0;
        }

        @media (max-width: 768px) {
            .helpdesk-hero h1 {
                font-size: 1.8rem;
            }

            .helpdesk-hero p {
                font-size: 0.95rem;
            }

            .contact-form-section {
                padding: 1.5rem;
            }
        }
    </style>
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
            <div class="contact-card-icon">💬</div>
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
