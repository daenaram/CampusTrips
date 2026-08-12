<?php
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}

// Include necessary files for database connection and search functions
require_once __DIR__ . '/../../assets/api/config/database.php';
require_once __DIR__ . '/../../assets/api/dashboard/searchflights.php';
require_once __DIR__ . '/../../assets/api/dashboard/searchHotel.php';
require_once __DIR__ . '/../../assets/api/dashboard/searchActivities.php';

function renderTripSelectOptions(array $trips, ?int $selectedTripId = null): string {
    $html = '<option value="">Choose a trip</option>';

    foreach ($trips as $trip) {
        $tripId = (int)($trip['id'] ?? 0);
        $tripTitle = htmlspecialchars($trip['title'] ?? 'Untitled trip');
        $selected = $selectedTripId !== null && $tripId === $selectedTripId ? ' selected' : '';
        $html .= '<option value="' . $tripId . '"' . $selected . '>' . $tripTitle . '</option>';
    }

    return $html;
}

$userTrips = [];
$saveStatus = ['success' => '', 'error' => ''];

try {
    $tripStmt = $pdo->prepare("SELECT id, title, destination, start_date, end_date FROM trips WHERE user_id = ? ORDER BY start_date ASC, title ASC");
    $tripStmt->execute([$_SESSION['user_id']]);
    $userTrips = $tripStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Trip lookup error: ' . $e->getMessage());
}

// Determine which search type is being performed (flights, accommodation, or activities)
$searchType = $_POST['search_type'] ?? 'flights';
$activeTab = in_array($searchType, ['accommodation', 'activities'], true) ? $searchType : 'flights';
$searchPerformed = $_SERVER['REQUEST_METHOD'] === 'POST';

// Initialize search parameters with empty values
$flightSearch = [
    'departure_city' => '',
    'arrival_city' => '',
    'airline' => '',
    'departure_date' => '',
    'return_date' => '',
];

// Accommodation search parameters
$hotelSearch = [
    'accommodation_name' => '',
    'accommodation_type' => '',
    'accommodation_city' => '',
];

// Activity search parameters
$activitySearch = [
    'keyword' => '',
    'city' => '',
    'category' => '',
    'activity_date' => '',
];

$flights = [];
$accommodations = [];
$activities = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_trip_item'])) {
    $tripId = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
    $createNewTrip = isset($_POST['create_new_trip']) && $_POST['create_new_trip'] === '1';
    $itemType = $_POST['item_type'] ?? '';

    if ($createNewTrip) {
        $newTripTitle = trim($_POST['new_trip_title'] ?? '');
        $newTripDestination = trim($_POST['new_trip_destination'] ?? '');
        $newTripStartDate = trim($_POST['new_trip_start_date'] ?? '');
        $newTripEndDate = trim($_POST['new_trip_end_date'] ?? '');
        $newTripNotes = trim($_POST['new_trip_notes'] ?? '');

        $tripErrors = [];
        if ($newTripTitle === '') {
            $tripErrors[] = 'Please enter a title for the new trip.';
        }
        if ($newTripDestination === '') {
            $tripErrors[] = 'Please enter a destination for the new trip.';
        }
        if ($newTripStartDate === '') {
            $tripErrors[] = 'Please enter a start date for the new trip.';
        }
        if ($newTripEndDate === '') {
            $tripErrors[] = 'Please enter an end date for the new trip.';
        }
        if ($newTripStartDate !== '' && $newTripEndDate !== '' && strtotime($newTripStartDate) > strtotime($newTripEndDate)) {
            $tripErrors[] = 'End date must be the same as or after the start date.';
        }

        if (!empty($tripErrors)) {
            $saveStatus['error'] = implode(' ', $tripErrors);
        } else {
            try {
                $createTripStmt = $pdo->prepare("INSERT INTO trips (user_id, title, destination, start_date, end_date, notes, travel_style) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $createTripStmt->execute([$_SESSION['user_id'], $newTripTitle, $newTripDestination, $newTripStartDate, $newTripEndDate, $newTripNotes, '']);
                $tripId = (int)$pdo->lastInsertId();

                $tripStmt = $pdo->prepare("SELECT id, title FROM trips WHERE user_id = ? ORDER BY start_date ASC, title ASC");
                $tripStmt->execute([$_SESSION['user_id']]);
                $userTrips = $tripStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log('Trip creation error: ' . $e->getMessage());
                $saveStatus['error'] = 'Unable to create the trip right now.';
            }
        }
    }

    if (empty($saveStatus['error']) && (!$tripId || $tripId <= 0)) {
        $saveStatus['error'] = 'Please choose or create a trip before saving this item.';
    }

    if (empty($saveStatus['error']) && $tripId && $tripId > 0) {
        $tripOwnershipStmt = $pdo->prepare("SELECT id FROM trips WHERE id = ? AND user_id = ?");
        $tripOwnershipStmt->execute([$tripId, $_SESSION['user_id']]);

        if (!$tripOwnershipStmt->fetch()) {
            $saveStatus['error'] = 'That trip could not be found.';
        } else {
            try {
                if ($itemType === 'flight') {
                    $airlineValue = trim($_POST['airline_value'] ?? $_POST['airline'] ?? '');
                    $flightNumber = trim($_POST['flight_number'] ?? '');
                    $departureDatetime = trim($_POST['departure_datetime'] ?? '');

                    // --- Duplicate check: same airline + flight number + departure time already saved to this trip ---
                    $dupFlightStmt = $pdo->prepare("SELECT id FROM saved_flights WHERE user_id = ? AND trip_id = ? AND airline = ? AND flight_number = ? AND departure_datetime = ?");
                    $dupFlightStmt->execute([$_SESSION['user_id'], $tripId, $airlineValue, $flightNumber, $departureDatetime]);

                    if ($dupFlightStmt->fetch()) {
                        $saveStatus['error'] = 'This flight has already been added to this trip.';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO saved_flights (user_id, trip_id, airline, flight_number, departure_city, arrival_city, departure_airport, arrival_airport, departure_datetime, arrival_datetime, duration_minutes, stops, cabin_class, price_nzd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            $tripId,
                            $airlineValue,
                            $flightNumber,
                            trim($_POST['departure_city'] ?? ''),
                            trim($_POST['arrival_city'] ?? ''),
                            trim($_POST['departure_airport'] ?? ''),
                            trim($_POST['arrival_airport'] ?? ''),
                            $departureDatetime,
                            trim($_POST['arrival_datetime'] ?? ''),
                            (int)($_POST['duration_minutes'] ?? 0),
                            (int)($_POST['stops'] ?? 0),
                            trim($_POST['cabin_class'] ?? 'Economy'),
                            (float)($_POST['price_nzd'] ?? 0),
                        ]);
                        $saveStatus['success'] = 'Flight added to your trip.';
                    }
                } elseif ($itemType === 'accommodation') {
                    $accommodationName = trim($_POST['accommodation_name'] ?? '');
                    $plannedCheckIn = trim($_POST['planned_check_in'] ?? '');
                    $plannedCheckOut = trim($_POST['planned_check_out'] ?? '');
                    $plannedCheckIn = $plannedCheckIn !== '' ? $plannedCheckIn : null;
                    $plannedCheckOut = $plannedCheckOut !== '' ? $plannedCheckOut : null;

                    // --- Duplicate check: same accommodation name + check-in/check-out already saved to this trip ---
                    $dupHotelStmt = $pdo->prepare("SELECT id FROM saved_accommodations WHERE user_id = ? AND trip_id = ? AND name = ? AND planned_check_in <=> ? AND planned_check_out <=> ?");
                    $dupHotelStmt->execute([$_SESSION['user_id'], $tripId, $accommodationName, $plannedCheckIn, $plannedCheckOut]);

                    if ($dupHotelStmt->fetch()) {
                        $saveStatus['error'] = 'This accommodation has already been added to this trip.';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO saved_accommodations (user_id, trip_id, name, type, city, country, address, planned_check_in, planned_check_out, check_in_time, check_out_time, price_per_night_nzd, rating, amenities) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            $tripId,
                            $accommodationName,
                            trim($_POST['accommodation_type'] ?? ''),
                            trim($_POST['accommodation_city'] ?? ''),
                            trim($_POST['accommodation_country'] ?? ''),
                            trim($_POST['accommodation_address'] ?? ''),
                            $plannedCheckIn,
                            $plannedCheckOut,
                            trim($_POST['check_in_time'] ?? '14:00:00'),
                            trim($_POST['check_out_time'] ?? '11:00:00'),
                            (float)($_POST['price_per_night_nzd'] ?? 0),
                            (float)($_POST['rating'] ?? 0),
                            trim($_POST['amenities'] ?? ''),
                        ]);
                        $saveStatus['success'] = 'Accommodation added to your trip.';
                    }
                } elseif ($itemType === 'activity') {
                    $activityName = trim($_POST['activity_name'] ?? '');
                    $activityCity = trim($_POST['activity_city'] ?? '');
                    $activityDate = trim($_POST['activity_date_value'] ?? $_POST['activity_date'] ?? '');

                    // --- Duplicate check: same activity name + city + date already saved to this trip ---
                    $dupActivityStmt = $pdo->prepare("SELECT id FROM saved_activities WHERE user_id = ? AND trip_id = ? AND name = ? AND city = ? AND activity_date = ?");
                    $dupActivityStmt->execute([$_SESSION['user_id'], $tripId, $activityName, $activityCity, $activityDate]);

                    if ($dupActivityStmt->fetch()) {
                        $saveStatus['error'] = 'This activity has already been added to this trip.';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO saved_activities (user_id, trip_id, name, city, category, activity_date, cost_nzd, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            $tripId,
                            $activityName,
                            $activityCity,
                            trim($_POST['activity_category'] ?? ''),
                            $activityDate,
                            (float)($_POST['activity_cost_nzd'] ?? 0),
                            trim($_POST['activity_description'] ?? ''),
                        ]);
                        $saveStatus['success'] = 'Activity added to your trip.';
                    }
                } else {
                    $saveStatus['error'] = 'Unable to save that item.';
                }
            } catch (PDOException $e) {
                error_log('Save trip item error: ' . $e->getMessage());
                $saveStatus['error'] = 'Unable to save that item right now.';
            }
        }
    }
}

// Perform the appropriate search based on the submitted form data
if ($searchPerformed) {
    if ($searchType === 'accommodation') {
        $hotelSearch['accommodation_name'] = trim($_POST['accommodation_name'] ?? '');
        $hotelSearch['accommodation_type'] = trim($_POST['accommodation_type'] ?? '');
        $hotelSearch['accommodation_city'] = trim($_POST['accommodation_city'] ?? '');
        $accommodations = searchAccommodations($pdo, $hotelSearch);
    } elseif ($searchType === 'activities') {
        $activitySearch['keyword'] = trim($_POST['keyword'] ?? '');
        $activitySearch['city'] = trim($_POST['city'] ?? '');
        $activitySearch['category'] = trim($_POST['category'] ?? '');
        $activitySearch['activity_date'] = trim($_POST['activity_date'] ?? '');
        $activities = searchActivities($pdo, $activitySearch);
    } else {
        $flightSearch['departure_city'] = trim($_POST['departure_city'] ?? '');
        $flightSearch['arrival_city'] = trim($_POST['arrival_city'] ?? '');
        $flightSearch['airline'] = trim($_POST['airline'] ?? '');
        $flightSearch['departure_date'] = trim($_POST['departure_date'] ?? '');
        $flightSearch['return_date'] = trim($_POST['return_date'] ?? '');
        $flights = searchFlights($pdo, $flightSearch);
    }
} else {
    $flights = searchFlights($pdo, $flightSearch);
}
?>

<!-- The HTML structure for the search board page, including tabs for different search types and displaying results -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Search Results</title>
    <link rel="stylesheet" href="../../assets/css/settingsbutton.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/searchBoard.css">
    <link rel="stylesheet" href="../../assets/css/hamburgerMenu.css">
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
            <!-- Back to Dashboard moved to top-left for quick access -->
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

    <!-- Page heading for profile setup -->
    <div class="search-container-searchBoard">
        <div class="search-tabs">
            <button class="tab-btn <?php echo $activeTab === 'flights' ? 'active' : ''; ?>" onclick="showSearchTab('flights', this)">Flights</button>
            <button class="tab-btn <?php echo $activeTab === 'accommodation' ? 'active' : ''; ?>" onclick="showSearchTab('accommodation', this)">Accommodation</button>
            <button class="tab-btn <?php echo $activeTab === 'activities' ? 'active' : ''; ?>" onclick="showSearchTab('activities', this)">Activities</button>
        </div>

        <!-- Flight Search Form -->
        <form method="POST" action="/AUT-Web-Based-Travel-Planner/Pages/userDashboard/searchBoard.php" class="search-panel <?php echo $activeTab === 'flights' ? 'active-panel' : ''; ?>" id="flights">
            <input type="hidden" name="search_type" value="flights">
            <input type="text" name="departure_city" placeholder="Starting Location..." value="<?php echo htmlspecialchars($flightSearch['departure_city']); ?>">
            <input type="text" name="arrival_city" placeholder="Destination..." value="<?php echo htmlspecialchars($flightSearch['arrival_city']); ?>">
            <select name="airline" id="airline-search">
                <option value="" <?php echo $flightSearch['airline'] === '' ? 'selected' : ''; ?>>Any Airline</option>
                <option value="Air New Zealand" <?php echo $flightSearch['airline'] === 'Air New Zealand' ? 'selected' : ''; ?>>Air New Zealand</option>
                <option value="Qantas" <?php echo $flightSearch['airline'] === 'Qantas' ? 'selected' : ''; ?>>Qantas</option>
                <option value="Jetstar" <?php echo $flightSearch['airline'] === 'Jetstar' ? 'selected' : ''; ?>>Jetstar</option>
                <option value="Emirates" <?php echo $flightSearch['airline'] === 'Emirates' ? 'selected' : ''; ?>>Emirates</option>
                <option value="Singapore Airlines" <?php echo $flightSearch['airline'] === 'Singapore Airlines' ? 'selected' : ''; ?>>Singapore Airlines</option>
            </select>
            <input type="date" name="departure_date" value="<?php echo htmlspecialchars($flightSearch['departure_date']); ?>">
            <input type="date" name="return_date" value="<?php echo htmlspecialchars($flightSearch['return_date']); ?>">
            <button type="submit" class="search-btn">Search</button>
            
        </form>

        <!-- Accommodation search form with additional fields for city and type -->
        <form method="POST" action="/AUT-Web-Based-Travel-Planner/Pages/userDashboard/searchBoard.php" class="search-panel <?php echo $activeTab === 'accommodation' ? 'active-panel' : ''; ?>" id="accommodation">
            <input type="hidden" name="search_type" value="accommodation">
            <input type="text" name="accommodation_name" placeholder="Search accommodation..." value="<?php echo htmlspecialchars($hotelSearch['accommodation_name']); ?>">
            <input type="text" name="accommodation_type" placeholder="Accommodation type..." value="<?php echo htmlspecialchars($hotelSearch['accommodation_type']); ?>">
            <input type="text" name="accommodation_city" placeholder="City..." value="<?php echo htmlspecialchars($hotelSearch['accommodation_city']); ?>">
            <button type="submit" class="search-btn">Search</button>
        </form>

        <!-- Activity search form -->
        <form method="POST" action="/AUT-Web-Based-Travel-Planner/Pages/userDashboard/searchBoard.php" class="search-panel <?php echo $activeTab === 'activities' ? 'active-panel' : ''; ?>" id="activities">
            <input type="hidden" name="search_type" value="activities">
            <input type="text" name="keyword" placeholder="Search activities..." value="<?php echo htmlspecialchars($activitySearch['keyword']); ?>">
            <input type="text" name="city" placeholder="City/Country" value="<?php echo htmlspecialchars($activitySearch['city']); ?>">
            <input type="text" name="category" placeholder="Category" value="<?php echo htmlspecialchars($activitySearch['category']); ?>">
            <input type="date" name="activity_date" value="<?php echo htmlspecialchars($activitySearch['activity_date']); ?>">
            <button type="submit" class="search-btn">Search</button>
        </form>

    </div>

    <!-- Search results section -->
    <div class="search-results">
        <h2>Search Results</h2>
        <?php if (!empty($saveStatus['success'])): ?>
            <div class="save-status success"><?php echo htmlspecialchars($saveStatus['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($saveStatus['error'])): ?>
            <div class="save-status error"><?php echo htmlspecialchars($saveStatus['error']); ?></div>
        <?php endif; ?>
        <?php if (empty($userTrips)): ?>
            <div class="trip-save-note">Create a trip on your dashboard first, then add flights, hotels, or activities here.</div>
        <?php endif; ?>
        <?php if ($activeTab === 'accommodation'): ?>
            <?php if (count($accommodations) === 0): ?>
                <p>No accommodations found for the selected criteria.</p>
            <?php else: ?>
                <div class="accommodation-results-container">
                    <?php foreach ($accommodations as $acc): ?>
                        <div class="accommodation-result-card draggable-item" draggable="true"
                    data-item-type="accommodation"
                    data-search-type="accommodation"
                    data-accommodation-name="<?php echo htmlspecialchars($acc['name']); ?>"
                    data-accommodation-type="<?php echo htmlspecialchars($acc['type']); ?>"
                    data-accommodation-city="<?php echo htmlspecialchars($acc['city']); ?>"
                    data-accommodation-country="<?php echo htmlspecialchars($acc['country']); ?>"
                    data-accommodation-address="<?php echo htmlspecialchars($acc['address']); ?>"
                    data-check-in-time="<?php echo htmlspecialchars($acc['check_in_time']); ?>"
                    data-check-out-time="<?php echo htmlspecialchars($acc['check_out_time']); ?>"
                    data-price-per-night-nzd="<?php echo htmlspecialchars($acc['price_per_night_nzd']); ?>"
                    data-rating="<?php echo htmlspecialchars($acc['rating']); ?>"
                    data-amenities="<?php echo htmlspecialchars($acc['amenities']); ?>"
                >
                            <div class="accommodation-card-header">
                                <div class="accommodation-info">
                                    <h3><?php echo htmlspecialchars($acc['name']); ?></h3>
                                    <div class="accommodation-type-location">
                                        <span class="accommodation-type"><?php echo htmlspecialchars($acc['type']); ?></span>
                                        <span class="accommodation-location"><?php echo htmlspecialchars($acc['city']); ?>, <?php echo htmlspecialchars($acc['country']); ?></span>
                                    </div>
                                </div>
                                <div class="accommodation-rating">
                                    <span class="rating-badge">★ <?php echo htmlspecialchars($acc['rating']); ?></span>
                                </div>
                            </div>
                            
                            <div class="accommodation-card-body">
                                <div class="amenities-section">
                                    <p class="amenities-label">Amenities:</p>
                                    <p class="amenities-text"><?php echo htmlspecialchars(substr($acc['amenities'], 0, 80)); ?>...</p>
                                </div>
                                <div class="accommodation-address">
                                    <span class="label">Address:</span> <?php echo htmlspecialchars($acc['address']); ?>
                                </div>
                            </div>
                            
                            <div class="accommodation-card-footer">
                                <div class="check-times">
                                    <div class="check-time">
                                        <span class="label">Check-in</span>
                                        <span class="time"><?php echo htmlspecialchars($acc['check_in_time']); ?></span>
                                    </div>
                                    <div class="check-time">
                                        <span class="label">Check-out</span>
                                        <span class="time"><?php echo htmlspecialchars($acc['check_out_time']); ?></span>
                                    </div>
                                </div>
                                <div class="price-section">
                                    <div class="price-per-night">
                                        <span class="label">per night</span>
                                        <span class="amount">NZD <?php echo htmlspecialchars(number_format($acc['price_per_night_nzd'], 0)); ?></span>
                                    </div>
                                    <button type="button" class="add-btn open-trip-modal-btn"
                                        data-item-type="accommodation"
                                        data-search-type="accommodation"
                                        data-accommodation-name="<?php echo htmlspecialchars($acc['name']); ?>"
                                        data-accommodation-type="<?php echo htmlspecialchars($acc['type']); ?>"
                                        data-accommodation-city="<?php echo htmlspecialchars($acc['city']); ?>"
                                        data-accommodation-country="<?php echo htmlspecialchars($acc['country']); ?>"
                                        data-accommodation-address="<?php echo htmlspecialchars($acc['address']); ?>"
                                        data-check-in-time="<?php echo htmlspecialchars($acc['check_in_time']); ?>"
                                        data-check-out-time="<?php echo htmlspecialchars($acc['check_out_time']); ?>"
                                        data-price-per-night-nzd="<?php echo htmlspecialchars($acc['price_per_night_nzd']); ?>"
                                        data-rating="<?php echo htmlspecialchars($acc['rating']); ?>"
                                        data-amenities="<?php echo htmlspecialchars($acc['amenities']); ?>"
                                    >Add to Trip</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php elseif ($activeTab === 'activities'): ?>
            <?php if (count($activities) === 0): ?>
                <p>No activities found for the selected criteria.</p>
            <?php else: ?>
                <div class="activity-results-container">
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-result-card draggable-item" draggable="true"
                        data-item-type="activity"
                        data-search-type="activities"
                        data-activity-name="<?php echo htmlspecialchars($activity['activity_name']); ?>"
                        data-activity-city="<?php echo htmlspecialchars($activity['city']); ?>"
                        data-activity-category="<?php echo htmlspecialchars($activity['category']); ?>"
                        data-activity-date-value="<?php echo htmlspecialchars($activity['activity_date']); ?>"
                        data-activity-cost-nzd="<?php echo htmlspecialchars($activity['cost_nzd']); ?>"
                        data-activity-description="<?php echo htmlspecialchars($activity['description']); ?>"
                    >
                            <div class="activity-card-header">
                                <div class="activity-info">
                                    <h3><?php echo htmlspecialchars($activity['activity_name']); ?></h3>
                                    <span class="activity-category"><?php echo htmlspecialchars($activity['category']); ?></span>
                                </div>
                                <div class="activity-rating">
                                    <span class="rating-badge">★ <?php echo htmlspecialchars($activity['rating']); ?></span>
                                </div>
                            </div>
                            <div class="activity-card-body">
                                <div class="activity-location">
                                    <?php echo htmlspecialchars($activity['city']); ?>, <?php echo htmlspecialchars($activity['country']); ?>
                                </div>
                                <div class="activity-details">
                                    <span><strong>Date:</strong> <?php echo date('d F Y', strtotime($activity['activity_date'])); ?></span>
                                </div>
                                <p class="activity-description">
                                <?php echo htmlspecialchars($activity['description']); ?>
                                </p>
                            </div>
                            <div class="activity-card-footer">
                            <div class="activity-price">
                                <span class="label">Price</span>
                                <span class="amount">
                                    NZD <?php echo htmlspecialchars(number_format($activity['cost_nzd'], 0)); ?>
                                </span>
                            </div>

                            <button type="button" class="add-btn open-trip-modal-btn"
                                data-item-type="activity"
                                data-search-type="activities"
                                data-activity-name="<?php echo htmlspecialchars($activity['activity_name']); ?>"
                                data-activity-city="<?php echo htmlspecialchars($activity['city']); ?>"
                                data-activity-category="<?php echo htmlspecialchars($activity['category']); ?>"
                                data-activity-date-value="<?php echo htmlspecialchars($activity['activity_date']); ?>"
                                data-activity-cost-nzd="<?php echo htmlspecialchars($activity['cost_nzd']); ?>"
                                data-activity-description="<?php echo htmlspecialchars($activity['description']); ?>"
                            >Add to Trip</button>
                        </div>
                        </div>
                    <?php endforeach; ?>
                </div>  
            <?php endif; ?>
        <?php else: ?>
            <?php if (count($flights) === 0): ?>
                <p>No flights found for the selected criteria.</p>
            <?php else: ?>
                <div class="flight-results-container">
                    <?php foreach ($flights as $flight): ?>
                        <div class="flight-result-card draggable-item" draggable="true"
                            data-item-type="flight"
                            data-search-type="flights"
                            data-departure-city="<?php echo htmlspecialchars($flightSearch['departure_city']); ?>"
                            data-arrival-city="<?php echo htmlspecialchars($flightSearch['arrival_city']); ?>"
                            data-airline="<?php echo htmlspecialchars($flightSearch['airline']); ?>"
                            data-departure-date="<?php echo htmlspecialchars($flightSearch['departure_date']); ?>"
                            data-return-date="<?php echo htmlspecialchars($flightSearch['return_date']); ?>"
                            data-airline-value="<?php echo htmlspecialchars($flight['airline']); ?>"
                            data-flight-number="<?php echo htmlspecialchars($flight['flight_number']); ?>"
                            data-departure-airport="<?php echo htmlspecialchars($flight['departure_airport']); ?>"
                            data-arrival-airport="<?php echo htmlspecialchars($flight['arrival_airport']); ?>"
                            data-departure-datetime="<?php echo htmlspecialchars($flight['departure_datetime']); ?>"
                            data-arrival-datetime="<?php echo htmlspecialchars($flight['arrival_datetime']); ?>"
                            data-duration-minutes="<?php echo (int)$flight['duration_minutes']; ?>"
                            data-stops="<?php echo (int)$flight['stops']; ?>"
                            data-cabin-class="Economy"
                            data-price-nzd="<?php echo htmlspecialchars($flight['price_nzd']); ?>"
                        >
                            <div class="flight-card-left">
                                <div class="flight-airline">
                                    <strong><?php echo htmlspecialchars($flight['airline']); ?></strong>
                                    <span><?php echo htmlspecialchars($flight['flight_number']); ?></span>
                                </div>
                                <div class="flight-time-location">
                                    <div class="time"><?php echo substr($flight['departure_datetime'], 11, 5); ?></div>
                                    <div class="location"><?php echo htmlspecialchars($flight['departure_city']); ?> (<?php echo htmlspecialchars($flight['departure_airport']); ?>)</div>
                                </div>
                            </div>
                            
                            <div class="flight-card-middle">
                                <div class="duration-info">
                                    <span class="duration"><?php 
                                        $hours = floor($flight['duration_minutes'] / 60);
                                        $mins = $flight['duration_minutes'] % 60;
                                        echo $hours . 'h ' . $mins . 'm';
                                    ?></span>
                                </div>
                                <div class="stops-info">
                                    <?php echo $flight['stops'] == 0 ? 'Direct' : $flight['stops'] . ' stop' . ($flight['stops'] > 1 ? 's' : ''); ?>
                                </div>
                            </div>
                            
                            <div class="flight-card-right">
                                <div class="flight-time-location">
                                    <div class="time"><?php echo substr($flight['arrival_datetime'], 11, 5); ?></div>
                                    <div class="location"><?php echo htmlspecialchars($flight['arrival_city']); ?> (<?php echo htmlspecialchars($flight['arrival_airport']); ?>)</div>
                                </div>
                            </div>
                            
                            <div class="flight-card-price">
                                <div class="cabin-class">Economy</div>
                                <div class="price-tag">
                                    <span class="currency">NZD</span>
                                    <span class="amount"><?php echo htmlspecialchars(number_format($flight['price_nzd'], 0)); ?></span>
                                </div>
                                <button type="button" class="add-btn open-trip-modal-btn"
                                    data-item-type="flight"
                                    data-search-type="flights"
                                    data-departure-city="<?php echo htmlspecialchars($flightSearch['departure_city']); ?>"
                                    data-arrival-city="<?php echo htmlspecialchars($flightSearch['arrival_city']); ?>"
                                    data-airline="<?php echo htmlspecialchars($flightSearch['airline']); ?>"
                                    data-departure-date="<?php echo htmlspecialchars($flightSearch['departure_date']); ?>"
                                    data-return-date="<?php echo htmlspecialchars($flightSearch['return_date']); ?>"
                                    data-airline-value="<?php echo htmlspecialchars($flight['airline']); ?>"
                                    data-flight-number="<?php echo htmlspecialchars($flight['flight_number']); ?>"
                                    data-departure-airport="<?php echo htmlspecialchars($flight['departure_airport']); ?>"
                                    data-arrival-airport="<?php echo htmlspecialchars($flight['arrival_airport']); ?>"
                                    data-departure-datetime="<?php echo htmlspecialchars($flight['departure_datetime']); ?>"
                                    data-arrival-datetime="<?php echo htmlspecialchars($flight['arrival_datetime']); ?>"
                                    data-duration-minutes="<?php echo (int)$flight['duration_minutes']; ?>"
                                    data-stops="<?php echo (int)$flight['stops']; ?>"
                                    data-cabin-class="Economy"
                                    data-price-nzd="<?php echo htmlspecialchars($flight['price_nzd']); ?>"
                                >Add to Trip</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- move to js file 
    JavaScript function to handle tab switching -->
    <script>
        function showSearchTab(tabId, clickedButton) {
            const panels = document.querySelectorAll('.search-panel');
            const buttons = document.querySelectorAll('.tab-btn');
            const activeForm = document.getElementById(tabId);

            panels.forEach(panel => panel.classList.remove('active-panel'));
            buttons.forEach(button => button.classList.remove('active'));

            if (activeForm && activeForm.tagName === 'FORM') {
                activeForm.classList.add('active-panel');
                activeForm.submit();
            }

            clickedButton.classList.add('active');
        }
    </script>
    <div id="add-trip-modal" class="modal-backdrop" aria-hidden="true">
        <div class="modal-window add-trip-window">
            <div class="modal-header">
                <h3>Add to trip</h3>
                <button type="button" class="modal-close" id="close-add-trip-modal">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="add-trip-modal-form" class="add-trip-modal-form">
                    <input type="hidden" name="save_trip_item" value="1">
                    <input type="hidden" name="item_type">
                    <input type="hidden" name="trip_id" id="selected-trip-id" value="">
                    <input type="hidden" name="create_new_trip" id="create-new-trip-flag" value="0">
                    <input type="hidden" name="search_type">
                    <input type="hidden" name="departure_city">
                    <input type="hidden" name="arrival_city">
                    <input type="hidden" name="airline">
                    <input type="hidden" name="departure_date">
                    <input type="hidden" name="return_date">
                    <input type="hidden" name="airline_value">
                    <input type="hidden" name="flight_number">
                    <input type="hidden" name="departure_airport">
                    <input type="hidden" name="arrival_airport">
                    <input type="hidden" name="departure_datetime">
                    <input type="hidden" name="arrival_datetime">
                    <input type="hidden" name="duration_minutes">
                    <input type="hidden" name="stops">
                    <input type="hidden" name="cabin_class">
                    <input type="hidden" name="price_nzd">
                    <input type="hidden" name="accommodation_name">
                    <input type="hidden" name="accommodation_type">
                    <input type="hidden" name="accommodation_city">
                    <input type="hidden" name="accommodation_country">
                    <input type="hidden" name="accommodation_address">
                    <input type="hidden" name="planned_check_in">
                    <input type="hidden" name="planned_check_out">
                    <input type="hidden" name="check_in_time">
                    <input type="hidden" name="check_out_time">
                    <input type="hidden" name="price_per_night_nzd">
                    <input type="hidden" name="rating">
                    <input type="hidden" name="amenities">
                    <input type="hidden" name="activity_name">
                    <input type="hidden" name="activity_city">
                    <input type="hidden" name="activity_category">
                    <input type="hidden" name="activity_date">
                    <input type="hidden" name="activity_date_value">
                    <input type="hidden" name="activity_cost_nzd">
                    <input type="hidden" name="activity_description">

                    <div class="add-trip-section">
                        <h4>Choose an existing trip</h4>
                        <div class="trip-list-grid">
                            <?php if (!empty($userTrips)): ?>
                                <?php foreach ($userTrips as $trip): ?>
                                    <button type="button" class="trip-card trip-card-selectable" data-trip-id="<?php echo (int)$trip['id']; ?>">
                                        <div class="trip-card-title"><?php echo htmlspecialchars($trip['title']); ?></div>
                                        <div class="trip-card-detail">
                                            <strong>Destination</strong>
                                            <span><?php echo htmlspecialchars($trip['destination']); ?></span>
                                        </div>
                                        <div class="trip-card-detail">
                                            <strong>Dates</strong>
                                            <span><?php echo htmlspecialchars($trip['start_date']); ?> → <?php echo htmlspecialchars($trip['end_date']); ?></span>
                                        </div>
                                    </button>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="trip-option-empty">You do not have any trips yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="add-trip-section">
                        <h4>Create a new trip</h4>
                        <button type="button" class="trip-card new-trip-card create-trip-button" id="open-create-trip-modal">
                            <div class="new-trip-icon">+</div>
                            <div class="new-trip-content">
                                <strong>Create new Trip</strong>
                                <span>Open a trip creation form</span>
                            </div>
                        </button>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="modal-btn modal-cancel" id="cancel-add-trip-modal">Cancel</button>
                        <button type="submit" class="modal-btn modal-save">Add to Trip</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="create-trip-modal" class="modal-backdrop" aria-hidden="true">
        <div class="modal-window add-trip-window">
            <div class="modal-header">
                <h3>Create New Trip</h3>
                <button type="button" class="modal-close" id="close-create-trip-modal">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="create-trip-modal-form" class="modal-form">
                    <input type="hidden" name="save_trip_item" value="1">
                    <input type="hidden" name="item_type">
                    <input type="hidden" name="create_new_trip" value="1">
                    <input type="hidden" name="search_type">
                    <input type="hidden" name="departure_city">
                    <input type="hidden" name="arrival_city">
                    <input type="hidden" name="airline">
                    <input type="hidden" name="departure_date">
                    <input type="hidden" name="return_date">
                    <input type="hidden" name="airline_value">
                    <input type="hidden" name="flight_number">
                    <input type="hidden" name="departure_airport">
                    <input type="hidden" name="arrival_airport">
                    <input type="hidden" name="departure_datetime">
                    <input type="hidden" name="arrival_datetime">
                    <input type="hidden" name="duration_minutes">
                    <input type="hidden" name="stops">
                    <input type="hidden" name="cabin_class">
                    <input type="hidden" name="price_nzd">
                    <input type="hidden" name="accommodation_name">
                    <input type="hidden" name="accommodation_type">
                    <input type="hidden" name="accommodation_city">
                    <input type="hidden" name="accommodation_country">
                    <input type="hidden" name="accommodation_address">
                    <input type="hidden" name="planned_check_in">
                    <input type="hidden" name="planned_check_out">
                    <input type="hidden" name="check_in_time">
                    <input type="hidden" name="check_out_time">
                    <input type="hidden" name="price_per_night_nzd">
                    <input type="hidden" name="rating">
                    <input type="hidden" name="amenities">
                    <input type="hidden" name="activity_name">
                    <input type="hidden" name="activity_city">
                    <input type="hidden" name="activity_category">
                    <input type="hidden" name="activity_date">
                    <input type="hidden" name="activity_date_value">
                    <input type="hidden" name="activity_cost_nzd">
                    <input type="hidden" name="activity_description">

                    <label>
                        Title
                        <input type="text" name="new_trip_title" placeholder="Trip title" required>
                    </label>
                    <label>
                        Destination
                        <input type="text" name="new_trip_destination" placeholder="Destination" required>
                    </label>
                    <label>
                        Start date
                        <input type="date" name="new_trip_start_date" required>
                    </label>
                    <label>
                        End date
                        <input type="date" name="new_trip_end_date" required>
                    </label>
                    <label>
                        Notes
                        <textarea name="new_trip_notes" rows="4" placeholder="Add notes"></textarea>
                    </label>
                    <div class="modal-actions">
                        <button type="button" class="modal-btn modal-cancel" id="cancel-create-trip-modal">Cancel</button>
                        <button type="submit" class="modal-btn modal-save">Create Trip & Add</button>
                    </div>
                </form>
            </div>
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

        function showSearchTab(tabId, clickedButton) {
            const panels = document.querySelectorAll('.search-panel');
            const buttons = document.querySelectorAll('.tab-btn');
            const activeForm = document.getElementById(tabId);

            panels.forEach(panel => panel.classList.remove('active-panel'));
            buttons.forEach(button => button.classList.remove('active'));

            if (activeForm && activeForm.tagName === 'FORM') {
                activeForm.classList.add('active-panel');
                activeForm.submit();
            }

            clickedButton.classList.add('active');
        }

        const addTripModal = document.getElementById('add-trip-modal');
        const addTripModalForm = document.getElementById('add-trip-modal-form');
        const selectedTripInput = document.getElementById('selected-trip-id');
        const createNewTripFlag = document.getElementById('create-new-trip-flag');
        const closeAddTripModal = document.getElementById('close-add-trip-modal');
        const cancelAddTripModal = document.getElementById('cancel-add-trip-modal');
        const createTripModal = document.getElementById('create-trip-modal');
        const createTripModalForm = document.getElementById('create-trip-modal-form');
        const closeCreateTripModal = document.getElementById('close-create-trip-modal');
        const cancelCreateTripModal = document.getElementById('cancel-create-trip-modal');

        function copyHiddenFields(sourceForm, targetForm) {
            const hiddenFields = sourceForm.querySelectorAll('input[type="hidden"]');
            hiddenFields.forEach(field => {
                const targetField = targetForm.querySelector(`[name="${field.name}"]`);
                if (targetField) {
                    targetField.value = field.value;
                }
            });
        }

        function getDragValue(element, attr) {
            const dataAttr = 'data-' + attr.replace(/([A-Z])/g, '-$1').toLowerCase();
            return element.getAttribute(dataAttr) || '';
        }

        function openAddTripModal(button) {
            if (!addTripModal || !addTripModalForm) {
                return;
            }

            addTripModalForm.reset();
            selectedTripInput.value = '';
            createNewTripFlag.value = '0';
            document.querySelectorAll('.trip-card-selectable').forEach(option => option.classList.remove('active'));

            const fieldMap = [
                ['item_type', getDragValue(button, 'itemType')],
                ['search_type', getDragValue(button, 'searchType')],
                ['departure_city', getDragValue(button, 'departureCity')],
                ['arrival_city', getDragValue(button, 'arrivalCity')],
                ['airline', getDragValue(button, 'airline')],
                ['departure_date', getDragValue(button, 'departureDate')],
                ['return_date', getDragValue(button, 'returnDate')],
                ['airline_value', getDragValue(button, 'airlineValue')],
                ['flight_number', getDragValue(button, 'flightNumber')],
                ['departure_airport', getDragValue(button, 'departureAirport')],
                ['arrival_airport', getDragValue(button, 'arrivalAirport')],
                ['departure_datetime', getDragValue(button, 'departureDatetime')],
                ['arrival_datetime', getDragValue(button, 'arrivalDatetime')],
                ['duration_minutes', getDragValue(button, 'durationMinutes')],
                ['stops', getDragValue(button, 'stops')],
                ['cabin_class', getDragValue(button, 'cabinClass')],
                ['price_nzd', getDragValue(button, 'priceNzd')],
                ['accommodation_name', getDragValue(button, 'accommodationName')],
                ['accommodation_type', getDragValue(button, 'accommodationType')],
                ['accommodation_city', getDragValue(button, 'accommodationCity')],
                ['accommodation_country', getDragValue(button, 'accommodationCountry')],
                ['accommodation_address', getDragValue(button, 'accommodationAddress')],
                ['planned_check_in', getDragValue(button, 'plannedCheckIn')],
                ['planned_check_out', getDragValue(button, 'plannedCheckOut')],
                ['check_in_time', getDragValue(button, 'checkInTime')],
                ['check_out_time', getDragValue(button, 'checkOutTime')],
                ['price_per_night_nzd', getDragValue(button, 'pricePerNightNzd')],
                ['rating', getDragValue(button, 'rating')],
                ['amenities', getDragValue(button, 'amenities')],
                ['activity_name', getDragValue(button, 'activityName')],
                ['activity_city', getDragValue(button, 'activityCity')],
                ['activity_category', getDragValue(button, 'activityCategory')],
                ['activity_date', getDragValue(button, 'activityDate')],
                ['activity_date_value', getDragValue(button, 'activityDateValue')],
                ['activity_cost_nzd', getDragValue(button, 'activityCostNzd')],
                ['activity_description', getDragValue(button, 'activityDescription')]
            ];

            fieldMap.forEach(([name, value]) => {
                const field = addTripModalForm.querySelector(`[name="${name}"]`);
                if (field) {
                    field.value = value || '';
                }
            });

            addTripModal.style.display = 'flex';
            addTripModal.setAttribute('aria-hidden', 'false');
        }

        function openCreateTripModal() {
            if (!createTripModal || !createTripModalForm || !addTripModalForm) {
                return;
            }

            createTripModalForm.reset();
            copyHiddenFields(addTripModalForm, createTripModalForm);
            addTripModal.style.display = 'none';
            addTripModal.setAttribute('aria-hidden', 'true');
            createTripModal.style.display = 'flex';
            createTripModal.setAttribute('aria-hidden', 'false');
        }

        function closeAddTripModalHandler() {
            addTripModal.style.display = 'none';
            addTripModal.setAttribute('aria-hidden', 'true');
        }

        function closeCreateTripModalHandler(showParent = true) {
            if (!createTripModal) {
                return;
            }
            createTripModal.style.display = 'none';
            createTripModal.setAttribute('aria-hidden', 'true');
            if (showParent) {
                addTripModal.style.display = 'flex';
                addTripModal.setAttribute('aria-hidden', 'false');
            }
        }

        const dragState = {
            sourceElement: null,
            isDragging: false,
        };

        document.querySelectorAll('.open-trip-modal-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                openAddTripModal(this);
            });
        });

        document.querySelectorAll('.draggable-item').forEach(function(card) {
            card.addEventListener('dragstart', function(event) {
                dragState.sourceElement = this;
                dragState.isDragging = true;
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', 'dragging');
                this.classList.add('drag-source');
                setTimeout(() => openAddTripModal(this), 0);
            });

            card.addEventListener('dragend', function() {
                this.classList.remove('drag-source');
                dragState.sourceElement = null;
                dragState.isDragging = false;
            });
        });

        document.querySelectorAll('.trip-card-selectable, .create-trip-button').forEach(function(target) {
            target.addEventListener('dragover', function(event) {
                event.preventDefault();
                this.classList.add('drop-target');
                event.dataTransfer.dropEffect = 'move';
            });

            target.addEventListener('dragenter', function(event) {
                event.preventDefault();
                this.classList.add('drop-target');
            });

            target.addEventListener('dragleave', function() {
                this.classList.remove('drop-target');
            });

            target.addEventListener('drop', function(event) {
                event.preventDefault();
                this.classList.remove('drop-target');
                if (!dragState.isDragging) {
                    return;
                }
                if (this.classList.contains('create-trip-button')) {
                    openCreateTripModal();
                    createTripModalForm.querySelector('[name="create_new_trip"]').value = '1';
                } else {
                    document.querySelectorAll('.trip-card-selectable').forEach(option => option.classList.remove('active'));
                    this.classList.add('active');
                    selectedTripInput.value = this.getAttribute('data-trip-id') || '';
                    createNewTripFlag.value = '0';
                    addTripModalForm.submit();
                }
            });
        });

        document.querySelectorAll('.trip-card-selectable').forEach(function(button) {
            button.addEventListener('click', function() {
                document.querySelectorAll('.trip-card-selectable').forEach(option => option.classList.remove('active'));
                this.classList.add('active');
                selectedTripInput.value = this.getAttribute('data-trip-id') || '';
                createNewTripFlag.value = '0';
            });
        });

        document.querySelectorAll('.create-trip-button').forEach(function(button) {
            button.addEventListener('click', function() {
                openCreateTripModal();
            });
        });

        addTripModalForm.addEventListener('submit', function(event) {
            const hasSelectedTrip = selectedTripInput.value !== '';

            if (!hasSelectedTrip) {
                event.preventDefault();
                alert('Please choose an existing trip or use Create new Trip.');
            }
        });

        closeAddTripModal.addEventListener('click', closeAddTripModalHandler);
        cancelAddTripModal.addEventListener('click', closeAddTripModalHandler);
        addTripModal.addEventListener('click', function(event) {
            if (event.target === addTripModal) {
                closeAddTripModalHandler();
            }
        });

        closeCreateTripModal.addEventListener('click', function() {
            closeCreateTripModalHandler(true);
        });
        cancelCreateTripModal.addEventListener('click', function() {
            closeCreateTripModalHandler(true);
        });
        createTripModal.addEventListener('click', function(event) {
            if (event.target === createTripModal) {
                closeCreateTripModalHandler(true);
            }
        });
    </script>
</body>
</html>