<?php
/* Completed Trips Section */

$currentDate = date("Y-m-d");
$completedTrips = [];

// Filter for trips that have already ended
if (!empty($trips) && is_array($trips)) {
    foreach ($trips as $trip) {
        if ($trip['end_date'] < $currentDate) {
            $completedTrips[] = $trip;
        }
    }
}
?>

<div class="savedTrips" style="margin-top: 40px;">

    <div class="savedTrips-header">
        <div class="savedTrips-title">
            <h2>Completed Trips</h2>
            <p>Look back at your completed journeys and expenses.</p>
        </div>
    </div>

    <div class="trip-grid">
        <?php if (empty($completedTrips)): ?>
            <div class="trip-card empty-trip-card" style="background: #f3f4f6; border-style: dashed;">
                <div class="trip-card-body">
                    <p>No completed trips yet.</p>
                    <p>Your past adventures will be saved here!</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($completedTrips as $trip): ?>
                <?php
                $tripId = (int) $trip['id'];
                $tripName = $trip['title'];
                $destination = $trip['destination'];

                $today = new DateTime();
                $endDateObj = new DateTime($trip['end_date']);
                $daysSinceTrip = (int) $endDateObj->diff($today)->format("%a");

                //total budget calculation - if budget null or 0 the budget is not set

                $totalBudget = isset($trip['budget']) ? (float) $trip['budget'] : 0;
                $formattedBudget = "$" . number_format($totalBudget, 2);
                ?>

                <div class="trip-card saved-trip-card completed-card" style="opacity: 0.85; background-color: #fafafa;">
                    <div class="trip-card-title" style="color: #4b5563;">
                        <?php echo htmlspecialchars($tripName); ?>
                        <span style="font-size: 0.8em; color: #10b981;">✓</span>
                    </div>

                    <div class="trip-card-detail">
                        <strong>Destination</strong>
                        <span><?php echo htmlspecialchars($destination); ?></span>
                    </div>

                    <div class="trip-card-detail">
                        <strong>Dates</strong>
                        <span><?php echo htmlspecialchars($trip['start_date']); ?> →
                            <?php echo htmlspecialchars($trip['end_date']); ?></span>
                    </div>

                    <!-- Total Budget Display -->
                    <div class="trip-card-detail">
                        <strong>Total Budget</strong>
                        <span style="color: #059669; font-weight: 600;">
                            <?php echo $totalBudget > 0 ? $formattedBudget : '<em style="color:#9ca3af; font-weight:normal;">Not set</em>'; ?>
                        </span>
                    </div>

                    <div class="trip-card-detail">
                        <strong>Status</strong>
                        <span style="color: #6b7280;">
                            <?php
                            if ($daysSinceTrip === 0) {
                                echo "Ended today";
                            } else {
                                echo "Completed " . $daysSinceTrip . " day" . ($daysSinceTrip === 1 ? "" : "s") . " ago";
                            }
                            ?>
                        </span>
                    </div>

                    <div class="trip-card-actions">
                        <button type="button" class="trip-action-btn view-details-btn" data-trip-id="<?php echo $tripId; ?>"
                            style="width: 100%; background: #9ca3af;">View Memories</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>