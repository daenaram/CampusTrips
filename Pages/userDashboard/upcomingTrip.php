<?php

/* Upcoming Trips Section */
/*This should display a list of the user's upcoming trips */

$currentDate = date("Y-m-d");
$upcomingTrips = [];

foreach ($trips as $trip) {
    if ($trip['end_date'] >= $currentDate) {
        $upcomingTrips[] = $trip;
    }
}
?>

<div class="upcomingTrips">
    <div class="savedTrips-header">
        <h2>Upcoming Trips</h2>
        <p>Your upcoming adventures await.</p>
    </div>

    <div class="sort-container">
        <label for="sortUpcomingTrips">Sort by:</label>
        <select id="sortUpcomingTrips">
            <option value="date">Date</option>
            <option value="destination">Destination</option>
        </select>
    </div>
</div>

<div class="trip-grid">
    <?php if (empty($upcomingTrips)) : ?>
        <div class="trip-card no-trips">
            <div class="trip-card-body">
                <p>No upcoming trips found.</p>
                <p>Once you create a trip, it will appear.</p>
            </div>
        </div>
    <?php else : ?>
        <?php foreach ($upcomingTrips as $trip) : ?>
            <?php
                $tripId = $trip['trip_id'];
                $tripName = $trip['trip_name'];
                $destination = $trip['destination'];
                $today = new DateTime();
                $startDateObj = new DateTime($trip['start_date']);
                $endDateObj = new DateTime($trip['end_date']);
                $startDate = $startDateObj->format("d M Y");
                $endDate = $endDateObj->format("d M Y");

                $daysUntilTrip = (int)$today->diff($startDateObj)->format("%r%a");
                if ($daysUntilTrip < 0) {
                    $daysUntilTrip = 0;
                }

                $tripLength = $startDateObj->diff($endDateObj)->days + 1;
                $progress = 0;

                if (!empty($tripDetails[$tripId]['flights'])) {
                    $progress += 25;
                }

                if (!empty($tripDetails[$tripId]['hotels'])) {
                    $progress += 25;
                }

                if (!empty($tripDetails[$tripId]['activities'])) {
                    $progress += 25;
                }

                if (!empty($tripDetails[$tripId]['notes'])) {
                    $progress += 25;
                }
            ?>

            <div class="trip-card upcoming-trip-card">
                <div class="upcoming-status">
                    Upcoming
                </div>
                <div class="trip-card-title">
                    <?= htmlspecialchars($tripName); ?>
                </div>
                <div class="trip-card-detail">
                    <strong>Destination:</strong>
                    <span><?= htmlspecialchars($destination); ?></span>
                </div>
                <div class="trip-card-detail">
                    <strong>Departure:</strong>
                    <span><?= htmlspecialchars($startDate); ?></span>
                </div>
                <div class="trip-card-detail">
                    <strong>Duration:</strong>
                    <span><?= $tripLength; ?> days</span>
                </div>
                <div class="trip-card-detail">
                    <strong>Countdown:</strong>
                    <span class="countdown">
                        <?php
                            if ($daysUntilTrip === 0) {
                                echo "Trip has started!";
                            } else {
                                echo $daysUntilTrip . " day" . ($daysUntilTrip === 1 ? "" : "s") . " until trip";
                            }
                        ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
