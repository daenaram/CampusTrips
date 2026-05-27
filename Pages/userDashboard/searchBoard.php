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

// Determine which search type is being performed (flights, accommodation, or activities)
$searchType = $_POST['search_type'] ?? 'flights';
$activeTab = in_array($searchType, ['accommodation', 'activities'], true) ? $searchType : 'flights';
$searchPerformed = $_SERVER['REQUEST_METHOD'] === 'POST';

// Initialize search parameters with empty values
$flightSearch = [
    'departure_city' => '',
    'arrival_city' => '',
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
</head>
<body>
    <!-- Page heading for profile setup -->
    <div class="search-container">
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
            <input type="date" name="departure_date" value="<?php echo htmlspecialchars($flightSearch['departure_date']); ?>">
            <input type="date" name="return_date" value="<?php echo htmlspecialchars($flightSearch['return_date']); ?>">
            <button type="submit" class="search-btn">Search</button>
            <button type="button" class="search-btn" onclick="location.href='Dashboard.php'">Back to Dashboard</button>
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
        <?php if ($activeTab === 'accommodation'): ?>
            <?php if (count($accommodations) === 0): ?>
                <p>No accommodations found for the selected criteria.</p>
            <?php else: ?>
                <div class="accommodation-results-container">
                    <?php foreach ($accommodations as $acc): ?>
                        <div class="accommodation-result-card">
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
                                    <button class="add-btn">Add Now</button>
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
                        <div class="activity-result-card">
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
                                    <span><strong>Date:</strong> <?php echo htmlspecialchars($activity['activity_date']); ?></span>
                                    <span><strong>Time:</strong> <?php echo htmlspecialchars(substr($activity['activity_time'], 0, 5)); ?></span>
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

                            <button class="add-btn">Add to Plan</button>
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
                        <div class="flight-result-card">
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
                                <button class="add-btn">Add Now</button>
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

            panels.forEach(panel => panel.classList.remove('active-panel'));
            buttons.forEach(button => button.classList.remove('active'));

            document.getElementById(tabId).classList.add('active-panel');
            clickedButton.classList.add('active');
        }
    </script>
</body>
</html>