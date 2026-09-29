<?php
/**
 * Public view of a trip via its shareable link — read-only by default, or
 * editable when the owner has set the link's permission to "edit" AND the
 * visitor is signed in.
 *
 * session_start() here is read-only: unlike userDashboard/Dashboard.php,
 * this page never forces a login. It only checks whether a session already
 * happens to exist, to decide whether to show edit controls or a "sign in
 * to edit" prompt.
 *
 * Every edit-mode mutation below re-resolves the token from scratch via
 * resolveUsableShare()/shareAllowsEditing() and takes the trip id/owner id
 * from THAT, never from anything posted by the client — a link only ever
 * authorizes changes to the one trip it points to.
 */

session_start();

require_once __DIR__ . '/../../assets/api/config/database.php';
require_once __DIR__ . '/../../assets/api/helpers/shareHelper.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$actionError = '';

// ---------------------------------------------------------------------
// Edit-mode mutations. Handled before any output so a successful change
// can redirect straight back to a clean GET of this same link.
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editShare = resolveUsableShare($pdo, $token);
    $canSubmitEdit = shareAllowsEditing($editShare, $_SESSION['user_id'] ?? null);

    if (!$canSubmitEdit) {
        $actionError = 'You need edit access to this link, and to be signed in, to make changes.';
    } else {
        $ownerId = (int) $editShare['owner_id'];
        $tripId = (int) $editShare['trip_id'];
        $selfUrl = 'sharedTrip.php?token=' . urlencode($token);

        try {
            if (isset($_POST['save_shared_notes'])) {
                $stmt = $pdo->prepare("UPDATE trips SET notes = ? WHERE id = ?");
                $stmt->execute([trim($_POST['trip_notes'] ?? ''), $tripId]);
                header('Location: ' . $selfUrl);
                exit();
            }

            if (isset($_POST['delete_shared_item'])) {
                $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
                $itemType = $_POST['item_type'] ?? '';
                $tables = ['flight' => 'saved_flights', 'hotel' => 'saved_accommodations', 'activity' => 'saved_activities'];

                if ($itemId && isset($tables[$itemType])) {
                    $stmt = $pdo->prepare("DELETE FROM {$tables[$itemType]} WHERE id = ? AND trip_id = ? AND user_id = ?");
                    $stmt->execute([$itemId, $tripId, $ownerId]);
                    header('Location: ' . $selfUrl);
                    exit();
                }
                $actionError = 'Unable to remove that item right now.';
            }

            if (isset($_POST['save_shared_activity_date'])) {
                $activityId = filter_input(INPUT_POST, 'activity_id', FILTER_VALIDATE_INT);
                $activityDate = trim($_POST['activity_date'] ?? '');

                if ($activityId && $activityDate !== '' && $activityDate >= $editShare['start_date'] && $activityDate <= $editShare['end_date']) {
                    $stmt = $pdo->prepare("UPDATE saved_activities SET activity_date = ? WHERE id = ? AND trip_id = ? AND user_id = ?");
                    $stmt->execute([$activityDate, $activityId, $tripId, $ownerId]);
                    header('Location: ' . $selfUrl);
                    exit();
                }
                $actionError = 'Activity date must be within the trip dates.';
            }
        } catch (PDOException $e) {
            error_log('Shared trip edit error: ' . $e->getMessage());
            $actionError = 'Unable to save that change right now.';
        }
    }
}

// ---------------------------------------------------------------------
// Resolve the link for display
// ---------------------------------------------------------------------
$state = 'invalid'; // invalid | trip_gone | private | ok
$trip = null;
$flights = [];
$hotels = [];
$activities = [];
$canEdit = false;

if ($token !== '') {
    try {
        $share = getShareByToken($pdo, $token);

        if (!$share) {
            $state = 'invalid';
        } elseif ($share['trip_exists'] === null) {
            $state = 'trip_gone';
        } elseif (!((int) $share['is_active'])) {
            $state = 'invalid';
        } elseif ((int) $share['is_private']) {
            $state = 'private';
        } else {
            $state = 'ok';
            recordShareView($pdo, (int) $share['id']);
            $trip = $share;
            $canEdit = shareAllowsEditing($share, $_SESSION['user_id'] ?? null);

            $flightStmt = $pdo->prepare("
                SELECT id, airline, flight_number, departure_city, arrival_city, departure_airport, arrival_airport,
                       departure_datetime, arrival_datetime, duration_minutes, stops, cabin_class, price_nzd
                FROM saved_flights
                WHERE user_id = ? AND trip_id = ?
                ORDER BY departure_datetime ASC
            ");
            $flightStmt->execute([$share['owner_id'], $share['trip_id']]);
            $flights = $flightStmt->fetchAll(PDO::FETCH_ASSOC);

            $hotelStmt = $pdo->prepare("
                SELECT id, name, type, city, country, address, planned_check_in, planned_check_out,
                       price_per_night_nzd, rating
                FROM saved_accommodations
                WHERE user_id = ? AND trip_id = ?
                ORDER BY planned_check_in ASC, planned_check_out ASC
            ");
            $hotelStmt->execute([$share['owner_id'], $share['trip_id']]);
            $hotels = $hotelStmt->fetchAll(PDO::FETCH_ASSOC);

            $activityStmt = $pdo->prepare("
                SELECT id, name, city, category, activity_date, cost_nzd, description
                FROM saved_activities
                WHERE user_id = ? AND trip_id = ?
                ORDER BY activity_date ASC, name ASC
            ");
            $activityStmt->execute([$share['owner_id'], $share['trip_id']]);
            $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log('Shared trip view error: ' . $e->getMessage());
        $state = 'invalid';
    }
}

$messages = [
    'invalid' => [
        'title' => 'This link is no longer available',
        'body'  => 'The trip owner may have disabled this link or generated a new one. Ask them to share the current link if you still need access.',
    ],
    'trip_gone' => [
        'title' => 'This trip no longer exists',
        'body'  => 'The trip owner has deleted this trip, so there is nothing left to show.',
    ],
    'private' => [
        'title' => 'This trip is no longer publicly accessible',
        'body'  => 'The trip owner has made this trip private. Ask them to make it public again if you still need access.',
    ],
];

$signInUrl = '../UserAuthentication/loginForm.html?redirect='
    . urlencode('/AUT-Web-Based-Travel-Planner/Pages/shared/sharedTrip.php?token=' . $token);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $state === 'ok' ? htmlspecialchars($trip['title']) . ' — Shared Trip' : 'Shared Trip'; ?></title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sharedTrip.css">
</head>
<body class="shared-trip-body">

<header class="shared-trip-brand">
    <span class="shared-trip-brand-mark">CampusTrips</span>
    <span class="shared-trip-brand-tag">Shared itinerary</span>
</header>

<?php if ($state !== 'ok'): ?>

    <main class="shared-trip-status">
        <div class="shared-trip-status-card">
            <h1><?php echo htmlspecialchars($messages[$state]['title']); ?></h1>
            <p><?php echo htmlspecialchars($messages[$state]['body']); ?></p>
        </div>
    </main>

<?php else: ?>

    <main class="shared-trip-main">
        <section class="shared-trip-header">
            <h1><?php echo htmlspecialchars($trip['title']); ?></h1>
            <div class="shared-trip-header-meta">
                <span><strong>Destination:</strong> <?php echo htmlspecialchars($trip['destination']); ?></span>
                <span><strong>Dates:</strong> <?php echo htmlspecialchars($trip['start_date']); ?> &rarr; <?php echo htmlspecialchars($trip['end_date']); ?></span>
            </div>

            <?php if ($trip['access_level'] !== 'edit'): ?>
                <p class="shared-trip-readonly-note">You're viewing a read-only shared copy of this trip.</p>
            <?php elseif ($canEdit): ?>
                <p class="shared-trip-edit-note">✓ Edit mode — signed in as <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['email'] ?? 'you'); ?>. Changes save straight to the owner's trip.</p>
            <?php else: ?>
                <div class="shared-trip-signin-banner">
                    <p>This link allows editing, but you'll need to sign in first.</p>
                    <a href="<?php echo htmlspecialchars($signInUrl); ?>" class="trip-action-btn">Sign In to Edit</a>
                </div>
            <?php endif; ?>

            <?php if ($actionError !== ''): ?>
                <p class="shared-trip-action-error"><?php echo htmlspecialchars($actionError); ?></p>
            <?php endif; ?>
        </section>

        <div class="trip-details-section">
            <h5>Flights</h5>
            <?php if (empty($flights)): ?>
                <p class="trip-details-empty">No flights have been added for this trip yet.</p>
            <?php else: ?>
                <div class="saved-items-grid">
                    <?php foreach ($flights as $flight): ?>
                        <div class="saved-item-card">
                            <div class="saved-item-header">
                                <h4><?php echo htmlspecialchars($flight['airline'] . ' ' . $flight['flight_number']); ?></h4>
                                <span class="saved-item-badge">Flight</span>
                            </div>
                            <div class="saved-item-meta">
                                <span><strong>Route:</strong> <?php echo htmlspecialchars($flight['departure_city'] . ' (' . $flight['departure_airport'] . ') → ' . $flight['arrival_city'] . ' (' . $flight['arrival_airport'] . ')'); ?></span>
                                <span><strong>Departure:</strong> <?php echo htmlspecialchars($flight['departure_datetime'] ? date('d M Y H:i', strtotime($flight['departure_datetime'])) : 'TBD'); ?></span>
                                <span><strong>Arrival:</strong> <?php echo htmlspecialchars($flight['arrival_datetime'] ? date('d M Y H:i', strtotime($flight['arrival_datetime'])) : 'TBD'); ?></span>
                                <span><strong>Stops:</strong> <?php echo htmlspecialchars($flight['stops'] == 0 ? 'Direct' : $flight['stops'] . ' stop' . ($flight['stops'] > 1 ? 's' : '')); ?></span>
                            </div>
                            <div class="saved-item-footer">
                                <span>NZD <?php echo htmlspecialchars(number_format($flight['price_nzd'], 0)); ?></span>
                                <span><?php echo htmlspecialchars($flight['cabin_class'] ?: 'Economy'); ?></span>
                            </div>
                            <?php if ($canEdit): ?>
                                <div class="saved-item-actions">
                                    <form method="POST" onsubmit="return confirm('Remove this flight from the trip?');">
                                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                        <input type="hidden" name="item_type" value="flight">
                                        <input type="hidden" name="item_id" value="<?php echo (int) $flight['id']; ?>">
                                        <button type="submit" name="delete_shared_item" class="saved-item-remove-btn">Remove</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="trip-details-section">
            <h5>Accommodations</h5>
            <?php if (empty($hotels)): ?>
                <p class="trip-details-empty">No accommodations have been added for this trip yet.</p>
            <?php else: ?>
                <div class="saved-items-grid">
                    <?php foreach ($hotels as $hotel): ?>
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
                            </div>
                            <div class="saved-item-footer">
                                <span>NZD <?php echo htmlspecialchars(number_format($hotel['price_per_night_nzd'], 0)); ?> / night</span>
                                <span><?php echo htmlspecialchars($hotel['address']); ?></span>
                            </div>
                            <?php if ($canEdit): ?>
                                <div class="saved-item-actions">
                                    <form method="POST" onsubmit="return confirm('Remove this accommodation from the trip?');">
                                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                        <input type="hidden" name="item_type" value="hotel">
                                        <input type="hidden" name="item_id" value="<?php echo (int) $hotel['id']; ?>">
                                        <button type="submit" name="delete_shared_item" class="saved-item-remove-btn">Remove</button>
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
            <?php if (empty($activities)): ?>
                <p class="trip-details-empty">No attractions have been added for this trip yet.</p>
            <?php else: ?>
                <div class="saved-items-grid">
                    <?php foreach ($activities as $activity): ?>
                        <div class="saved-item-card saved-item-activity-card">
                            <div class="saved-item-header">
                                <h4><?php echo htmlspecialchars($activity['name']); ?></h4>
                                <span class="saved-item-badge">Attraction</span>
                            </div>
                            <div class="saved-item-meta">
                                <span><strong>Category:</strong> <?php echo htmlspecialchars($activity['category']); ?></span>
                                <span><strong>Location:</strong> <?php echo htmlspecialchars($activity['city']); ?></span>

                                <?php if ($canEdit): ?>
                                    <span class="shared-activity-date-field">
                                        <strong>Date:</strong>
                                        <form method="POST" class="activity-date-form">
                                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                            <input type="hidden" name="activity_id" value="<?php echo (int) $activity['id']; ?>">
                                            <input
                                                type="date"
                                                name="activity_date"
                                                value="<?php echo htmlspecialchars($activity['activity_date'] ?? ''); ?>"
                                                min="<?php echo htmlspecialchars($trip['start_date']); ?>"
                                                max="<?php echo htmlspecialchars($trip['end_date']); ?>"
                                                required
                                            >
                                            <button type="submit" name="save_shared_activity_date" class="save-activity-date-btn">Save</button>
                                        </form>
                                    </span>
                                <?php else: ?>
                                    <span><strong>Date:</strong> <?php echo htmlspecialchars($activity['activity_date'] ? date('d M Y', strtotime($activity['activity_date'])) : 'TBD'); ?></span>
                                <?php endif; ?>

                                <span><strong>Cost:</strong> NZD <?php echo htmlspecialchars(number_format($activity['cost_nzd'], 0)); ?></span>
                            </div>
                            <?php if (!empty($activity['description'])): ?>
                                <p class="saved-item-description"><?php echo htmlspecialchars($activity['description']); ?></p>
                            <?php endif; ?>
                            <?php if ($canEdit): ?>
                                <div class="saved-item-actions">
                                    <form method="POST" onsubmit="return confirm('Remove this attraction from the trip?');">
                                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                        <input type="hidden" name="item_type" value="activity">
                                        <input type="hidden" name="item_id" value="<?php echo (int) $activity['id']; ?>">
                                        <button type="submit" name="delete_shared_item" class="saved-item-remove-btn">Remove</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="trip-details-section">
            <h5>Notes</h5>
            <?php if ($canEdit): ?>
                <form method="POST" class="trip-notes-form">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <textarea name="trip_notes" rows="6" placeholder="Add notes for this trip..."><?php echo htmlspecialchars($trip['notes'] ?? ''); ?></textarea>
                    <div class="trip-details-actions">
                        <button type="submit" name="save_shared_notes" class="trip-action-btn">Save Notes</button>
                    </div>
                </form>
            <?php elseif (empty($trip['notes']) || trim($trip['notes']) === ''): ?>
                <p class="trip-details-empty">No notes have been added for this trip yet.</p>
            <?php else: ?>
                <p class="shared-trip-notes"><?php echo nl2br(htmlspecialchars($trip['notes'])); ?></p>
            <?php endif; ?>
        </div>
    </main>

<?php endif; ?>

</body>
</html>
