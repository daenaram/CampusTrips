<?php

/* Upcoming Trips Section */
/*This should display a list of the user's upcoming trips */

$currentDate = date("Y-m-d");
$upcomingTrips = [];

// Filter for trips that haven't ended yet
if (!empty($trips) && is_array($trips)) {
    foreach ($trips as $trip) {
        if ($trip['end_date'] >= $currentDate) {
            $upcomingTrips[] = $trip;
        }
    }
}
?>

<!-- Reusing the .savedTrips wrapper for identical styling -->
<div class="savedTrips">
    
    <div class="savedTrips-header">
        <div class="savedTrips-title">
            <h2>Upcoming Trips</h2>
            <p>Your upcoming adventures await.</p>
        </div>
        
    </div>

    <div class="trip-grid">
        <?php if (empty($upcomingTrips)) : ?>
            <div class="trip-card empty-trip-card">
                <div class="trip-card-body">
                    <p>No upcoming trips found.</p>
                    <p>Once you create a trip, it will appear here.</p>
                </div>
            </div>
        <?php else : ?>
            <?php foreach ($upcomingTrips as $trip) : ?>
                <?php
                    // Use 'id' and 'title' to match the PDO fetch in Dashboard.php
                    $tripId = (int)$trip['id'];
                    $tripName = $trip['title'];
                    $destination = $trip['destination'];
                    
                    $today = new DateTime();
                    $startDateObj = new DateTime($trip['start_date']);
                    $endDateObj = new DateTime($trip['end_date']);
                    
                    // Calculate days until trip
                    $daysUntilTrip = (int)$today->diff($startDateObj)->format("%r%a");
                    if ($daysUntilTrip < 0) {
                        $daysUntilTrip = 0;
                    }

                    // Calculate total duration
                    $tripLength = $startDateObj->diff($endDateObj)->days + 1;
                ?>


                <div class="trip-card saved-trip-card">
                    <div class="trip-card-title">
                        <?php echo htmlspecialchars($tripName); ?>
                    </div>
                    
                    <div class="trip-card-detail">
                        <strong>Destination</strong>
                        <span><?php echo htmlspecialchars($destination); ?></span>
                    </div>
                    
                    <div class="trip-card-detail">
                        <strong>Dates</strong>
                        <span><?php echo htmlspecialchars($trip['start_date']); ?> → <?php echo htmlspecialchars($trip['end_date']); ?></span>
                    </div>
                    
                    <div class="trip-card-detail">
                        <strong>Duration</strong>
                        <span><?php echo $tripLength; ?> day<?php echo $tripLength > 1 ? 's' : ''; ?></span>
                    </div>
                    
                    <div class="trip-card-detail">
                        <strong>Countdown</strong>
                        <span>
                            <?php
                                if ($daysUntilTrip === 0) {
                                    echo "Starting today!";
                                } else {
                                    echo $daysUntilTrip . " day" . ($daysUntilTrip === 1 ? "" : "s") . " away";
                                }
                            ?>
                        </span>
                    </div>

                        <!-- Reusing the view-details-btn hooks right into your existing JS -->
                        <div class="trip-card-actions" style="display:flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                            <button type="button" class="trip-action-btn edit-trip-btn" data-trip-id="<?php echo $tripId; ?>" style="padding: 8px 16px; background: #107c22; color: white; border: none; border-radius: 999px;">Edit Trip</button>
                            <button type="button" class="trip-action-btn view-details-btn" data-trip-id="<?php echo $tripId; ?>" style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 999px;">View Details</button>
                        </div>
                        
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
