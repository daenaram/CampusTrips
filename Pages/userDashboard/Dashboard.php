<?php
// Start session to access user data from login
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}

require_once __DIR__ . '/../../assets/api/config/database.php';

$errors = [];
$showModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_trip'])) {
    $title = trim($_POST['title'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $group_size = intval($_POST['group_size'] ?? 0);

    if ($title === '') {
        $errors[] = 'Please enter a title for the trip.';
    }
    if ($destination === '') {
        $errors[] = 'Please enter a destination.';
    }
    if ($start_date === '') {
        $errors[] = 'Please enter a start date.';
    }
    if ($end_date === '') {
        $errors[] = 'Please enter an end date.';
    }
    if ($start_date !== '' && $end_date !== '' && strtotime($start_date) > strtotime($end_date)) {
        $errors[] = 'End date must be the same as or after the start date.';
    }
    if ($group_size < 1) {
        $errors[] = 'Please enter a valid group size.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO trips (user_id, title, destination, start_date, end_date, group_size, travel_style) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $destination, $start_date, $end_date, $group_size, '']);
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();
        } catch (PDOException $e) {
            error_log('Trip save error: ' . $e->getMessage());
            $errors[] = 'Unable to save the trip right now. Please try again later.';
        }
    }

    if (!empty($errors)) {
        $showModal = true;
    }
}

$trips = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM trips WHERE user_id = ? ORDER BY start_date ASC");
    $stmt->execute([$_SESSION['user_id']]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Trip load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/settingsbutton.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
</head>
<body>

<div class="dashboard-hero">
    <div class="hero-overlay">    
        <h1>CampusTrips</h1>
        <h2>AUT Web-Based Travel Planner</h2>
        <?php if (isset($_SESSION['name'])): ?>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! Here you can manage your travel plans, view your itinerary, and access exclusive travel deals.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Search bar prototype -->

<div class="search-container-dashBoard">
    <div class="search-tabs">
        <button class="tab-btn active" onclick="showSearchTab('flights', this)">Flights</button>
        <button class="tab-btn" onclick="showSearchTab('accommodation', this)">Accommodation</button>
        <button class="tab-btn" onclick="showSearchTab('activities', this)">Activities</button>
    </div>

    <form method="POST" action="/AUT-Web-Based-Travel-Planner/Pages/userDashboard/searchBoard.php" class="search-panel active-panel" id="flights">
        <input type="hidden" name="search_type" value="flights">
        <input type="text" name="departure_city" placeholder="Starting Location...">
        <input type="text" name="arrival_city" placeholder="Destination...">
        <select name="airline" id="airline">
            <option value="">Any Airline</option>
            <option value="Air New Zealand">Air New Zealand</option>
            <option value="Qantas">Qantas</option>
            <option value="Jetstar">Jetstar</option>
            <option value="Emirates">Emirates</option>
            <option value="Singapore Airlines">Singapore Airlines</option>
        </select>
        <input type="date" name="departure_date">
        <input type="date" name="return_date">
        <button type="submit" class="search-btn">Search</button>
    </form>

    <form method="POST" action="/AUT-Web-Based-Travel-Planner/Pages/userDashboard/searchBoard.php" class="search-panel" id="accommodation">
        <input type="hidden" name="search_type" value="accommodation">
        <input type="text" name="accommodation_name" placeholder="Search accommodation...">
        <input type="text" name="accommodation_type" placeholder="Accommodation type...">
        <input type="text" name="accommodation_city" placeholder="City...">
        <button type="submit" class="search-btn">Search</button>
    </form>

    <form method="POST" action="/AUT-Web-Based-Travel-Planner/Pages/userDashboard/searchBoard.php" class="search-panel" id="activities">
        <input type="hidden" name="search_type" value="activities">
        <input type="text" name="keyword" placeholder="Search activities...">
        <input type="text" name="city" placeholder="City/Country">
        <input type="text" name="category" placeholder="Category">
        <input type="date" name="activity_date">
        <button type="submit" class="search-btn">Search</button>
    </form>

</div>

<!--  -->

<!-- <a class="top-right-button" href="/AUT-Web-Based-Travel-Planner/assets/api/auth/signout.php">Sign Out</a>
<p><a href="userProfile.php">View User Profile</a></p> -->

<div class="top-right-actions">
    <button class="profile-btn" onclick="location.href='userProfile.php'">
        <div class="mini-avatar"></div>
    </button>

    <button class="signout-btn" onclick="location.href='/AUT-Web-Based-Travel-Planner/assets/api/auth/signout.php'">
        Sign Out
    </button>
</div>

<!-- Search function JS -->
 <script>
    function showSearchTab(tabId, clickedButton) {
        const panels = document.querySelectorAll('.search-panel');
        const buttons = document.querySelectorAll('.tab-btn');

        panels.forEach(panel => {
            panel.classList.remove('active-panel');
        });

        buttons.forEach(button => {
            button.classList.remove('active');
        });

        document.getElementById(tabId).classList.add('active-panel');
        clickedButton.classList.add('active');
    }
 </script>
 
<!--  -->

 <div class="savedTrips">
    <h2>Your Saved Trips</h2>
    <p>View and manage your saved trips here.</p>

    <div class="trip-grid">
        <div class="trip-card new-trip-card">
            <a href="#" id="open-trip-modal" class="new-trip-link">
                <div class="new-trip-icon">+</div>
                <div class="new-trip-content">
                    <strong>Create new Trip</strong>
                    <span>Start planning your next adventure</span>
                </div>
            </a>
        </div>

        <?php if (count($trips) === 0): ?>
            <div class="trip-card empty-trip-card">
                <div class="trip-card-body">
                    <p>No saved trips yet.</p>
                    <p>Add a new trip to see it here.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($trips as $trip): ?>
                <div class="trip-card saved-trip-card">
                    <div class="trip-card-title"><?php echo htmlspecialchars($trip['title']); ?></div>
                    <div class="trip-card-detail">
                        <strong>Destination</strong>
                        <span><?php echo htmlspecialchars($trip['destination']); ?></span>
                    </div>
                    <div class="trip-card-detail">
                        <strong>Dates</strong>
                        <span><?php echo htmlspecialchars($trip['start_date']); ?> → <?php echo htmlspecialchars($trip['end_date']); ?></span>
                    </div>
                    <div class="trip-card-detail">
                        <strong>Group Size</strong>
                        <span><?php echo htmlspecialchars($trip['group_size']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="trip-modal" class="modal-backdrop" aria-hidden="true">
        <div class="modal-window">
            <div class="modal-header">
                <h3>Create New Trip</h3>
                <button id="close-trip-modal" class="modal-close" type="button">×</button>
            </div>
            <div class="modal-body">
                <?php if (!empty($errors)): ?>
                    <div class="modal-errors">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="POST" class="modal-form">
                    <label>
                        Title
                        <input type="text" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                    </label>
                    <label>
                        Destination
                        <input type="text" name="destination" value="<?php echo htmlspecialchars($_POST['destination'] ?? ''); ?>" required>
                    </label>
                    <label>
                        Start Date
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" required>
                    </label>
                    <label>
                        End Date
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>" required>
                    </label>
                    <label>
                        Add Notes
                        <textarea rows="8" cols="40"name="notes" placeholder="Notes about the trip..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </label>
                    <label>
                        Group Size
                        <input type="number" name="group_size" min="1" value="<?php echo htmlspecialchars($_POST['group_size'] ?? '1'); ?>" required>
                    </label>
                    <div class="modal-actions">
                        <button type="button" class="modal-btn modal-cancel" id="cancel-trip-modal">Cancel</button>
                        <button type="submit" class="modal-btn modal-save" name="create_trip">Save Trip</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
    const tripModal = document.getElementById('trip-modal');
    const openTripModal = document.getElementById('open-trip-modal');
    const closeTripModal = document.getElementById('close-trip-modal');
    const cancelTripModal = document.getElementById('cancel-trip-modal');

    function showTripModal() {
        tripModal.style.display = 'flex';
        tripModal.setAttribute('aria-hidden', 'false');
    }

    function hideTripModal() {
        tripModal.style.display = 'none';
        tripModal.setAttribute('aria-hidden', 'true');
    }

    openTripModal.addEventListener('click', function(event) {
        event.preventDefault();
        showTripModal();
    });

    closeTripModal.addEventListener('click', hideTripModal);
    cancelTripModal.addEventListener('click', hideTripModal);
    tripModal.addEventListener('click', function(event) {
        if (event.target === tripModal) {
            hideTripModal();
        }
    });

    <?php if ($showModal): ?>
        window.addEventListener('DOMContentLoaded', showTripModal);
    <?php endif; ?>
</script>
</body>
</html>