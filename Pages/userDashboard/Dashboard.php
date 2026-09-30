<?php
// Start session to access user data from login
session_start();

// Redirect to login if user is not authenticated. A shared trip link
// (?shared_token=...) survives this round trip via login.php's whitelisted
// "redirect" param, so signing in lands back here with it still attached.
if (!isset($_SESSION['user_id'])) {
    $loginUrl = "/AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html";
    $incomingSharedToken = trim($_GET['shared_token'] ?? '');
    if ($incomingSharedToken !== '') {
        $returnTo = '/AUT-Web-Based-Travel-Planner/Pages/userDashboard/Dashboard.php?shared_token=' . urlencode($incomingSharedToken);
        $loginUrl .= '?redirect=' . urlencode($returnTo);
    }
    header("Location: {$loginUrl}");
    exit();
}

require_once __DIR__ . '/../../assets/api/config/database.php';

//for conflict detection
require_once __DIR__ . '/../../assets/api/helpers/conflictDetection.php';

//for budget totals (shared with budget.php)
require_once __DIR__ . '/../../assets/api/helpers/currencyHelper.php';
require_once __DIR__ . '/../../assets/api/helpers/budgetHelper.php';

//for trip category
require_once __DIR__ . '/../../assets/api/helpers/categoryHelper.php';

//for shareable trip links
require_once __DIR__ . '/../../assets/api/helpers/shareHelper.php';

$errors = [];
$showModal = false;
$tripActionMessage = '';
$sharedLinkError = '';

// A signed-in user arriving via someone else's shared trip link: record
// that they now have access to it, then redirect to a clean URL (so the
// raw token doesn't linger in the address bar/history) that auto-opens
// the trip's details. Visibility going forward is still re-checked live
// against trip_shares/trips on every load — see getSharedTripsForUser().
if (isset($_GET['shared_token']) && trim($_GET['shared_token']) !== '') {
    $incomingToken = trim($_GET['shared_token']);
    try {
        $incomingShare = resolveUsableShare($pdo, $incomingToken);
        if ($incomingShare) {
            grantShareRecipient($pdo, (int) $incomingShare['id'], (int) $incomingShare['trip_id'], (int) $_SESSION['user_id']);
            header('Location: /AUT-Web-Based-Travel-Planner/Pages/userDashboard/Dashboard.php?open_trip=' . (int) $incomingShare['trip_id']);
            exit();
        }

        // Not usable — figure out which of the three reasons, so the
        // banner says something more specific than a generic "invalid".
        $rawShare = getShareByToken($pdo, $incomingToken);
        if (!$rawShare) {
            $sharedLinkError = 'That shared trip link is no longer available.';
        } elseif ($rawShare['trip_exists'] === null) {
            $sharedLinkError = 'That trip no longer exists.';
        } elseif ((int) $rawShare['is_private']) {
            $sharedLinkError = 'That trip is no longer publicly accessible.';
        } else {
            $sharedLinkError = 'That shared trip link is no longer available.';
        }
    } catch (PDOException $e) {
        error_log('Shared link acceptance error: ' . $e->getMessage());
        $sharedLinkError = 'Unable to open that shared trip link right now.';
    }
}

// Handle trip creation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_trip'])) {
    $title = trim($_POST['title'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $travel_style = tripCategory($_POST['travel_style'] ?? null);
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

    //checking if there is an exisitng trip that overlaps with the dates
    if (empty($errors)) {
        try {
            // We need the ID and Title to trigger the popup
            $checkStmt = $pdo->prepare("
            SELECT id, title FROM trips 
            WHERE user_id = ? 
            AND (start_date <= ? AND end_date >= ?)
            LIMIT 1
        ");

            $checkStmt->execute([$_SESSION['user_id'], $end_date, $start_date]);
            $conflictingTrip = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($conflictingTrip) {
                $errors[] = 'Schedule Conflict: Overlaps with "' . htmlspecialchars($conflictingTrip['title']) . '". Please choose different dates.';
                // CRITICAL: Capture the ID for the JS bridge
                $conflictingTripId = (int) $conflictingTrip['id'];
            }
        } catch (PDOException $e) {
            error_log('Overlap check error: ' . $e->getMessage());
        }
    }


    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO trips (user_id, title, destination, start_date, end_date, notes, travel_style) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $destination, $start_date, $end_date, $notes, $travel_style]);
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

// Handle trip notes saving — the trip's owner, or a signed-in visitor
// currently holding edit access to it via a shared link.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_trip_notes'])) {
    $tripId = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
    $tripNotes = trim($_POST['trip_notes'] ?? '');
    $scopeOwnerId = $tripId ? getEditableTripOwnerId($pdo, $tripId, (int) $_SESSION['user_id']) : null;

    if ($tripId && $scopeOwnerId) {
        try {
            $stmt = $pdo->prepare("UPDATE trips SET notes = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$tripNotes, $tripId, $scopeOwnerId]);
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

// Handle trip deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_trip'])) {
    $tripId = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);

    if ($tripId && $tripId > 0) {
        try {
            $pdo->beginTransaction();

            $deleteFlightStmt = $pdo->prepare("DELETE FROM saved_flights WHERE user_id = ? AND trip_id = ?");
            $deleteFlightStmt->execute([$_SESSION['user_id'], $tripId]);

            $deleteHotelStmt = $pdo->prepare("DELETE FROM saved_accommodations WHERE user_id = ? AND trip_id = ?");
            $deleteHotelStmt->execute([$_SESSION['user_id'], $tripId]);

            $deleteActivityStmt = $pdo->prepare("DELETE FROM saved_activities WHERE user_id = ? AND trip_id = ?");
            $deleteActivityStmt->execute([$_SESSION['user_id'], $tripId]);

            $deleteTripStmt = $pdo->prepare("DELETE FROM trips WHERE id = ? AND user_id = ?");
            $deleteTripStmt->execute([$tripId, $_SESSION['user_id']]);

            $pdo->commit();
            $tripActionMessage = 'Trip deleted successfully.';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Trip deletion error: ' . $e->getMessage());
            $errors[] = 'Unable to delete the trip right now.';
        }
    } else {
        $errors[] = 'Unable to delete the trip right now.';
    }
}

// Setting activity date — owner, or a signed-in visitor with edit access
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_activity_date'])) {

    $tripId = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
    $activityId = filter_input(INPUT_POST, 'activity_id', FILTER_VALIDATE_INT);
    $activityDate = trim($_POST['activity_date'] ?? '');
    $scopeOwnerId = $tripId ? getEditableTripOwnerId($pdo, $tripId, (int) $_SESSION['user_id']) : null;

    if (!$tripId || !$scopeOwnerId || !$activityId || $activityDate === '') {
        $errors[] = 'Please choose a valid activity date.';
    } else {
        try {

            // Get the trip's valid date range
            $tripDateStmt = $pdo->prepare("
                SELECT start_date, end_date
                FROM trips
                WHERE id = ? AND user_id = ?
            ");

            $tripDateStmt->execute([
                $tripId,
                $scopeOwnerId
            ]);

            $selectedTrip = $tripDateStmt->fetch(PDO::FETCH_ASSOC);

            if (!$selectedTrip) {
                $errors[] = 'Trip could not be found.';

            } elseif (
                $activityDate < $selectedTrip['start_date'] ||
                $activityDate > $selectedTrip['end_date']
            ) {
                $errors[] = 'Activity date must be within the trip dates.';

            } else {

                $updateStmt = $pdo->prepare("
                    UPDATE saved_activities
                    SET activity_date = ?
                    WHERE id = ?
                    AND trip_id = ?
                    AND user_id = ?
                ");

                $updateStmt->execute([
                    $activityDate,
                    $activityId,
                    $tripId,
                    $scopeOwnerId
                ]);

                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit();
            }

        } catch (PDOException $e) {
            error_log('Activity date update error: ' . $e->getMessage());
            $errors[] = 'Unable to update activity date.';
        }
    }
}

// Handle saved item deletion — owner, or a signed-in visitor with edit access
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_saved_item'])) {
    $tripId = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
    $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $itemType = $_POST['item_type'] ?? '';
    $scopeOwnerId = $tripId ? getEditableTripOwnerId($pdo, $tripId, (int) $_SESSION['user_id']) : null;

    if ($tripId && $scopeOwnerId && $itemId && in_array($itemType, ['flight', 'hotel', 'activity'], true)) {
        try {
            switch ($itemType) {
                case 'flight':
                    $stmt = $pdo->prepare("DELETE FROM saved_flights WHERE id = ? AND user_id = ? AND trip_id = ?");
                    break;
                case 'hotel':
                    $stmt = $pdo->prepare("DELETE FROM saved_accommodations WHERE id = ? AND user_id = ? AND trip_id = ?");
                    break;
                case 'activity':
                    $stmt = $pdo->prepare("DELETE FROM saved_activities WHERE id = ? AND user_id = ? AND trip_id = ?");
                    break;
            }
            if (isset($stmt)) {
                $stmt->execute([$itemId, $scopeOwnerId, $tripId]);
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit();
            }
        } catch (PDOException $e) {
            error_log('Item deletion error: ' . $e->getMessage());
            $errors[] = 'Unable to remove the saved item right now.';
        }
    } else {
        $errors[] = 'Unable to remove the saved item right now.';
    }
}

// Expenses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {

    $tripId = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);

    $expenseType = trim($_POST['expense_type'] ?? '');
    $expenseName = trim($_POST['expense_name'] ?? '');

    $expenseAmount = filter_input(
        INPUT_POST,
        'expense_amount',
        FILTER_VALIDATE_FLOAT
    );

    $expenseCurrency = strtoupper(
        trim($_POST['expense_currency'] ?? 'NZD')
    );

    $scopeOwnerId = $tripId ? getEditableTripOwnerId($pdo, $tripId, (int) $_SESSION['user_id']) : null;

    $allowedExpenseTypes = [
        'Flights',
        'Accommodation',
        'Activities',
        'Food',
        'Transport',
        'Insurance',
        'Shopping',
        'Other'
    ];

    // Validate the submitted fields
    if (
        !$tripId ||
        $expenseName === '' ||
        $expenseAmount === false ||
        $expenseAmount <= 0 ||
        !in_array($expenseType, $allowedExpenseTypes, true)
    ) {
        $errors[] = 'Please enter valid expense details.';
    }

    // Validate the currency
    elseif (!isCurrencySupported($expenseCurrency)) {
        $errors[] = 'Unsupported currency selected.';
    }

    // Owner, or a signed-in visitor currently holding edit access via a shared link
    elseif (!$scopeOwnerId) {
        $errors[] = 'Trip could not be found.';
    }

    else {

        try {

            // Convert the entered amount into NZD
            $amountNzd = convertToNZD(
                $pdo,
                $expenseAmount,
                $expenseCurrency
            );

            // Save it into the SAME table used by
            // Additional Budget Items
            $stmt = $pdo->prepare("
                INSERT INTO budget_items
                (
                    user_id,
                    trip_id,
                    category,
                    item_name,
                    amount,
                    currency,
                    amount_nzd
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $scopeOwnerId,
                $tripId,
                $expenseType,
                $expenseName,
                $expenseAmount,
                $expenseCurrency,
                $amountNzd
            ]);

            // Prevent duplicate form submission if page is refreshed
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();

        } catch (Throwable $e) {

            error_log(
                'Expense save error: ' . $e->getMessage()
            );

            $errors[] = 'Unable to save expense.';
        }
    }
}

// Handle creating a trip's first shareable link, or replacing its current
// one. "Generate New Link" uses this same handler — it and the initial
// "Create Shareable Link" action are the same operation: revoke whatever
// link currently exists for the trip, then mint a brand new, unique one.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_share_link'])) {
    $tripId = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);

    if ($tripId && tripBelongsToUser($pdo, $tripId, $_SESSION['user_id'])) {
        try {
            createShareLink($pdo, $tripId, (int) $_SESSION['user_id']);
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();
        } catch (PDOException $e) {
            error_log('Share link generation error: ' . $e->getMessage());
            $errors[] = 'Unable to generate a shareable link right now.';
        }
    } else {
        $errors[] = 'Unable to generate a shareable link right now.';
    }
}

// Handle enabling/disabling the trip's current shareable link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_share_link'])) {
    $tripId = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
    $activate = ($_POST['activate'] ?? '') === '1';

    if ($tripId && tripBelongsToUser($pdo, $tripId, $_SESSION['user_id'])) {
        try {
            setShareLinkActive($pdo, $tripId, (int) $_SESSION['user_id'], $activate);
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();
        } catch (PDOException $e) {
            error_log('Share link toggle error: ' . $e->getMessage());
            $errors[] = 'Unable to update the shareable link right now.';
        }
    } else {
        $errors[] = 'Unable to update the shareable link right now.';
    }
}

// Handle switching the trip's current link between view-only and editable.
// Editing additionally always requires the visitor to sign in first — that
// gate is enforced on the public share page itself, not here.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_share_access'])) {
    $tripId = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
    $accessLevel = ($_POST['access_level'] ?? '') === 'edit' ? 'edit' : 'view';

    if ($tripId && tripBelongsToUser($pdo, $tripId, $_SESSION['user_id'])) {
        try {
            setShareAccessLevel($pdo, $tripId, (int) $_SESSION['user_id'], $accessLevel);
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();
        } catch (PDOException $e) {
            error_log('Share access level update error: ' . $e->getMessage());
            $errors[] = 'Unable to update the link permission right now.';
        }
    } else {
        $errors[] = 'Unable to update the link permission right now.';
    }
}

// Handle switching a trip's privacy setting. Making a trip private blocks
// its shared link (if any) even while that link is otherwise active.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_trip_privacy'])) {
    $tripId = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
    $isPrivate = ($_POST['is_private'] ?? '') === '1';

    if ($tripId && tripBelongsToUser($pdo, $tripId, $_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE trips SET is_private = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$isPrivate ? 1 : 0, $tripId, $_SESSION['user_id']]);
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();
        } catch (PDOException $e) {
            error_log('Trip privacy update error: ' . $e->getMessage());
            $errors[] = 'Unable to update the trip privacy setting right now.';
        }
    } else {
        $errors[] = 'Unable to update the trip privacy setting right now.';
    }
}

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
$allTrips = [];
$tripDetails = [];

// Fetch the user's own trips, plus any trips shared with them that are
// still currently accessible, and merge the two into one list.

try {

    $currentDate = date("Y-m-d");

    $stmt = $pdo->prepare("
        SELECT id, title, destination, start_date, end_date, notes, travel_style, is_private, created_at
        FROM trips
        WHERE user_id = ?
        ORDER BY $orderBy
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $ownedTrips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ownedTrips as &$ownedTrip) {
        $ownedTrip['is_owner'] = true;
        $ownedTrip['owner_id'] = (int) $_SESSION['user_id'];
        $ownedTrip['owner_name'] = null;
        $ownedTrip['link_access_level'] = null;
    }
    unset($ownedTrip);

    $sharedTrips = getSharedTripsForUser($pdo, (int) $_SESSION['user_id']);
    foreach ($sharedTrips as &$sharedTrip) {
        $sharedTrip['is_owner'] = false;
        $sharedTrip['owner_id'] = (int) $sharedTrip['owner_id'];
    }
    unset($sharedTrip);

    // The two queries can't share one ORDER BY, so sort the merged list in
    // PHP using the same rule the dropdown offers.
    $trips = array_merge($ownedTrips, $sharedTrips);
    usort($trips, function ($a, $b) use ($sort) {
        switch ($sort) {
            case 'newest':
                return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
            case 'oldest':
                return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
            case 'latest':
                return strcmp($b['start_date'], $a['start_date']);
            case 'soonest':
            default:
                return strcmp($a['start_date'], $b['start_date']);
        }
    });

    $allTrips = $trips;

    //To deleted completed trips from "Your Saved Trips" section, we filter out trips that have already ended.
    $currentDate = date('Y-m-d');
    $activeTrips = array_filter($trips, function ($trip) use ($currentDate) {
        return $trip['end_date'] >= $currentDate;
    });

    foreach ($trips as $trip) {
        $tripId = (int) $trip['id'];

        // Every saved_flights/saved_accommodations/... row for a trip is
        // stored under its OWNER's user_id, regardless of who is looking
        // at it right now — a shared trip's items still belong to whoever
        // created the trip.
        $scopeUserId = $trip['is_owner'] ? (int) $_SESSION['user_id'] : (int) $trip['owner_id'];

        $tripDetails[$tripId] = [
            'notes' => $trip['notes'] ?? '',
            'flights' => [],
            'hotels' => [],
            'attractions' => [],
            'expenses' => [],
            'is_owner' => $trip['is_owner'],
            'owner_name' => $trip['owner_name'],
            // 'owner' (full control), 'edit' (shared, can manage saved items/notes), or 'view' (shared, read-only)
            'access_level' => $trip['is_owner'] ? 'owner' : $trip['link_access_level'],
        ];

        // Share management (the link itself) is an owner-only feature
        $tripDetails[$tripId]['share'] = $trip['is_owner']
            ? getCurrentShareForTrip($pdo, $tripId, $scopeUserId)
            : null;

        // Fetch associated flights, hotels, and attractions for each trip
        $flightStmt = $pdo->prepare("SELECT id, airline, flight_number, departure_city, arrival_city, departure_airport, arrival_airport, departure_datetime, arrival_datetime, duration_minutes, stops, cabin_class, price_nzd FROM saved_flights WHERE user_id = ? AND trip_id = ? ORDER BY departure_datetime ASC");
        $flightStmt->execute([$scopeUserId, $tripId]);
        $tripDetails[$tripId]['flights'] = $flightStmt->fetchAll(PDO::FETCH_ASSOC);

        $hotelStmt = $pdo->prepare("SELECT id, name, type, city, country, address, planned_check_in, planned_check_out, price_per_night_nzd, rating, notes FROM saved_accommodations WHERE user_id = ? AND trip_id = ? ORDER BY planned_check_in ASC, planned_check_out ASC");
        $hotelStmt->execute([$scopeUserId, $tripId]);
        $tripDetails[$tripId]['hotels'] = $hotelStmt->fetchAll(PDO::FETCH_ASSOC);

        $activityStmt = $pdo->prepare("SELECT id, name, city, category, activity_date, cost_nzd, description, notes FROM saved_activities WHERE user_id = ? AND trip_id = ? ORDER BY activity_date ASC, name ASC");
        $activityStmt->execute([$scopeUserId, $tripId]);
        $tripDetails[$tripId]['attractions'] = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

        // Conflict detection for the trip
        $tripDetails[$tripId]['conflicts'] = getTripConflicts($tripDetails[$tripId]['flights'], $tripDetails[$tripId]['hotels'], $tripDetails[$tripId]['attractions']);

        // Budget summary for the trip (flights + hotels + activities + custom items, all in NZD)
        $tripDetails[$tripId]['budget'] = getTripBudgetSummary($pdo, $tripId, $scopeUserId);

        $expenseStmt = $pdo->prepare("SELECT id, category, item_name, amount, currency, amount_nzd FROM budget_items WHERE user_id = ? AND trip_id = ? ORDER BY id DESC");
        $expenseStmt->execute([$scopeUserId, $tripId]);
        $tripDetails[$tripId]['expenses'] = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);
    }



} catch (PDOException $e) {
    error_log('Trip load error: ' . $e->getMessage());
}

// Flattened, JS-friendly trip data used for the "Export as PDF" feature
$pdfExportData = [];
foreach ($trips as $trip) {
    $tid = (int) $trip['id'];
    $pdfExportData[$tid] = [
        'title' => $trip['title'],
        'destination' => $trip['destination'],
        'start_date' => $trip['start_date'],
        'end_date' => $trip['end_date'],
        'notes' => $tripDetails[$tid]['notes'] ?? '',
        'flights' => $tripDetails[$tid]['flights'] ?? [],
        'hotels' => $tripDetails[$tid]['hotels'] ?? [],
    ];
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
    <link rel="stylesheet" href="../../assets/css/hamburgerMenu.css">
    <link rel="stylesheet" href="../../assets/css/calendar.css">
    <link rel="stylesheet" href="../../assets/css/conflictAlert.css">
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
            <button type="button" onclick="location.href='userProfile.php'">
                User Profile
            </button>
        </li>
        <li>
            <button type="button" onclick="location.href='budget.php'">
                Budget
            </button>
        </li>

        <li>
            <button type="button" onclick="location.href='settings.php'">
                Settings
            </button>
        </li>
        <li>
            <button type="button" onclick="location.href='helpDesk.php'">
                Contact us
            </button>
        </li>
        <li>
            <button type="button" onclick="location.href='/AUT-Web-Based-Travel-Planner/assets/api/auth/signout.php'">
                Sign Out
            </button>
        </li>
    </ul>
</nav>

<div class="dashboard-hero">
    <div class="hero-overlay">    
        <h1>CampusTrips</h1>
        <h2>AUT Web-Based Travel Planner</h2>
        <?php if (isset($_SESSION['name'])): ?>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! Here you can manage your travel plans, view your itinerary, and access exclusive travel deals.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($tripActionMessage !== '' || $sharedLinkError !== ''): ?>
    <div class="dashboard-flash-banner">
        <?php if ($tripActionMessage !== ''): ?>
            <p class="dashboard-flash-success"><?php echo htmlspecialchars($tripActionMessage); ?></p>
        <?php endif; ?>
        <?php if ($sharedLinkError !== ''): ?>
            <p class="dashboard-flash-error"><?php echo htmlspecialchars($sharedLinkError); ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

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
 

<!-- Saved trips -->


 <div class="savedTrips">
    
    <div class="savedTrips-header">

        <div class="savedTrips-title">
            <h2>Your Saved Trips</h2>
            <p>View and manage your saved trips here.</p>
        </div>

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

        <!--Creating New Trip Card -->
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

        <?php if (count($activeTrips) === 0): ?>
            <div class="trip-card empty-trip-card">
                <div class="trip-card-body">
                    <p>No saved trips yet.</p>
                    <p>Add a new trip to see it here.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($activeTrips as $trip): ?>
                <?php $tripId = (int)$trip['id']; ?>
                <?php
                $currentDate = date("Y-m-d");
                    if($trip['end_date'] <= $currentDate) {
                    continue;
                    }
                $tripId = (int) $trip['id'];
                $category = getCategoryDetails($trip['travel_style']);
                $isOwner = $tripDetails[$tripId]['is_owner'];
                $canManage = $isOwner || ($tripDetails[$tripId]['access_level'] === 'edit');
                $ownerName = $tripDetails[$tripId]['owner_name'];
                ?>
                <div class="trip-card saved-trip-card">
                    <span class="bookmark-ribbon" style="--ribbon-color: <?php echo htmlspecialchars($category['color']); ?>" title="<?php echo htmlspecialchars($category['label']); ?>">
                        <span class="bookmark-ribbon-label"><?php echo htmlspecialchars(strtoupper(substr($category['label'], 0, 1))); ?></span>
                    </span>
                    <?php if (!$isOwner): ?>
                        <span class="shared-with-badge" title="Shared by <?php echo htmlspecialchars($ownerName); ?>">
                            🔗 Shared by <?php echo htmlspecialchars($ownerName); ?>
                        </span>
                    <?php endif; ?>
                    <div class="trip-card-title"><?php echo htmlspecialchars($trip['title']); ?></div>
                    <div class="trip-card-detail">
                        <strong>Destination</strong>
                        <span><?php echo htmlspecialchars($trip['destination']); ?></span>
                    </div>
                    <div class="trip-card-detail">
                        <strong>Dates</strong>
                        <span><?php echo date('d M Y', strtotime($trip['start_date'])); ?> → <?php echo date('d M Y', strtotime($trip['end_date'])); ?></span>
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

                    <div class="trip-card-detail">
                        <strong>Total Cost</strong>
                        <span>NZD <?php echo number_format($tripDetails[$tripId]['budget']['grand_total'] ?? 0, 2); ?></span>
                    </div>
                    
                    <!--Added style to separate the two buttons-->
                    <div class="trip-card-actions"
                    style="display:flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                        <button type="button" class="trip-action-btn view-details-btn" data-trip-id="<?php echo $tripId; ?>">View Details</button>
                        <?php if ($isOwner): ?>
                            <form method="POST" class="trip-delete-form" onsubmit="return confirm('Delete this trip? This cannot be undone.');">
                                <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                                <button type="submit" name="delete_trip" class="trip-action-btn delete-trip-btn">Delete Trip</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="trip-details-template" id="trip-details-template-<?php echo $tripId; ?>" style="display:none;">
                    <div class="trip-details-summary">
                        <div class="trip-details-summary-header">
                            <h4><?php echo htmlspecialchars($trip['title']); ?></h4>
                            <button type="button" class="trip-action-btn export-pdf-btn" data-trip-id="<?php echo $tripId; ?>">Export as PDF</button>
                        </div>
                        <?php if (!$isOwner): ?>
                            <p class="shared-with-note">
                                🔗 Shared by <?php echo htmlspecialchars($ownerName); ?> —
                                <?php echo $canManage ? 'you can edit this trip.' : 'view only.'; ?>
                            </p>
                        <?php endif; ?>
                        <p><strong>Destination:</strong> <?php echo htmlspecialchars($trip['destination']); ?></p>
                        <!-- Dates shown as dd Month yyyy (e.g. 05 October 2026) -->
                        <p><strong>Dates:</strong> <?php echo date('d F Y', strtotime($trip['start_date'])); ?> → <?php echo date('d F Y', strtotime($trip['end_date'])); ?></p>
                        <p><strong>Trip Duration:</strong> <?php echo $duration; ?></p>
                    </div>

                    <div class="trip-details-section trip-flight-expense-section">
                        <div class="trip-flights-column">
                            <h5>Flights</h5><?php if (empty($tripDetails[$tripId]['flights'])): ?>
                                <p class="trip-details-empty">No flights added for this trip yet.</p>
                                <?php if ($isOwner): ?>
                                    <button type="button" class="trip-action-link trip-quick-add-btn" data-search-target="flights">Add Flight</button>
                                <?php endif; ?>
                            <?php else: ?>

                                <div class="saved-items-grid flight-stack">
                                    <?php foreach ($tripDetails[$tripId]['flights'] as $flight): ?>
                                        <div class="collapsible-card">
                                            <button type="button" class="collapsible-header">
                                                <div>
                                                    <strong>
                                                        <?php echo htmlspecialchars($flight['airline']); ?>
                                                        <?php echo htmlspecialchars($flight['flight_number']); ?>
                                                    </strong>
                                                    <span class="collapsed-date"><?php echo date('d M Y', strtotime($flight['departure_datetime'])); ?></span>
                                                </div>
                                                <span class="collapse-arrow">⌄</span>
                                            </button>
                                    
                                    <div class="collapsible-content">

                                        <div class="saved-item-meta">

                                            <div class="detail-row">
                                                <strong>Route:</strong>
                                                    <span>  
                                                        <?php echo htmlspecialchars(
                                                            $flight['departure_city'] .
                                                            ' → ' .
                                                            $flight['arrival_city']
                                                        ); ?>
                                                    </span>
                                            </div>

                                            <div class="detail-row">
                                                <strong>From:</strong>    
                                                    <span>
                                                        <?php echo htmlspecialchars(
                                                            $flight['departure_airport']
                                                        ); ?>
                                                    </span>
                                            </div>

                                            <div class="detail-row">
                                                <strong>To:</strong>    
                                                    <span>
                                                        <?php echo htmlspecialchars(
                                                            $flight['arrival_airport']
                                                        ); ?>
                                                    </span>
                                            </div>

                                            <div class="detail-row">
                                                <strong>Departure:</strong>    
                                                    <span>
                                                        <?php echo htmlspecialchars(
                                                            $flight['departure_datetime']
                                                                ? date(
                                                                    'd M Y H:i',
                                                                    strtotime($flight['departure_datetime'])
                                                                )
                                                                : 'TBD'
                                                        ); ?>
                                                    </span>
                                            </div>
                                            
                                            <div class="detail-row">
                                                <strong>Arrival:</strong>    
                                                    <span>
                                                        
                                                        <?php echo htmlspecialchars(
                                                            $flight['arrival_datetime']
                                                                ? date(
                                                                    'd M Y H:i',
                                                                    strtotime($flight['arrival_datetime'])
                                                                )
                                                                : 'TBD'
                                                        ); ?>
                                                    </span>
                                            </div>

                                            <div class="detail-row">
                                                <strong>Duration:</strong>    
                                                    <span>
                                                        <?php echo htmlspecialchars(
                                                            floor($flight['duration_minutes'] / 60)
                                                            . 'h '
                                                            . ($flight['duration_minutes'] % 60)
                                                            . 'm'
                                                        ); ?>
                                                    </span>
                                            </div>

                                            <div class="detail-row">
                                                <strong>Stops:</strong>   
                                                    <span>
                                                        <?php echo htmlspecialchars(
                                                            $flight['stops'] == 0
                                                                ? 'Direct'
                                                                : $flight['stops'] .
                                                                ' stop' .
                                                                ($flight['stops'] > 1 ? 's' : '')
                                                        ); ?>
                                                    </span>
                                            </div>
                                        </div>

                                        <div class="saved-item-footer">

                                            <span>
                                                NZD
                                                <?php echo htmlspecialchars(
                                                    number_format($flight['price_nzd'], 0)
                                                ); ?>
                                            </span>

                                            <span>
                                                <?php echo htmlspecialchars(
                                                    $flight['cabin_class'] ?: 'Economy'
                                                ); ?>
                                            </span>

                                        </div>

                                        <?php if ($canManage): ?>
                                        <div class="saved-item-actions">

                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Remove this flight from the trip?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="trip_id"
                                                    value="<?php echo $tripId; ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="item_type"
                                                    value="flight"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="item_id"
                                                    value="<?php echo htmlspecialchars($flight['id']); ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="delete_saved_item"
                                                    class="saved-item-remove-btn"
                                                >
                                                    Remove
                                                </button>

                                            </form>

                                        </div>
                                        <?php endif; ?>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>
                    <?php endif; ?>
                    </div>
                             
                    <?php
                        $expenseTotalNZD = 0;

                        foreach ($tripDetails[$tripId]['expenses'] as $expense) {
                            $expenseTotalNZD += (float)$expense['amount_nzd'];
                        }
                        ?>
                        <div class="trip-expense-column">

                            <div class="expense-log-header">
                                <div>
                                    <h5>Expense Log</h5>

                                    <p class="expense-total">
                                        Total Expenses:
                                        <strong>NZD <span class="expense-total-value"><?php echo number_format($expenseTotalNZD, 2); ?></span></strong>
                                    </p>
                                </div>

                                <?php if ($canManage): ?>
                                <button
                                    type="button"
                                    class="add-expense-btn"
                                    data-trip-id="<?php echo $tripId; ?>">
                                    <span>+</span>
                                    Add Expense
                                </button>
                                <?php endif; ?>
                            </div>

                            <div
                                class="expense-list"
                                data-trip-id="<?php echo $tripId; ?>">

                                <?php if (empty($tripDetails[$tripId]['expenses'])): ?>

                                    <p class="expense-empty">
                                        No expenses added yet.
                                    </p>

                                <?php else: ?>

                                    <?php foreach ($tripDetails[$tripId]['expenses'] as $expense): ?>

                                        <div
                                            class="expense-item"
                                            data-expense-id="<?php echo (int)$expense['id']; ?>">

                                            <div class="expense-item-main">

                                                <span class="expense-type">
                                                    <?php echo htmlspecialchars($expense['category']); ?>
                                                </span>

                                                <strong class="expense-name">
                                                    <?php echo htmlspecialchars($expense['item_name']); ?>
                                                </strong>

                                            </div>

                                            <div class="expense-item-right">

                                                <strong class="expense-cost">
                                                    <?php
                                                    echo htmlspecialchars($expense['currency'])
                                                        . ' '
                                                        . number_format((float)$expense['amount'], 2);
                                                    ?>
                                                </strong>

                                                <?php if ($expense['currency'] !== 'NZD'): ?>
                                                    <span class="expense-nzd-value">
                                                        ≈ NZD <?php echo number_format(
                                                            (float)$expense['amount_nzd'],
                                                            2
                                                        ); ?>
                                                    </span>
                                                <?php endif; ?>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                    <div class="trip-details-section">
                        <h5>Hotels</h5>
                        <?php if (empty($tripDetails[$tripId]['hotels'])): ?>
                            <p class="trip-details-empty">No hotel plans added for this trip yet.</p>
                            <?php if ($isOwner): ?>
                                <button type="button" class="trip-action-link trip-quick-add-btn" data-search-target="accommodation">Add Hotel</button>
                            <?php endif; ?>
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
                                        <?php if ($canManage): ?>
                                        <div class="saved-item-actions">
                                            <form method="POST" onsubmit="return confirm('Remove this accommodation from the trip?');">
                                                <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                                                <input type="hidden" name="item_type" value="hotel">
                                                <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($hotel['id']); ?>">
                                                <button type="submit" name="delete_saved_item" class="saved-item-remove-btn">Remove</button>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="trip-details-section">
                        <h5>Attractions</h5>
                        <?php if (empty($tripDetails[$tripId]['attractions'])): ?>
                            <p class="trip-details-empty">No attractions added for this trip yet.</p>
                            <?php if ($isOwner): ?>
                                <button type="button" class="trip-action-link trip-quick-add-btn" data-search-target="activities">Add Attraction</button>
                            <?php endif; ?>
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
                                            <?php if ($canManage): ?>
                                             <div class="activity-date-setting">

                                                <strong>Date:</strong>

                                                <form method="POST" class="activity-date-form">

                                                    <input
                                                        type="hidden"
                                                        name="trip_id"
                                                        value="<?php echo $tripId; ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="activity_id"
                                                        value="<?php echo (int)$attraction['id']; ?>"
                                                    >

                                                    <input
                                                        type="date"
                                                        name="activity_date"
                                                        value="<?php echo htmlspecialchars($attraction['activity_date'] ?? ''); ?>"
                                                        min="<?php echo htmlspecialchars($trip['start_date']); ?>"
                                                        max="<?php echo htmlspecialchars($trip['end_date']); ?>"
                                                        required
                                                    >

                                                    <button
                                                        type="submit"
                                                        name="save_activity_date"
                                                        class="save-activity-date-btn"
                                                    >
                                                        Save
                                                    </button>

                                                </form>

                                            </div>
                                            <?php else: ?>
                                                <span><strong>Date:</strong> <?php echo htmlspecialchars($attraction['activity_date'] ? date('d M Y', strtotime($attraction['activity_date'])) : 'TBD'); ?></span>
                                            <?php endif; ?>
                                            <span><strong>Cost:</strong> NZD <?php echo htmlspecialchars(number_format($attraction['cost_nzd'], 0)); ?></span>
                                        </div>
                                        <p class="saved-item-description"><?php echo htmlspecialchars($attraction['description']); ?></p>
                                        <?php if ($canManage): ?>
                                        <div class="saved-item-actions">
                                            <form method="POST" onsubmit="return confirm('Remove this activity from the trip?');">
                                                <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                                                <input type="hidden" name="item_type" value="activity">
                                                <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($attraction['id']); ?>">
                                                <button type="submit" name="delete_saved_item" class="saved-item-remove-btn">Remove</button>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="trip-details-section">
                        <h5>Estimated Travel Duration</h5>

                        <?php if (empty($tripDetails[$tripId]['flights'])): ?>

                            <p class="trip-details-empty">
                                Add trip items to estimate travel duration.
                            </p>

                        <?php else: ?>

                            <?php
                                $totalFlightMinutes = 0;

                                foreach ($tripDetails[$tripId]['flights'] as $flight) {
                                    $totalFlightMinutes += (int)$flight['duration_minutes'];
                                }

                                $totalHours = floor($totalFlightMinutes / 60);
                                $totalMinutes = $totalFlightMinutes % 60;
                            ?>

                            <div class="saved-items-grid">

                                <div class="saved-item-card saved-item-activity-card">

                                    <div class="saved-item-header">

                                        <h4>
                                            <?php echo $totalHours . ' hours ' . $totalMinutes . ' minutes'; ?>
                                        </h4>

                                        <span class="saved-item-badge">
                                            Estimate
                                        </span>

                                    </div>

                                    <div class="saved-item-meta">

                                        <?php foreach ($tripDetails[$tripId]['flights'] as $flight): ?>

                                            <?php
                                                $flightHours = floor($flight['duration_minutes'] / 60);
                                                $flightMinutes = $flight['duration_minutes'] % 60;
                                            ?>

                                            <span>
                                                <strong>
                                                    <?php echo htmlspecialchars(
                                                        $flight['departure_airport']
                                                        . ' → '
                                                        . $flight['arrival_airport']
                                                    ); ?>:
                                                </strong>

                                                <?php echo $flightHours . 'h ' . $flightMinutes . 'm'; ?>
                                            </span>

                                        <?php endforeach; ?>

                                        <span>
                                            <strong>Airport to Hotel:</strong> --
                                        </span>

                                        <span>
                                            <strong>Hotel to Activity:</strong> --
                                        </span>

                                        <span>
                                            <strong>Activity to Airport:</strong> --
                                        </span>

                                    </div>

                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="trip-details-section">
                        <h5>Budget</h5>
                        <p style="margin: 4px 0 8px 0;">
                            <strong>Total Cost:</strong> NZD <?php echo number_format($tripDetails[$tripId]['budget']['grand_total'] ?? 0, 2); ?>
                        </p>
                        <?php if ($canManage): ?>
                            <a href="budget.php?trip_id=<?php echo $tripId; ?>"
                               style="background: none; font-weight: normal; font-size: 0.85em; color: #2563eb; text-decoration: underline; padding: 0;">
                                View Budget Breakdown
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="trip-details-section">
                        <h5>Notes</h5>
                        <?php if ($canManage): ?>
                            <form method="POST" class="trip-notes-form">
                                <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                                <textarea name="trip_notes" rows="6" placeholder="Add notes for this trip..."><?php echo htmlspecialchars($tripDetails[$tripId]['notes'] !== '' ? $tripDetails[$tripId]['notes'] : ''); ?></textarea>
                                <div class="trip-details-actions">
                                    <button type="submit" name="save_trip_notes" class="trip-action-btn">Save Notes</button>
                                </div>
                            </form>
                        <?php elseif ($tripDetails[$tripId]['notes'] === ''): ?>
                            <p class="trip-details-empty">No notes have been added for this trip yet.</p>
                        <?php else: ?>
                            <p class="shared-trip-notes"><?php echo nl2br(htmlspecialchars($tripDetails[$tripId]['notes'])); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ($isOwner): ?>
                    <?php $share = $tripDetails[$tripId]['share']; ?>
                    <div class="trip-details-section trip-share-section">
                        <h5>Share Trip</h5>

                        <div class="share-privacy-row">
                            <span class="share-privacy-label">Trip Visibility</span>
                            <form method="POST" class="privacy-toggle-form">
                                <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                                <input type="hidden" name="is_private" value="<?php echo $trip['is_private'] ? '0' : '1'; ?>">
                                <button
                                    type="submit"
                                    name="set_trip_privacy"
                                    class="privacy-toggle-btn <?php echo $trip['is_private'] ? 'is-private' : 'is-public'; ?>"
                                    title="Click to switch to <?php echo $trip['is_private'] ? 'Public' : 'Private'; ?>"
                                >
                                    <?php echo $trip['is_private'] ? 'Private' : 'Public'; ?>
                                </button>
                            </form>
                        </div>
                        <p class="trip-details-empty">
                            <?php echo $trip['is_private']
                                ? 'This trip is private — a shared link will not open for anyone, even while enabled.'
                                : 'This trip is public — an enabled shared link below can be opened by anyone who has it.'; ?>
                        </p>

                        <?php if (!$share): ?>
                            <form method="POST" class="share-generate-form">
                                <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                                <button type="submit" name="generate_share_link" class="trip-action-btn">Create Shareable Link</button>
                            </form>
                        <?php else: ?>
                            <?php $shareUrl = buildShareUrl($share['token']); ?>
                            <div class="share-link-box">
                                <input
                                    type="text"
                                    class="share-link-input"
                                    readonly
                                    value="<?php echo htmlspecialchars($shareUrl); ?>"
                                    onclick="this.select();"
                                    aria-label="Shareable trip link"
                                >
                                <button type="button" class="trip-action-link copy-share-link-btn" data-link="<?php echo htmlspecialchars($shareUrl); ?>">Copy Link</button>
                            </div>

                            <div class="share-privacy-row" style="margin-top:0.85rem;">
                                <span class="share-privacy-label">Link Permission</span>
                                <form method="POST" class="access-toggle-form">
                                    <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                                    <input type="hidden" name="access_level" value="<?php echo $share['access_level'] === 'edit' ? 'view' : 'edit'; ?>">
                                    <button
                                        type="submit"
                                        name="set_share_access"
                                        class="access-toggle-btn <?php echo $share['access_level'] === 'edit' ? 'is-edit' : 'is-view'; ?>"
                                        title="Click to switch to <?php echo $share['access_level'] === 'edit' ? 'Can View' : 'Can Edit'; ?>"
                                    >
                                        <?php echo $share['access_level'] === 'edit' ? 'Can Edit' : 'Can View'; ?>
                                    </button>
                                </form>
                            </div>
                            <p class="trip-details-empty">
                                <?php echo $share['access_level'] === 'edit'
                                    ? 'Anyone with this link can view it freely, but must sign in before they can add, remove, or change anything.'
                                    : 'Anyone with this link can only view this trip — no sign-in grants them the ability to change it.'; ?>
                            </p>

                            <div class="share-meta-grid">
                                <span>
                                    <strong>Status:</strong>
                                    <span class="share-status-badge <?php echo $share['is_active'] ? 'active' : 'disabled'; ?>">
                                        <?php echo $share['is_active'] ? 'Active' : 'Disabled'; ?>
                                    </span>
                                </span>
                                <span><strong>Generated:</strong> <?php echo date('d M Y, H:i', strtotime($share['created_at'])); ?></span>
                                <span>
                                    <strong>Views:</strong>
                                    <?php echo ((int) $share['view_count']) > 0
                                        ? number_format((int) $share['view_count']) . ' time' . ((int) $share['view_count'] > 1 ? 's' : '')
                                        : 'Not viewed yet'; ?>
                                </span>
                            </div>

                            <div class="share-actions">
                                <form method="POST">
                                    <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                                    <input type="hidden" name="activate" value="<?php echo $share['is_active'] ? '0' : '1'; ?>">
                                    <button
                                        type="submit"
                                        name="toggle_share_link"
                                        class="trip-action-btn <?php echo $share['is_active'] ? 'disable-share-btn' : ''; ?>"
                                    >
                                        <?php echo $share['is_active'] ? 'Disable Link' : 'Enable Link'; ?>
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Generate a new link for this trip? The current link will stop working immediately and cannot be reactivated.');">
                                    <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                                    <button type="submit" name="generate_share_link" class="trip-action-link">Generate New Link</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
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
    Trip Category
    <select name="travel_style" required>
        <?php
                            $selectedCategory = $_POST['travel_style'] ?? 'Personal Trip';
                            foreach (getTripCategories() as $key => $meta):
                                ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $selectedCategory === $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($meta['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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

 
<!-- Completed Trips -->
 <?php include 'completedTrip.php'; ?>

<div id="trip-details-modal" class="modal-backdrop" aria-hidden="true">
    <div class="modal-window trip-details-window">
        <div class="modal-header">
            <h3>Trip Details</h3>
            <button id="close-trip-details-modal" class="modal-close" type="button">×</button>
        </div>
        <div class="modal-body" id="trip-details-content"></div>
    </div>
</div>

<!-- Floating Calendar Button -->
<button id="floating-calendar-btn" class="floating-calendar-btn" title="Open Calendar" aria-label="Open trip calendar">📅</button>

<!-- Calendar Modal -->
<div id="calendar-modal-backdrop" class="calendar-modal-backdrop">
    <div class="calendar-modal">
        <div class="calendar-modal-header">
            <h2>Trip Calendar</h2>
            <button id="calendar-close-btn" class="calendar-close-btn" type="button">×</button>
        </div>
        
        <div class="calendar-controls">
            <button class="calendar-nav-btn" id="prev-month">← Prev</button>
            <div class="calendar-month-year" id="calendar-month-year"></div>
            <button class="calendar-nav-btn" id="next-month">Next →</button>
        </div>

        <div class="calendar-weekdays">
            <div class="calendar-weekday">Sun</div>
            <div class="calendar-weekday">Mon</div>
            <div class="calendar-weekday">Tue</div>
            <div class="calendar-weekday">Wed</div>
            <div class="calendar-weekday">Thu</div>
            <div class="calendar-weekday">Fri</div>
            <div class="calendar-weekday">Sat</div>
        </div>

        <div class="calendar-days" id="calendar-days"></div>

        <div class="calendar-trips-list" id="calendar-trips-list"></div>
    </div>
</div>

<div
        id="expense-modal"
        class="modal-backdrop"
        aria-hidden="true">

        <div class="modal-window expense-modal-window">

            <div class="modal-header">
                <h3 id="expense-modal-title">Add Expense</h3>

                <button
                    type="button"
                    class="modal-close"
                    id="close-expense-modal">
                    ×
                </button>
            </div>

            <div class="modal-body">

                <form id="expense-form" class="expense-form" method="POST">

                    <!-- Which trip this expense belongs to -->
                    <input
                        type="hidden"
                        id="expense-trip-id"
                        name="trip_id">

                    <!-- Used later when editing an expense -->
                    <input
                        type="hidden"
                        id="expense-edit-id"
                        name="expense_edit_id">

                    <label for="expense-type">
                        Expense Type
                    </label>

                    <select
                        id="expense-type"
                        name="expense_type"
                        required>
                        
                        <option value="">Choose expense type</option>
                        <option value="Flights">Flights</option>
                        <option value="Accommodation">Accommodation</option>
                        <option value="Activities">Activities</option>
                        <option value="Food">Food</option>
                        <option value="Transport">Transport</option>
                        <option value="Insurance">Insurance</option>
                        <option value="Shopping">Shopping</option>
                        <option value="Other">Other</option>
                    </select>

                    <label for="expense-name">
                        Expense Name
                    </label>

                    <input
                        type="text"
                        id="expense_name"
                        name="expense_name"
                        placeholder="e.g. Dinner at restaurant"
                        required>

                    <label for="expense-cost">
                        Cost
                    </label>

                    <div class="expense-cost-row">

                        <input
                            type="number"
                            id="expense_cost"
                            name="expense_amount"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            required>

                        <select
                            id="expense_currency"
                            name="expense_currency"
                            required>

                            <option value="NZD">NZD</option>
                            <option value="AUD">AUD</option>
                            <option value="USD">USD</option>
                            <option value="PHP">PHP</option>
                            <option value="JPY">JPY</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                        </select>

                    </div>

                    <div
                        class="expense-form-error"
                        id="expense-form-error">
                    </div>

                    <div class="modal-actions">

                        <button
                            type="button"
                            class="modal-btn modal-cancel"
                            id="cancel-expense-modal">
                            Cancel
                        </button>

                        <button
                            type="submit"
                            name="add_expense"
                            class="modal-btn modal-save">
                            Save Expense
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

<!-- Trips data for calendar (as JSON) -->
<script>
    const tripsData = <?php echo json_encode($trips); ?>;
</script>

<!-- Trip data for the "Export as PDF" feature (as JSON) -->
<script>
    const tripPdfData = <?php echo json_encode($pdfExportData); ?>;
</script>

<!--
    "Export as PDF" prints via the browser's own print dialog, which already
    gives a preview pane plus layout, copies and destination controls
    (including "Save as PDF") — no PDF library needed. This container is
    filled in per-trip by exportTripPDF() and is the only thing left visible
    when printing; see the ".pdf-print-area" rules in dashboard.css.
-->
<div class="pdf-print-area" id="pdf-print-area"></div>

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

       // --------- Trip modal behaviour ----------
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

    // Wipes any leftover draft/conflict data so the form opens blank next time
    function clearTripFormFields() {
        const tripForm = tripModal.querySelector('.modal-form');
        if (!tripForm) return;

        tripForm.querySelectorAll('input[type="text"], input[type="date"], textarea')
            .forEach(function (field) {
                field.value = '';
            });

        const categorySelect = tripForm.querySelector('select[name="travel_style"]');
        if (categorySelect) categorySelect.value = 'Personal Trip'; // Reset to the first option
        

        const errorsBox = tripForm.parentElement.querySelector('.modal-errors');
        if (errorsBox) errorsBox.remove();
    }

    // Single source of truth for "give up on this trip attempt"
    function cancelTripModal_() {
        hideTripModal();
        clearTripFormFields();
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

    closeTripModal.addEventListener('click', cancelTripModal_);
    cancelTripModal.addEventListener('click', cancelTripModal_);
    tripModal.addEventListener('click', function(event) {
        if (event.target === tripModal) {
            cancelTripModal_();
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

    <?php if (isset($_GET['open_trip'])): ?>
        // Arrived here via a shared trip link — open that trip's details
        // straight away instead of leaving the visitor to find it in the list.
        window.addEventListener('DOMContentLoaded', function () {
            const openBtn = document.querySelector('.view-details-btn[data-trip-id="<?php echo (int) $_GET['open_trip']; ?>"]');
            if (openBtn) {
                openBtn.click();
            }
        });
    <?php endif; ?>

    // ---------- Collapsible trip item cards ----------
    tripDetailsContent.addEventListener('click', function(event) {
        const header = event.target.closest('.collapsible-header');

        if (!header) {
            return;
        }

        const clickedCard = header.closest('.collapsible-card');

        if (!clickedCard) {
            return;
        }

        const wasExpanded = clickedCard.classList.contains('expanded');

        // Close all cards first
        tripDetailsContent
            .querySelectorAll('.collapsible-card.expanded')
            .forEach(function(card) {
                card.classList.remove('expanded');
            });

        // If the clicked card was closed before, open it
        if (!wasExpanded) {
            clickedCard.classList.add('expanded');
        }
    });


    // ---------- Expense Log UI ----------

    const expenseModal =
        document.getElementById('expense-modal');

    const expenseForm =
        document.getElementById('expense-form');

    const expenseTripId =
        document.getElementById('expense-trip-id');

    const expenseEditId =
        document.getElementById('expense-edit-id');

    const expenseType =
        document.getElementById('expense-type');

    const expenseName =
        document.getElementById('expense_name');

    const expenseCost =
        document.getElementById('expense_cost');

    const expenseCurrency =
        document.getElementById('expense_currency');

    const expenseFormError =
        document.getElementById('expense-form-error');

    const expenseModalTitle =
        document.getElementById('expense-modal-title');

    const closeExpenseModal =
        document.getElementById('close-expense-modal');

    const cancelExpenseModal =
        document.getElementById('cancel-expense-modal');


    let expensesByTrip = {};

    let nextExpenseId = 1;

    function showExpenseModal(tripId) {

        expenseForm.reset();

        expenseTripId.value = tripId;
        expenseEditId.value = '';

        expenseFormError.textContent = '';
        expenseModalTitle.textContent = 'Add Expense';

        expenseModal.style.display = 'flex';
        expenseModal.setAttribute('aria-hidden', 'false');
    }


    function hideExpenseModal() {

        expenseModal.style.display = 'none';
        expenseModal.setAttribute('aria-hidden', 'true');

        expenseForm.reset();

        expenseEditId.value = '';
        expenseFormError.textContent = '';
    }

    tripDetailsContent.addEventListener('click', function(event) {

        const button =
            event.target.closest('.add-expense-btn');

        if (!button) {
            return;
        }

        const tripId =
            button.getAttribute('data-trip-id');

        showExpenseModal(tripId);
    });

    closeExpenseModal.addEventListener(
        'click',
        hideExpenseModal
    );

    cancelExpenseModal.addEventListener(
        'click',
        hideExpenseModal
    );

    expenseModal.addEventListener('click', function(event) {

        if (event.target === expenseModal) {
            hideExpenseModal();
        }

    });

    expenseForm.addEventListener('submit', function(event) {

        expenseFormError.textContent = '';

        const tripId =
            expenseTripId.value;

        const type =
            expenseType.value;

        const name =
            expenseName.value.trim();

        const cost =
            Number(expenseCost.value);

        const currency =
            expenseCurrency.value;


        if (!tripId) {
            event.preventDefault();

            expenseFormError.textContent =
                'Unable to identify the selected trip.';
            return;
        }


        if (!type) {
            event.preventDefault();

            expenseFormError.textContent =
                'Please choose an expense type.';
            return;
        }


        if (!name) {
            event.preventDefault();

            expenseFormError.textContent =
                'Please enter an expense name.';
            return;
        }


        if (!Number.isFinite(cost) || cost <= 0) {
            event.preventDefault();

            expenseFormError.textContent =
                'Please enter a valid cost.';
            return;
        }


        if (!currency) {
            event.preventDefault();

            expenseFormError.textContent =
                'Please choose a currency.';
            return;
        }

    });

    function renderExpenses(tripId) {

        const expenseList =
            tripDetailsContent.querySelector(
                `.expense-list[data-trip-id="${tripId}"]`
            );

        if (!expenseList) {
            return;
        }


        const expenses =
            expensesByTrip[tripId] || [];


        if (expenses.length === 0) {

            expenseList.innerHTML = `
                <p class="expense-empty">
                    No expenses added yet.
                </p>
            `;

        } else {

            expenseList.innerHTML =
                expenses.map(function(expense) {

                    return `
                        <div
                            class="expense-item"
                            data-expense-id="${expense.id}">

                            <div class="expense-item-main">

                                <span class="expense-type">
                                    ${escapeHTML(expense.type)}
                                </span>

                                <strong class="expense-name">
                                    ${escapeHTML(expense.name)}
                                </strong>

                            </div>

                            <div class="expense-item-right">

                                <strong class="expense-cost">
                                    NZD ${expense.cost.toFixed(2)}
                                </strong>

                                <div class="expense-actions">

                                    <button
                                        type="button"
                                        class="expense-edit-btn"
                                        data-trip-id="${tripId}"
                                        data-expense-id="${expense.id}">
                                        Edit
                                    </button>

                                    <button
                                        type="button"
                                        class="expense-delete-btn"
                                        data-trip-id="${tripId}"
                                        data-expense-id="${expense.id}">
                                        Delete
                                    </button>

                                </div>

                            </div>

                        </div>
                    `;

                }).join('');

        }


        const total =
            expenses.reduce(function(sum, expense) {
                return sum + expense.cost;
            }, 0);


        const totalDisplay =
            tripDetailsContent.querySelector(
                '.expense-total-value'
            );

        if (totalDisplay) {
            totalDisplay.textContent =
                total.toFixed(2);
        }
    }

    function escapeHTML(value) {

        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

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

    // ---------- Export trip itinerary as PDF ----------

    tripDetailsContent.addEventListener('click', function(event) {
        const button = event.target.closest('.export-pdf-btn');
        if (!button) {
            return;
        }
        exportTripPDF(button.getAttribute('data-trip-id'));
    });

    // ---------- Copy shareable trip link ----------

    tripDetailsContent.addEventListener('click', function(event) {
        const button = event.target.closest('.copy-share-link-btn');
        if (!button) {
            return;
        }

        const link = button.getAttribute('data-link');
        const originalLabel = button.textContent;

        function showCopied() {
            button.textContent = 'Copied!';
            window.setTimeout(function () {
                button.textContent = originalLabel;
            }, 1800);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(link).then(showCopied).catch(function () {
                button.textContent = 'Copy failed';
                window.setTimeout(function () { button.textContent = originalLabel; }, 1800);
            });
        } else {
            // Fallback for non-HTTPS contexts (e.g. plain http://localhost)
            // where the async Clipboard API isn't available.
            const input = button.previousElementSibling;
            if (input && input.select) {
                input.select();
                try {
                    document.execCommand('copy');
                    showCopied();
                } catch (err) {
                    button.textContent = 'Copy failed';
                    window.setTimeout(function () { button.textContent = originalLabel; }, 1800);
                }
                window.getSelection().removeAllRanges();
            }
        }
    });

    function formatDateOnly(value) {
        if (!value) return 'TBD';
        const parsed = new Date(value.includes('T') ? value : value + 'T00:00:00');
        if (isNaN(parsed)) return 'TBD';
        return parsed.toLocaleDateString('en-NZ', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function formatDateTime(value) {
        if (!value) return 'TBD';
        const parsed = new Date(value.includes('T') ? value : value.replace(' ', 'T'));
        if (isNaN(parsed)) return 'TBD';
        const datePart = parsed.toLocaleDateString('en-NZ', { day: '2-digit', month: 'short', year: 'numeric' });
        const timePart = parsed.toLocaleTimeString('en-NZ', { hour: '2-digit', minute: '2-digit', hour12: false });
        return datePart + ', ' + timePart;
    }

    function formatFlightDuration(minutes) {
        const total = Number(minutes) || 0;
        return Math.floor(total / 60) + 'h ' + (total % 60) + 'm';
    }

    function formatMoney(value) {
        const amount = Number(value);
        return 'NZD ' + (Number.isFinite(amount) ? amount.toFixed(2) : '0.00');
    }

    function printField(label, value) {
        return '<p class="pdf-field"><strong>' + escapeHTML(label) + ':</strong> ' + (value || 'TBD') + '</p>';
    }

    function buildPrintableItinerary(trip) {
        const flightsHtml = (!trip.flights || trip.flights.length === 0)
            ? '<p class="pdf-empty">No flights have been added for this trip yet.</p>'
            : trip.flights.map(function (flight) {
                return '<div class="pdf-item">'
                    + '<h3>' + escapeHTML(flight.airline + ' ' + flight.flight_number) + '</h3>'
                    + printField('Route', escapeHTML(flight.departure_city + ' (' + flight.departure_airport + ') → ' + flight.arrival_city + ' (' + flight.arrival_airport + ')'))
                    + printField('Departure', formatDateTime(flight.departure_datetime))
                    + printField('Arrival', formatDateTime(flight.arrival_datetime))
                    + printField('Duration / Stops', formatFlightDuration(flight.duration_minutes) + '  •  ' + (flight.stops == 0 ? 'Direct' : flight.stops + ' stop' + (flight.stops > 1 ? 's' : '')))
                    + printField('Cabin / Price', escapeHTML(flight.cabin_class || 'Economy') + '  •  ' + formatMoney(flight.price_nzd))
                    + '</div>';
            }).join('');

        const hotelsHtml = (!trip.hotels || trip.hotels.length === 0)
            ? '<p class="pdf-empty">No accommodations have been added for this trip yet.</p>'
            : trip.hotels.map(function (hotel) {
                return '<div class="pdf-item">'
                    + '<h3>' + escapeHTML(hotel.name + ' (' + hotel.type + ')') + '</h3>'
                    + printField('Location', escapeHTML(hotel.city + ', ' + hotel.country))
                    + printField('Address', escapeHTML(hotel.address))
                    + printField('Check-in / Check-out', formatDateOnly(hotel.planned_check_in) + ' → ' + formatDateOnly(hotel.planned_check_out))
                    + printField('Rating / Price', escapeHTML(hotel.rating || 'N/A') + '  •  ' + formatMoney(hotel.price_per_night_nzd) + ' / night')
                    + '</div>';
            }).join('');

        const notesHtml = (!trip.notes || trip.notes.trim() === '')
            ? '<p class="pdf-empty">No notes have been added for this trip yet.</p>'
            : '<p class="pdf-note-text">' + escapeHTML(trip.notes) + '</p>';

        return '<h1>' + escapeHTML(trip.title || 'Trip Itinerary') + '</h1>'
            + '<p class="pdf-subtitle">CampusTrips — Trip Itinerary</p>'
            + '<hr>'
            + printField('Destination', escapeHTML(trip.destination))
            + printField('Travel Dates', formatDateOnly(trip.start_date) + ' – ' + formatDateOnly(trip.end_date))
            + printField('Group Size', 'Not specified yet')
            + '<section><h2>Flights</h2>' + flightsHtml + '</section>'
            + '<section><h2>Accommodations</h2>' + hotelsHtml + '</section>'
            + '<section><h2>Notes</h2>' + notesHtml + '</section>';
    }

    function exportTripPDF(tripId) {
        const trip = tripPdfData[tripId];
        const printArea = document.getElementById('pdf-print-area');

        if (!trip || !printArea) {
            return;
        }

        printArea.innerHTML = buildPrintableItinerary(trip);

        // Give the browser a moment to lay out the print content, then open
        // its native print dialog. That dialog is the preview: it already
        // offers layout (portrait/landscape), copies, and "Save as PDF" as
        // a destination alongside any real printer, so no PDF library or
        // custom preview UI is needed here.
        window.requestAnimationFrame(function () {
            window.print();
        });
    }

    <?php if ($showModal && !isset($conflictingTripId)): ?>
        window.addEventListener('DOMContentLoaded', showTripModal);
    <?php endif; ?>

</script>
    <!-- Conflict detection js -->
    <script>
        window.TRIP_CONFLICT_DATA = {
            hasConflict: <?php echo isset($conflictingTripId) ? 'true' : 'false'; ?>,
            conflictId: <?php echo isset($conflictingTripId) ? $conflictingTripId : 'null'; ?>,
                showModal: <?php echo $showModal ? 'true' : 'false'; ?>
            };
    </script>
    <script src="../../assets/js/conflictAlert.js"></script>
    <script src="../../assets/js/calendar.js"></script>
   
</body>
</html>