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
$tripActionMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_trip'])) {
    $title = trim($_POST['title'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

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

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO trips (user_id, title, destination, start_date, end_date, notes, travel_style) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $destination, $start_date, $end_date, $notes, '']);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_trip_notes'])) {
    $tripId = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
    $tripNotes = trim($_POST['trip_notes'] ?? '');

    if ($tripId && $tripId > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE trips SET notes = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$tripNotes, $tripId, $_SESSION['user_id']]);
            $tripActionMessage = 'Trip notes saved.';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();
        } catch (PDOException $e) {
            error_log('Trip note update error: ' . $e->getMessage());
            $errors[] = 'Unable to save your notes right now.';
        }
    } else {
        $errors[] = 'Unable to save your notes right now.';
    }
}

// $trips = [];
// $tripDetails = [];
// try {
//     $stmt = $pdo->prepare("SELECT id, title, destination, start_date, end_date, notes FROM trips WHERE user_id = ? ORDER BY start_date ASC");
//     $stmt->execute([$_SESSION['user_id']]);
//     $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sort = $_GET['sort'] ?? 'soonest';

switch ($sort) {
    case 'newest':
        $orderBy = "created_at DESC";
        break;

    case 'oldest':
        $orderBy = "created_at ASC";
        break;

    case 'latest':
        $orderBy = "start_date DESC";
        break;

    case 'soonest':
        $orderBy = "start_date ASC";
        break;
}

$trips = [];
$tripDetails = [];

try {

    $stmt = $pdo->prepare("
        SELECT id, title, destination, start_date, end_date, notes
        FROM trips
        WHERE user_id = ?
        ORDER BY $orderBy
    ");

    $stmt->execute([$_SESSION['user_id']]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($trips as $trip) {
        $tripId = (int)$trip['id'];
        $tripDetails[$tripId] = [
            'notes' => $trip['notes'] ?? '',
            'flights' => [],
            'hotels' => [],
            'attractions' => []
        ];

        $flightStmt = $pdo->prepare("SELECT airline, flight_number, departure_city, arrival_city, departure_airport, arrival_airport, departure_datetime, arrival_datetime, duration_minutes, stops, cabin_class, price_nzd FROM saved_flights WHERE user_id = ? AND trip_id = ? ORDER BY departure_datetime ASC");
        $flightStmt->execute([$_SESSION['user_id'], $tripId]);
        $tripDetails[$tripId]['flights'] = $flightStmt->fetchAll(PDO::FETCH_ASSOC);

        $hotelStmt = $pdo->prepare("SELECT name, type, city, country, address, planned_check_in, planned_check_out, price_per_night_nzd, rating, notes FROM saved_accommodations WHERE user_id = ? AND trip_id = ? ORDER BY planned_check_in ASC, planned_check_out ASC");
        $hotelStmt->execute([$_SESSION['user_id'], $tripId]);
        $tripDetails[$tripId]['hotels'] = $hotelStmt->fetchAll(PDO::FETCH_ASSOC);

        $activityStmt = $pdo->prepare("SELECT name, city, category, activity_date, cost_nzd, description, notes FROM saved_activities WHERE user_id = ? AND trip_id = ? ORDER BY activity_date ASC, name ASC");
        $activityStmt->execute([$_SESSION['user_id'], $tripId]);
        $tripDetails[$tripId]['attractions'] = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
    }
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
        <button class="tab-btn active" data-tab="flights" onclick="showSearchTab('flights', this)">Flights</button>
        <button class="tab-btn" data-tab="accommodation" onclick="showSearchTab('accommodation', this)">Accommodation</button>
        <button class="tab-btn" data-tab="activities" onclick="showSearchTab('activities', this)">Activities</button>
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

        const targetPanel = document.getElementById(tabId);
        if (targetPanel) {
            targetPanel.classList.add('active-panel');
        }

        if (clickedButton) {
            clickedButton.classList.add('active');
        }
    }
 </script>
 
<!--  -->

 <div class="savedTrips">
    
    <div class="savedTrips-header">

        <div class="savedTrips-title">
            <h2>Your Saved Trips</h2>
            <p>View and manage your saved trips here.</p>
        </div>
        
        <!-- <div class="sort-container">
                <label for="sortTrips">Sort by:</label>
                <select id="sortTrips">
                    <option value="newest">Date Created (Newest)</option>
                    <option value="oldest">Date Created (Oldest)</option>
                    <option value="soonest">Trip Coming Soon</option>
                    <option value="latest">Trip Furthest Away</option>
                </select>    
        </div> -->

        <form method="GET" class="sort-container">

            <label for="sortTrips">Sort by:</label>

            <select id="sortTrips" name="sort" onchange="this.form.submit()">

            <option value="soonest" <?= $sort == 'soonest' ? 'selected': '' ?>>
                Trip Coming Soon
            </option>

            <option value="latest" <?= $sort == 'latest' ? 'selected': '' ?>>
                Trip Furthest Away
            </option>

            <option value="newest" <?= $sort == 'newest' ? 'selected': '' ?>>
                Date Created (Newest)
            </option>

            <option value="oldest" <?= $sort == 'oldest' ? 'selected': '' ?>>
                Date Created (Oldest)
            </option>

            </select>

        </form>

    </div>

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
                <?php $tripId = (int)$trip['id']; ?>
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
                    <?php 
                        $startDate = new DateTime($trip['start_date']);
                        $endDate = new DateTime($trip['end_date']);

                        $totalDays = $startDate->diff($endDate)->days + 1;

                        $weeks = floor($totalDays / 7);
                        $days = $totalDays % 7;

                        if ($weeks > 0 && $days > 0){
                            $duration = $weeks . " week" . ($weeks > 1 ? "s" : "") . " " .
                                        $days . " day" . ($days > 1 ? "s" : "");
                        } elseif ($weeks > 0){
                            $duration = $weeks . " week" . ($weeks > 1 ? "s" : "");
                        } else {
                            $duration = $days . " day" . ($days > 1 ? "s" : "");
                        }
                    ?>
                    <div class="trip-card-detail">
                        <strong>Trip Duration</strong>
                        <span><?php echo $duration; ?></span>
                    </div>
                    <div class="trip-card-actions">
                        <button type="button" class="trip-action-btn view-details-btn" data-trip-id="<?php echo $tripId; ?>">View Details</button>
                    </div>
                </div>
                <div class="trip-details-template" id="trip-details-template-<?php echo $tripId; ?>" style="display:none;">
                    <div class="trip-details-summary">
                        <h4><?php echo htmlspecialchars($trip['title']); ?></h4>
                        <p><strong>Destination:</strong> <?php echo htmlspecialchars($trip['destination']); ?></p>
                        <p><strong>Dates:</strong> <?php echo htmlspecialchars($trip['start_date']); ?> → <?php echo htmlspecialchars($trip['end_date']); ?></p>
                        <p><strong>Trip Duration:</strong> <?php echo $duration; ?></p>
                    </div>

                    <div class="trip-details-section">
                        <h5>Flights</h5>
                        <?php if (empty($tripDetails[$tripId]['flights'])): ?>
                            <p class="trip-details-empty">No flights added for this trip yet.</p>
                            <button type="button" class="trip-action-link trip-quick-add-btn" data-search-target="flights">Add Flight</button>
                        <?php else: ?>
                            <div class="saved-items-grid">
                                <?php foreach ($tripDetails[$tripId]['flights'] as $flight): ?>
                                    <div class="saved-item-card saved-item-flight-card">
                                        <div class="saved-item-header">
                                            <h4><?php echo htmlspecialchars($flight['airline'] . ' ' . $flight['flight_number']); ?></h4>
                                            <span class="saved-item-badge">Flight</span>
                                        </div>
                                        <div class="saved-item-meta">
                                            <span><strong>Route:</strong> <?php echo htmlspecialchars($flight['departure_city'] . ' → ' . $flight['arrival_city']); ?></span>
                                            <span><strong>From:</strong> <?php echo htmlspecialchars($flight['departure_airport']); ?></span>
                                            <span><strong>To:</strong> <?php echo htmlspecialchars($flight['arrival_airport']); ?></span>
                                            <span><strong>Departure:</strong> <?php echo htmlspecialchars($flight['departure_datetime'] ? date('d M Y H:i', strtotime($flight['departure_datetime'])) : 'TBD'); ?></span>
                                            <span><strong>Arrival:</strong> <?php echo htmlspecialchars($flight['arrival_datetime'] ? date('d M Y H:i', strtotime($flight['arrival_datetime'])) : 'TBD'); ?></span>
                                            <span><strong>Duration:</strong> <?php echo htmlspecialchars(floor($flight['duration_minutes'] / 60) . 'h ' . ($flight['duration_minutes'] % 60) . 'm'); ?></span>
                                            <span><strong>Stops:</strong> <?php echo htmlspecialchars($flight['stops'] == 0 ? 'Direct' : $flight['stops'] . ' stop' . ($flight['stops'] > 1 ? 's' : '')); ?></span>
                                        </div>
                                        <div class="saved-item-footer">
                                            <span>NZD <?php echo htmlspecialchars(number_format($flight['price_nzd'], 0)); ?></span>
                                            <span><?php echo htmlspecialchars($flight['cabin_class'] ?: 'Economy'); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="trip-details-section">
                        <h5>Hotels</h5>
                        <?php if (empty($tripDetails[$tripId]['hotels'])): ?>
                            <p class="trip-details-empty">No hotel plans added for this trip yet.</p>
                            <button type="button" class="trip-action-link trip-quick-add-btn" data-search-target="accommodation">Add Hotel</button>
                        <?php else: ?>
                            <div class="saved-items-grid">
                                <?php foreach ($tripDetails[$tripId]['hotels'] as $hotel): ?>
                                    <div class="saved-item-card saved-item-hotel-card">
                                        <div class="saved-item-header">
                                            <h4><?php echo htmlspecialchars($hotel['name']); ?></h4>
                                            <span class="saved-item-badge">Hotel</span>
                                        </div>
                                        <div class="saved-item-meta">
                                            <span><strong>Location:</strong> <?php echo htmlspecialchars($hotel['city'] . ', ' . $hotel['country']); ?></span>
                                            <span><strong>Type:</strong> <?php echo htmlspecialchars($hotel['type']); ?></span>
                                            <span><strong>Check-in:</strong> <?php echo htmlspecialchars($hotel['planned_check_in'] ? date('d M Y', strtotime($hotel['planned_check_in'])) : 'TBD'); ?></span>
                                            <span><strong>Check-out:</strong> <?php echo htmlspecialchars($hotel['planned_check_out'] ? date('d M Y', strtotime($hotel['planned_check_out'])) : 'TBD'); ?></span>
                                            <span><strong>Rating:</strong> <?php echo htmlspecialchars($hotel['rating'] ?: 'N/A'); ?></span>
                                        </div>
                                        <div class="saved-item-footer">
                                            <span>NZD <?php echo htmlspecialchars(number_format($hotel['price_per_night_nzd'], 0)); ?> / night</span>
                                            <span><?php echo htmlspecialchars($hotel['address']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="trip-details-section">
                        <h5>Attractions</h5>
                        <?php if (empty($tripDetails[$tripId]['attractions'])): ?>
                            <p class="trip-details-empty">No attractions added for this trip yet.</p>
                            <button type="button" class="trip-action-link trip-quick-add-btn" data-search-target="activities">Add Attraction</button>
                        <?php else: ?>
                            <div class="saved-items-grid">
                                <?php foreach ($tripDetails[$tripId]['attractions'] as $attraction): ?>
                                    <div class="saved-item-card saved-item-activity-card">
                                        <div class="saved-item-header">
                                            <h4><?php echo htmlspecialchars($attraction['name']); ?></h4>
                                            <span class="saved-item-badge">Attraction</span>
                                        </div>
                                        <div class="saved-item-meta">
                                            <span><strong>Category:</strong> <?php echo htmlspecialchars($attraction['category']); ?></span>
                                            <span><strong>Location:</strong> <?php echo htmlspecialchars($attraction['city']); ?></span>
                                            <span><strong>Date:</strong> <?php echo htmlspecialchars($attraction['activity_date'] ? date('d M Y', strtotime($attraction['activity_date'])) : 'TBD'); ?></span>
                                            <span><strong>Cost:</strong> NZD <?php echo htmlspecialchars(number_format($attraction['cost_nzd'], 0)); ?></span>
                                        </div>
                                        <p class="saved-item-description"><?php echo htmlspecialchars($attraction['description']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="trip-details-section">
                        <h5>Estimated Travel Duration</h5>
                        <?php if (empty($tripDetails[$tripId]['flights'])): ?>
                            <p class="trip-details-empty">Add trip items to estimate travel duration.</p>
                        <?php else: ?>
                            <div class="saved-items-grid">
                                <?php foreach ($tripDetails[$tripId]['flights'] as $flight): ?>
                                    <div class="saved-item-card saved-item-activity-card">
                                        <div class="saved-item-header">
                                            <h4><?php echo htmlspecialchars(floor($flight['duration_minutes'] / 60) . ' hours ' . ($flight['duration_minutes'] % 60) . ' minutes '); ?></h4>
                                            <span class="saved-item-badge">Estimate</span>
                                        </div>
                                        <div class="saved-item-meta">
                                            <span><strong>Flight Duration:</strong> <?php echo htmlspecialchars(floor($flight['duration_minutes'] / 60) . ' hours ' . ($flight['duration_minutes'] % 60) . ' minutes '); ?></span>
                                            <span><strong>Airport to Hotel:</strong> -- </span>
                                            <span><strong>Hotel to Activity:</strong> -- </span>
                                            <span><strong>Activity to Airport:</strong> -- </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    


                    <div class="trip-details-section">
                        <h5>Notes</h5>
                        <form method="POST" class="trip-notes-form">
                            <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                            <textarea name="trip_notes" rows="6" placeholder="Add notes for this trip..."><?php echo htmlspecialchars($tripDetails[$tripId]['notes'] !== '' ? $tripDetails[$tripId]['notes'] : ''); ?></textarea>
                            <div class="trip-details-actions">
                                <button type="submit" name="save_trip_notes" class="trip-action-btn">Save Notes</button>
                            </div>
                        </form>
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
                        <textarea rows="8" cols="40" name="notes" placeholder="Notes about the trip..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
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

<div id="trip-details-modal" class="modal-backdrop" aria-hidden="true">
    <div class="modal-window trip-details-window">
        <div class="modal-header">
            <h3>Trip Details</h3>
            <button id="close-trip-details-modal" class="modal-close" type="button">×</button>
        </div>
        <div class="modal-body" id="trip-details-content"></div>
    </div>
</div>

<script>
    const tripModal = document.getElementById('trip-modal');
    const openTripModal = document.getElementById('open-trip-modal');
    const closeTripModal = document.getElementById('close-trip-modal');
    const cancelTripModal = document.getElementById('cancel-trip-modal');
    const tripDetailsModal = document.getElementById('trip-details-modal');
    const tripDetailsContent = document.getElementById('trip-details-content');
    const closeTripDetailsModal = document.getElementById('close-trip-details-modal');

    function showTripModal() {
        tripModal.style.display = 'flex';
        tripModal.setAttribute('aria-hidden', 'false');
    }

    function hideTripModal() {
        tripModal.style.display = 'none';
        tripModal.setAttribute('aria-hidden', 'true');
    }

    function showTripDetailsModal() {
        tripDetailsModal.style.display = 'flex';
        tripDetailsModal.setAttribute('aria-hidden', 'false');
    }

    function hideTripDetailsModal() {
        tripDetailsModal.style.display = 'none';
        tripDetailsModal.setAttribute('aria-hidden', 'true');
        tripDetailsContent.innerHTML = '';
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

    document.querySelectorAll('.view-details-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const tripId = this.getAttribute('data-trip-id');
            const template = document.getElementById('trip-details-template-' + tripId);
            if (template) {
                tripDetailsContent.innerHTML = template.innerHTML;
                showTripDetailsModal();
            }
        });
    });

    tripDetailsContent.addEventListener('click', function(event) {
        const button = event.target.closest('.trip-quick-add-btn');
        if (!button) {
            return;
        }

        const target = button.getAttribute('data-search-target');
        const tabButton = document.querySelector('.tab-btn[data-tab="' + target + '"]');
        hideTripDetailsModal();

        if (tabButton) {
            showSearchTab(target, tabButton);
        }

        window.setTimeout(function() {
            const searchSection = document.querySelector('.search-container-dashBoard');
            if (searchSection) {
                searchSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            const panel = document.getElementById(target);
            if (panel) {
                const focusTarget = panel.querySelector('input, select, textarea');
                if (focusTarget) {
                    focusTarget.focus();
                }
            }
        }, 120);
    });

    closeTripDetailsModal.addEventListener('click', hideTripDetailsModal);
    tripDetailsModal.addEventListener('click', function(event) {
        if (event.target === tripDetailsModal) {
            hideTripDetailsModal();
        }
    });

    <?php if ($showModal): ?>
        window.addEventListener('DOMContentLoaded', showTripModal);
    <?php endif; ?>
</script>
</body>
</html>