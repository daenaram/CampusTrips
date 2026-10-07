<?php
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}

require_once __DIR__ . '/../../assets/api/config/database.php';
require_once __DIR__ . '/../../assets/api/dashboard/searchflights.php';
require_once __DIR__ . '/../../assets/api/dashboard/searchHotel.php';

$searchType = $_POST['search_type'] ?? 'flights';
$activeTab = $searchType === 'accommodation' ? 'accommodation' : 'flights';
$searchPerformed = $_SERVER['REQUEST_METHOD'] === 'POST';

$flightSearch = [
    'departure_city' => '',
    'arrival_city' => '',
    'departure_date' => '',
    'return_date' => '',
];

$hotelSearch = [
    'accommodation_name' => '',
    'accommodation_type' => '',
    'accommodation_city' => '',
];

$flights = [];
$accommodations = [];

if ($searchPerformed) {
    if ($searchType === 'accommodation') {
        $hotelSearch['accommodation_name'] = trim($_POST['accommodation_name'] ?? '');
        $hotelSearch['accommodation_type'] = trim($_POST['accommodation_type'] ?? '');
        $hotelSearch['accommodation_city'] = trim($_POST['accommodation_city'] ?? '');
        $accommodations = searchAccommodations($pdo, $hotelSearch);
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

<!DOCTYPE html>
<html>
<head>
    <script src="/AUT-Web-Based-Travel-Planner/assets/js/pwa.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accommodation Search Results</title>
    <link rel="stylesheet" href="../../assets/css/settingsbutton.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/searchBoard.css">
</head>
<body>

    <div class="search-container">
        <div class="search-tabs">
            <button class="tab-btn <?php echo $activeTab === 'flights' ? 'active' : ''; ?>" onclick="showSearchTab('flights', this)">Flights</button>
            <button class="tab-btn <?php echo $activeTab === 'accommodation' ? 'active' : ''; ?>" onclick="showSearchTab('accommodation', this)">Accommodation</button>
            <button class="tab-btn" onclick="showSearchTab('budget', this)">Budget</button>
            <button class="tab-btn" onclick="showSearchTab('itinerary', this)">Itinerary Building</button>
        </div>

        <form method="POST" action="/AUT-Web-Based-Travel-Planner/Pages/userDashboard/flightBoard.php" class="search-panel <?php echo $activeTab === 'flights' ? 'active-panel' : ''; ?>" id="flights">
            <input type="hidden" name="search_type" value="flights">
            <input type="text" name="departure_city" placeholder="Starting Location..." value="<?php echo htmlspecialchars($flightSearch['departure_city']); ?>">
            <input type="text" name="arrival_city" placeholder="Destination..." value="<?php echo htmlspecialchars($flightSearch['arrival_city']); ?>">
            <input type="date" name="departure_date" value="<?php echo htmlspecialchars($flightSearch['departure_date']); ?>">
            <input type="date" name="return_date" value="<?php echo htmlspecialchars($flightSearch['return_date']); ?>">
            <button type="submit" class="search-btn">Search</button>
            <button type="button" class="search-btn" onclick="location.href='Dashboard.php'">Back to Dashboard</button>
        </form>

        <form method="POST" action="/AUT-Web-Based-Travel-Planner/Pages/userDashboard/hotelBoard.php" class="search-panel <?php echo $activeTab === 'accommodation' ? 'active-panel' : ''; ?>" id="accommodation">
            <input type="hidden" name="search_type" value="accommodation">
            <input type="text" name="accommodation_name" placeholder="Search accommodation..." value="<?php echo htmlspecialchars($hotelSearch['accommodation_name']); ?>">
            <input type="text" name="accommodation_type" placeholder="Accommodation type..." value="<?php echo htmlspecialchars($hotelSearch['accommodation_type']); ?>">
            <input type="text" name="accommodation_city" placeholder="City..." value="<?php echo htmlspecialchars($hotelSearch['accommodation_city']); ?>">
            <button type="submit" class="search-btn">Search</button>
        </form>

        <div id="budget" class="search-panel">
            <input type="text" placeholder="Search budget...">
            <button class="search-btn">Search</button>
        </div>

        <div id="itinerary" class="search-panel">
            <input type="text" placeholder="Search itinerary...">
            <button class="search-btn">Search</button>
        </div>
    </div>

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
        <?php endif; ?>

    </div>

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
    <script src="../../assets/js/accomodationSearch.js"></script>
</body>
</html>