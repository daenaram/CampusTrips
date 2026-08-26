<?php
/**
 * Shared trip-budget calculation helper.
 * Place this file at: assets/api/helpers/budgetHelper.php
 *
 * Used by both budget.php (the full budget breakdown page) and
 * userDashboard.php (to show each trip card's total cost) so the
 * calculation logic only lives in one place.
 *
 * Requires currencyHelper.php to already be loaded (for NZD conversion —
 * only relevant if you extend this to convert saved item prices too).
 */

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