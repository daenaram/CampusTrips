<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}

require_once __DIR__ . '/../../assets/api/config/database.php';
require_once __DIR__ . '/../../assets/api/helpers/currencyHelper.php';

$userId = (int) $_SESSION['user_id'];

//   HELPER FUNCTIONS 

function tripBelongsToUser(PDO $pdo, int $tripId, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT id FROM trips WHERE id = ? AND user_id = ?");
    $stmt->execute([$tripId, $userId]);
    return (bool) $stmt->fetch();
}

/**
 * Builds the full budget summary for one trip:
 *  - auto totals from saved flights / accommodation / activities (already stored in NZD)
 *  - user-added custom budget_items (converted to NZD at save time)
 *  - a category breakdown and a single grand total in NZD
 */
function getTripBudgetSummary(PDO $pdo, int $tripId, int $userId): array
{
    // Flights (already stored in NZD)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(price_nzd), 0) AS total FROM saved_flights WHERE trip_id = ? AND user_id = ?");
    $stmt->execute([$tripId, $userId]);
    $flightsTotal = (float) $stmt->fetchColumn();

    // Accommodation: price per night x number of nights
    $stmt = $pdo->prepare("SELECT price_per_night_nzd, planned_check_in, planned_check_out FROM saved_accommodations WHERE trip_id = ? AND user_id = ?");
    $stmt->execute([$tripId, $userId]);
    $hotelsTotal = 0.0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $hotel) {
        $nights = 1;
        if (!empty($hotel['planned_check_in']) && !empty($hotel['planned_check_out'])) {
            try {
                $in = new DateTime($hotel['planned_check_in']);
                $out = new DateTime($hotel['planned_check_out']);
                $diffDays = $in->diff($out)->days;
                $nights = $diffDays > 0 ? $diffDays : 1;
            } catch (Exception $e) {
                $nights = 1;
            }
        }
        $hotelsTotal += ((float) $hotel['price_per_night_nzd']) * $nights;
    }

    // Activities (already stored in NZD)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(cost_nzd), 0) AS total FROM saved_activities WHERE trip_id = ? AND user_id = ?");
    $stmt->execute([$tripId, $userId]);
    $activitiesTotal = (float) $stmt->fetchColumn();

    // User-added custom budget items (each already converted to NZD when saved)
    $stmt = $pdo->prepare("
        SELECT id, category, item_name, amount, currency, amount_nzd
        FROM budget_items
        WHERE trip_id = ? AND user_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$tripId, $userId]);
    $customItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $categoryTotals = [
        'Flights'       => $flightsTotal,
        'Accommodation' => $hotelsTotal,
        'Activities'    => $activitiesTotal,
    ];

    foreach ($customItems as $item) {
        $cat = $item['category'];
        if (!isset($categoryTotals[$cat])) {
            $categoryTotals[$cat] = 0.0;
        }
        $categoryTotals[$cat] += (float) $item['amount_nzd'];
    }

    $grandTotal = array_sum($categoryTotals);

    return [
        'trip_id'         => $tripId,
        'category_totals' => $categoryTotals,
        'grand_total'     => round($grandTotal, 2),
        'custom_items'    => $customItems,
    ];
}

/* =========================================================================
   AJAX API
   Every write (add / edit / delete) returns the freshly recalculated
   summary for that trip, so the front-end can repaint totals + breakdown
   immediately without a page refresh.
   ========================================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    $response = ['success' => false, 'message' => ''];

    try {
        switch ($action) {

            case 'add_item': {
                $tripId   = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
                $category = trim($_POST['category'] ?? '');
                $itemName = trim($_POST['item_name'] ?? '');
                $amount   = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
                $currency = strtoupper(trim($_POST['currency'] ?? 'NZD'));

                if (!$tripId || $category === '' || $itemName === '' || $amount === false || $amount === null || $amount < 0) {
                    $response['message'] = 'Please fill in all budget item fields with valid values.';
                    break;
                }
                if (!isCurrencySupported($currency)) {
                    $response['message'] = 'Unsupported currency selected.';
                    break;
                }
                if (!tripBelongsToUser($pdo, $tripId, $userId)) {
                    $response['message'] = 'Trip not found.';
                    break;
                }

                $amountNzd = convertToNZD($pdo, $amount, $currency);

                $stmt = $pdo->prepare("
                    INSERT INTO budget_items (user_id, trip_id, category, item_name, amount, currency, amount_nzd)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $tripId, $category, $itemName, $amount, $currency, $amountNzd]);

                $response['success'] = true;
                $response['summary'] = getTripBudgetSummary($pdo, $tripId, $userId);
                break;
            }

            case 'update_item': {
                $itemId   = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
                $tripId   = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
                $category = trim($_POST['category'] ?? '');
                $itemName = trim($_POST['item_name'] ?? '');
                $amount   = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
                $currency = strtoupper(trim($_POST['currency'] ?? 'NZD'));

                if (!$itemId || !$tripId || $category === '' || $itemName === '' || $amount === false || $amount === null || $amount < 0) {
                    $response['message'] = 'Please fill in all budget item fields with valid values.';
                    break;
                }
                if (!isCurrencySupported($currency)) {
                    $response['message'] = 'Unsupported currency selected.';
                    break;
                }
                if (!tripBelongsToUser($pdo, $tripId, $userId)) {
                    $response['message'] = 'Trip not found.';
                    break;
                }

                $amountNzd = convertToNZD($pdo, $amount, $currency);

                $stmt = $pdo->prepare("
                    UPDATE budget_items
                    SET category = ?, item_name = ?, amount = ?, currency = ?, amount_nzd = ?
                    WHERE id = ? AND user_id = ? AND trip_id = ?
                ");
                $stmt->execute([$category, $itemName, $amount, $currency, $amountNzd, $itemId, $userId, $tripId]);

                $response['success'] = true;
                $response['summary'] = getTripBudgetSummary($pdo, $tripId, $userId);
                break;
            }

            case 'delete_item': {
                $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
                $tripId = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);

                if (!$itemId || !$tripId) {
                    $response['message'] = 'Invalid item.';
                    break;
                }
                if (!tripBelongsToUser($pdo, $tripId, $userId)) {
                    $response['message'] = 'Trip not found.';
                    break;
                }

                $stmt = $pdo->prepare("DELETE FROM budget_items WHERE id = ? AND user_id = ? AND trip_id = ?");
                $stmt->execute([$itemId, $userId, $tripId]);

                $response['success'] = true;
                $response['summary'] = getTripBudgetSummary($pdo, $tripId, $userId);
                break;
            }

            case 'get_summary': {
                $tripId = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);

                if (!$tripId || !tripBelongsToUser($pdo, $tripId, $userId)) {
                    $response['message'] = 'Trip not found.';
                    break;
                }

                $response['success'] = true;
                $response['summary'] = getTripBudgetSummary($pdo, $tripId, $userId);
                break;
            }

            default:
                $response['message'] = 'Unknown action.';
        }
    } catch (PDOException $e) {
        error_log('Budget AJAX error: ' . $e->getMessage());
        $response['message'] = 'A server error occurred. Please try again.';
    }

    echo json_encode($response);
    exit();
}

//   PAGE RENDER (GET)

$trips = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, destination, start_date, end_date FROM trips WHERE user_id = ? ORDER BY start_date ASC");
    $stmt->execute([$userId]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Budget page trip load error: ' . $e->getMessage());
}

$selectedTripId = filter_input(INPUT_GET, 'trip_id', FILTER_VALIDATE_INT);
$validTripIds = array_map(fn($t) => (int) $t['id'], $trips);
if (!$selectedTripId || !in_array($selectedTripId, $validTripIds, true)) {
    $selectedTripId = $trips[0]['id'] ?? null;
}

$tripBudgets = [];
foreach ($trips as $trip) {
    $tripBudgets[(int) $trip['id']] = getTripBudgetSummary($pdo, (int) $trip['id'], $userId);
}

$supportedCurrencies = getSupportedCurrencies();
$categoryOptions = ['Flights', 'Accommodation', 'Activities', 'Food', 'Transport', 'Insurance', 'Shopping', 'Other'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Budget</title>
    <link rel="stylesheet" href="../../assets/css/settingsbutton.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/hamburgerMenu.css">
    <link rel="stylesheet" href="../../assets/css/budget.css">
</head>
<body>

<!-- Hamburger menu icon -->
<!-- Back to dashboard (top left) -->
<button type="button" class="back-to-dashboard" onclick="location.href='Dashboard.php'" aria-label="Back to dashboard">← Back to Dashboard</button>

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
        <!-- Back to Dashboard moved to top-left for quick access -->
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


<div class="budget-page">
    <div class="budget-page-header">
        <h1>Trip Budgets</h1>
        <p>Track spend across flights, accommodation, activities, and anything else you add — all converted to a single NZD total.</p>
    </div>

    <?php if (count($trips) === 0): ?>
        <div class="budget-empty-state">
            <p>You don't have any saved trips yet.</p>
            <a href="userDashboard.php">Go create a trip</a> to start tracking its budget.
        </div>
    <?php else: ?>

        <div class="budget-trip-tabs" id="budgetTripTabs">
            <?php foreach ($trips as $trip): ?>
                <button type="button"
                        class="budget-trip-tab <?php echo ((int)$trip['id'] === (int)$selectedTripId) ? 'active' : ''; ?>"
                        data-trip-id="<?php echo (int) $trip['id']; ?>">
                    <?php echo htmlspecialchars($trip['title']); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="budget-content" id="budgetContent">
            <!-- Filled in by budget.js on load / tab switch -->
        </div>

    <?php endif; ?>
</div>

<!-- Full trip + budget data set for client-side rendering, no reload needed to switch trips -->
<script>
    const BUDGET_TRIPS = <?php echo json_encode($trips); ?>;
    const BUDGET_DATA = <?php echo json_encode($tripBudgets); ?>;
    const BUDGET_CURRENCIES = <?php echo json_encode($supportedCurrencies); ?>;
    const BUDGET_CATEGORIES = <?php echo json_encode($categoryOptions); ?>;
    let SELECTED_TRIP_ID = <?php echo $selectedTripId ? (int) $selectedTripId : 'null'; ?>;
</script>

<script>
    // ---------- Hamburger menu behaviour (same as dashboard) ----------
    const menuToggle = document.getElementById('menuToggle');
    const menuPanel = document.getElementById('menuPanel');
    const menuBackdrop = document.getElementById('menuBackdrop');

    function openMenu() {
        menuToggle.classList.add('open');
        menuToggle.setAttribute('aria-expanded', 'true');
        menuPanel.classList.add('open');
        menuPanel.setAttribute('aria-hidden', 'false');
        menuBackdrop.classList.add('visible');
    }
    function closeMenu() {
        menuToggle.classList.remove('open');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuPanel.classList.remove('open');
        menuPanel.setAttribute('aria-hidden', 'true');
        menuBackdrop.classList.remove('visible');
    }
    menuToggle.addEventListener('click', () => menuPanel.classList.contains('open') ? closeMenu() : openMenu());
    menuBackdrop.addEventListener('click', closeMenu);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });
</script>

<script src="../../assets/js/budget.js"></script>
</body>
</html>