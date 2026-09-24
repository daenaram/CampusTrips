<?php
/* Completed Trips Section */

$currentDate = new DateTime('today');
$completedTrips = [];

// Filter for trips that have already ended
if (!empty($trips) && is_array($trips)) {
    foreach ($trips as $trip) {
        $endDate = new DateTime($trip['end_date']);
        $endDate->setTime(0, 0, 0);
        if ($endDate <= $currentDate) {
            $completedTrips[] = $trip;
        }
    }
}
?>

<div class="savedTrips">

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
                <?php $category = getCategoryDetails($trip['travel_style']); ?>
                <?php
                $tripId = (int) $trip['id'];
                $tripName = $trip['title'];
                $destination = $trip['destination'];

                $today = new DateTime();
                $endDateObj = new DateTime($trip['end_date']);
                $daysSinceTrip = (int) $endDateObj->diff($today)->format("%a");

                // Pull the pre-built budget summary for this trip (already calculated in Dashboard.php)
                $budgetSummary = $tripDetails[$tripId]['budget'] ?? null;
                $totalBudget = $budgetSummary['grand_total'] ?? 0;
                $formattedBudget = "$" . number_format($totalBudget, 2);
                ?>

                <div class="trip-card saved-trip-card completed-card" style="opacity: 0.85; background-color: #fafafa;">
                    <span class="bookmark-ribbon" style="--ribbon-color: <?php echo htmlspecialchars($category['color']); ?>;"
                        title="<?php echo htmlspecialchars($category['label']); ?>">
                        <span
                            class="bookmark-ribbon-label"><?php echo htmlspecialchars(strtoupper(substr($category['label'], 0, 1))); ?></span>
                    </span>
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
                        <button type="button" class="trip-action-btn view-btn" data-trip-id="<?php echo $tripId; ?>"
                            style="width: 100%; background: #10397f;">View</button>
                    </div>
                </div>

                <!-- Hidden template: this trip's flights / hotels / activities / budget -->
                <div class="trip-template" id="trip-template-<?php echo $tripId; ?>" style="display:none;">
                    <div class="trip-details-summary">
                        <h4><?php echo htmlspecialchars($tripName); ?> <span style="color:#10b981;">✓ Completed</span></h4>
                        <p><strong>Destination:</strong> <?php echo htmlspecialchars($destination); ?></p>
                        <p><strong>Dates:</strong> <?php echo htmlspecialchars($trip['start_date']); ?> →
                            <?php echo htmlspecialchars($trip['end_date']); ?></p>
                    </div>

                    <div class="trip-details-section">
                        <h5>Flights</h5>
                        <?php $flights = $tripDetails[$tripId]['flights'] ?? []; ?>
                        <?php if (empty($flights)): ?>
                            <p class="trip-details-empty">No flights were recorded for this trip.</p>
                        <?php else: ?>
                            <div class="saved-items-grid">
                                <?php foreach ($flights as $flight): ?>
                                    <div class="saved-item-card saved-item-flight-card">
                                        <div class="saved-item-header">
                                            <h4><?php echo htmlspecialchars($flight['airline'] . ' ' . $flight['flight_number']); ?>
                                            </h4>
                                            <span class="saved-item-badge">Flight</span>
                                        </div>
                                        <div class="saved-item-meta">
                                            <span><strong>Route:</strong>
                                                <?php echo htmlspecialchars($flight['departure_city'] . ' → ' . $flight['arrival_city']); ?></span>
                                            <span><strong>Departure:</strong>
                                                <?php echo htmlspecialchars($flight['departure_datetime'] ? date('d M Y H:i', strtotime($flight['departure_datetime'])) : 'TBD'); ?></span>
                                            <span><strong>Arrival:</strong>
                                                <?php echo htmlspecialchars($flight['arrival_datetime'] ? date('d M Y H:i', strtotime($flight['arrival_datetime'])) : 'TBD'); ?></span>
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
                        <?php $hotels = $tripDetails[$tripId]['hotels'] ?? []; ?>
                        <?php if (empty($hotels)): ?>
                            <p class="trip-details-empty">No accommodation was recorded for this trip.</p>
                        <?php else: ?>
                            <div class="saved-items-grid">
                                <?php foreach ($hotels as $hotel): ?>
                                    <div class="saved-item-card saved-item-hotel-card">
                                        <div class="saved-item-header">
                                            <h4><?php echo htmlspecialchars($hotel['name']); ?></h4>
                                            <span class="saved-item-badge">Hotel</span>
                                        </div>
                                        <div class="saved-item-meta">
                                            <span><strong>Location:</strong>
                                                <?php echo htmlspecialchars($hotel['city'] . ', ' . $hotel['country']); ?></span>
                                            <span><strong>Check-in:</strong>
                                                <?php echo htmlspecialchars($hotel['planned_check_in'] ? date('d M Y', strtotime($hotel['planned_check_in'])) : 'TBD'); ?></span>
                                            <span><strong>Check-out:</strong>
                                                <?php echo htmlspecialchars($hotel['planned_check_out'] ? date('d M Y', strtotime($hotel['planned_check_out'])) : 'TBD'); ?></span>
                                        </div>
                                        <div class="saved-item-footer">
                                            <span>NZD <?php echo htmlspecialchars(number_format($hotel['price_per_night_nzd'], 0)); ?> /
                                                night</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="trip-details-section">
                        <h5>Activities</h5>
                        <?php $attractions = $tripDetails[$tripId]['attractions'] ?? []; ?>
                        <?php if (empty($attractions)): ?>
                            <p class="trip-details-empty">No activities were recorded for this trip.</p>
                        <?php else: ?>
                            <div class="saved-items-grid">
                                <?php foreach ($attractions as $attraction): ?>
                                    <div class="saved-item-card saved-item-activity-card">
                                        <div class="saved-item-header">
                                            <h4><?php echo htmlspecialchars($attraction['name']); ?></h4>
                                            <span class="saved-item-badge">Activity</span>
                                        </div>
                                        <div class="saved-item-meta">
                                            <span><strong>Category:</strong>
                                                <?php echo htmlspecialchars($attraction['category']); ?></span>
                                            <span><strong>Location:</strong> <?php echo htmlspecialchars($attraction['city']); ?></span>
                                            <span><strong>Date:</strong>
                                                <?php echo htmlspecialchars($attraction['activity_date'] ? date('d M Y', strtotime($attraction['activity_date'])) : 'TBD'); ?></span>
                                        </div>
                                        <p class="saved-item-description"><?php echo htmlspecialchars($attraction['description']); ?>
                                        </p>
                                        <div class="saved-item-footer">
                                            <span>NZD <?php echo htmlspecialchars(number_format($attraction['cost_nzd'], 0)); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="trip-details-section">
                        <h5>Budget Breakdown</h5>
                        <?php $categoryTotals = $budgetSummary['category_totals'] ?? []; ?>
                        <?php if (empty($categoryTotals) || $totalBudget <= 0): ?>
                            <p class="trip-details-empty">No budget was recorded for this trip.</p>
                        <?php else: ?>
                            <div class="saved-items-grid">
                                <?php foreach ($categoryTotals as $cat => $amount): ?>
                                    <?php if ($amount > 0): ?>
                                        <div class="saved-item-card">
                                            <div class="saved-item-header">
                                                <h4><?php echo htmlspecialchars($cat); ?></h4>
                                            </div>
                                            <div class="saved-item-footer">
                                                <span>NZD <?php echo number_format($amount, 2); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <p style="margin-top: 12px;"><strong>Total Spent:</strong> NZD
                                <?php echo number_format($totalBudget, 2); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- View Modal (shared across all completed trip cards) -->
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
    (function () {
        const detailsModal = document.getElementById('trip-details-modal');
        const detailsContent = document.getElementById('trip-details-content');
        const closeDetailsModal = document.getElementById('close-trip-details-modal');

        function showDetailsModal() {
            detailsModal.style.display = 'flex';
            detailsModal.setAttribute('aria-hidden', 'false');
        }

        function hideDetailsModal() {
            detailsModal.style.display = 'none';
            detailsModal.setAttribute('aria-hidden', 'true');
            detailsContent.innerHTML = '';
        }

        document.querySelectorAll('.view-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                const tripId = this.getAttribute('data-trip-id');
                const template = document.getElementById('trip-template-' + tripId);
                if (template) {
                    detailsContent.innerHTML = template.innerHTML;
                    showDetailsModal();
                }
            });
        });

        closeDetailsModal.addEventListener('click', hideDetailsModal);
        detailsModal.addEventListener('click', function (event) {
            if (event.target === detailsModal) {
                hideDetailsModal();
            }
        });
    })();
</script>