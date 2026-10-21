<?php
/**
 * Works out which trip reminders are due and pushes them to users who have
 * enabled push notifications. Run on a schedule by
 * assets/api/notifications/sendDueReminders.php, independently of whether
 * anyone has the app open.
 *
 * Reminders (each sent once, tracked in push_reminder_log):
 *  - a trip starting tomorrow (or today, if tomorrow's was missed)
 *  - a saved activity tomorrow (or today)
 *  - a saved flight departing within 7 days, and again within 24 hours
 *  - a saved accommodation check-in within 7 days, and again within 24 hours
 *
 * Still to be added — see the TODOs in findDueReminders() and
 * sendDueReminders():
 *  - custom reminders created by the user
 *  - notification preferences (which reminders a user wants to receive)
 */

require_once __DIR__ . '/webPushHelper.php';

//enforce notification preference
require_once __DIR__ . '/notificationPreferenceHelper.php';

// Trip dates are stored as plain local dates with no timezone, so "today"
// and "tomorrow" are judged in the app's home timezone rather than the
// server's (WAMP's PHP defaults to UTC).
const REMINDER_TIMEZONE = 'Pacific/Auckland';

const DASHBOARD_PATH = '/AUT-Web-Based-Travel-Planner/Pages/userDashboard/Dashboard.php';

/** The page a reminder links to: the dashboard, with that trip's details opened. */
function tripDetailsUrl(int $tripId): string
{
    return DASHBOARD_PATH . '?open_trip=' . $tripId;
}

function reminderDayLabel(string $date, string $today): string
{
    return $date === $today ? 'today' : 'tomorrow';
}

/** "7 days", "2 days", "1 day" — counted in calendar days, as a traveller would. */
function daysAwayLabel(DateTimeImmutable $now, DateTimeImmutable $departs): string
{
    $days = (int) $now->setTime(0, 0)->diff($departs->setTime(0, 0))->days;
    return $days === 1 ? '1 day' : "$days days";
}

/** @return array[] Each: ['user_id', 'key', 'title', 'body', 'url'] */
function findDueReminders(PDO $pdo, DateTimeImmutable $now): array
{
    $today = $now->format('Y-m-d');
    $tomorrow = $now->modify('+1 day')->format('Y-m-d');
    $reminders = [];

    // Only users with at least one device subscribed are worth querying for.
    $hasSubscription = "EXISTS (SELECT 1 FROM push_subscriptions ps WHERE ps.user_id = %s.user_id)";

    $stmt = $pdo->prepare("
        SELECT t.id, t.user_id, t.title, t.destination, t.start_date
        FROM trips t
        WHERE t.start_date IN (?, ?) AND " . sprintf($hasSubscription, 't')
    );
    $stmt->execute([$today, $tomorrow]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $trip) {
        $reminders[] = [
            'user_id' => (int) $trip['user_id'],
            'key' => "trip:{$trip['id']}:{$trip['start_date']}",
            'title' => "Your trip starts " . reminderDayLabel($trip['start_date'], $today),
            'body' => "{$trip['title']} — {$trip['destination']}",
            'url' => tripDetailsUrl((int) $trip['id']),
        ];
    }

    // Flights get two reminders: a heads-up once departure is within 7 days,
    // and a final one once it is within 24 hours. A flight saved less than a
    // day out only gets the final one. Only future departures are selected,
    // so nothing is ever sent for a flight that has already left.
    // departure_datetime is a plain local time, like trip dates.
    $nowStamp = $now->format('Y-m-d H:i:s');
    $in24Hours = $now->modify('+24 hours')->format('Y-m-d H:i:s');
    $in7Days = $now->modify('+7 days')->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        SELECT f.id, f.user_id, f.trip_id, f.flight_number, f.departure_city,
               f.arrival_city, f.departure_datetime, t.title AS trip_title
        FROM saved_flights f
        JOIN trips t ON t.id = f.trip_id
        WHERE f.departure_datetime > ? AND f.departure_datetime <= ? AND " . sprintf($hasSubscription, 'f')
    );
    $stmt->execute([$nowStamp, $in7Days]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $flight) {
        $isFinal = $flight['departure_datetime'] <= $in24Hours;
        $departs = new DateTimeImmutable($flight['departure_datetime'], $now->getTimezone());

        $reminders[] = [
            'user_id' => (int) $flight['user_id'],
            // The departure time is part of the key so a flight that is
            // rescheduled gets reminded about again.
            'key' => 'flight-' . ($isFinal ? '24h' : '7d') . ":{$flight['id']}:{$flight['departure_datetime']}",
            'title' => $isFinal
                ? "Final reminder: flight {$flight['flight_number']} departs within 24 hours"
                : "Upcoming flight {$flight['flight_number']} in " . daysAwayLabel($now, $departs),
            'body' => "{$flight['departure_city']} → {$flight['arrival_city']}, departs "
                . $departs->format('D j M, g:i a') . " · Trip: {$flight['trip_title']}",
            'url' => tripDetailsUrl((int) $flight['trip_id']),
        ];
    }

    // Accommodation check-ins are reminded about the same way as flights:
    // once within 7 days, and a final time within 24 hours, never after the
    // check-in has passed. The check-in moment is the planned date at the
    // stay's check-in time (14:00 if none was set).
    $stmt = $pdo->prepare("
        SELECT a.id, a.user_id, a.trip_id, a.name, a.city, a.address, a.check_in_at,
               t.title AS trip_title
        FROM (
            SELECT s.*, CAST(TIMESTAMP(s.planned_check_in, COALESCE(s.check_in_time, '14:00:00')) AS DATETIME) AS check_in_at
            FROM saved_accommodations s
            WHERE s.planned_check_in IS NOT NULL AND " . sprintf($hasSubscription, 's') . "
        ) a
        JOIN trips t ON t.id = a.trip_id
        WHERE a.check_in_at > ? AND a.check_in_at <= ?
    ");
    $stmt->execute([$nowStamp, $in7Days]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $stay) {
        $isFinal = $stay['check_in_at'] <= $in24Hours;
        $checkIn = new DateTimeImmutable($stay['check_in_at'], $now->getTimezone());

        $reminders[] = [
            'user_id' => (int) $stay['user_id'],
            'key' => 'checkin-' . ($isFinal ? '24h' : '7d') . ":{$stay['id']}:{$stay['check_in_at']}",
            'title' => $isFinal
                ? "Final reminder: check in at {$stay['name']} within 24 hours"
                : "Upcoming check-in at {$stay['name']} in " . daysAwayLabel($now, $checkIn),
            'body' => "{$stay['address']}, {$stay['city']} · Check-in "
                . $checkIn->format('D j M, g:i a') . " · Trip: {$stay['trip_title']}",
            'url' => tripDetailsUrl((int) $stay['trip_id']),
        ];
    }

    // //Visa deadline reminders
    // $in7DaysDate = $now->modify('+7 days')->format('Y-m-d');
    // $stmt = $pdo->prepare("
    //     SELECT v.id, v.user_id, v.trip_id, v.country, v.deadline_date,t.title AS trip_title 
    //     FROM visa_deadlines v LEFT JOIN trips t ON t.id = v.trip_id 
    //     WHERE v.deadline_date >= ? AND v.deadline_date <= ? AND " . sprintf($hasSubscription, 'v')
    // );
    // $stmt->execute([$today, $in7DaysDate]);
    // foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $visa ) {
    //     $isFinal = $visa['deadline_date'] <= $tomorrow;
    //     $deadline = new DateTimeImmutable($visa['deadline_date'], $now->getTimezone());
    //      $reminders[] = [
    //         'user_id' => (int) $visa['user_id'],
    //         'key'     => 'visa-' . ($isFinal ? '24h' : '7d') . ":{$visa['id']}:{$visa['deadline_date']}",
    //         'title'   => $isFinal
    //             ? "Final reminder: visa deadline for {$visa['country']} is " . reminderDayLabel($visa['deadline_date'], $today)
    //             : "Visa deadline for {$visa['country']} in " . daysAwayLabel($now, $deadline),
    //         'body'    => "Deadline " . $deadline->format('D j M')
    //             . ($visa['trip_title'] ? " · Trip: {$visa['trip_title']}" : ''),
    //         'url'     => $visa['trip_id'] ? tripDetailsUrl((int) $visa['trip_id']) : DASHBOARD_PATH,
    //     ];
    // }

    // Custom reminders created by users.
    // Both custom reminders and visa deadlines are stored
    // in user_reminders, distinguished by reminder_type.
    $stmt = $pdo->prepare("
        SELECT r.id, r.user_id, r.trip_id, r.title, r.body, r.remind_on
        FROM user_reminders r
        WHERE r.reminder_type = 'custom' AND r.remind_on IN (?, ?) AND " . sprintf($hasSubscription, 'r')
    );

    $stmt->execute([$today, $tomorrow]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $custom) {
        $reminders[] = [
            'user_id' => (int) $custom['user_id'],
            'key' => "custom:{$custom['id']}:{$custom['remind_on']}",
            'title' => "Reminder: {$custom['title']}",
            'body' => ($custom['body'] !== null && $custom['body'] !== '' ? $custom['body'] . ' · ' : '') 
                . ucfirst(reminderDayLabel($custom['remind_on'], $today)),
            'url' => $custom['trip_id'] ? tripDetailsUrl((int) $custom['trip_id']) : DASHBOARD_PATH,
        ];
    }
    return $reminders;
}

/**
 * Pushes every due reminder that hasn't been sent yet.
 *
 * @return array ['sent' => int, 'skipped' => int, 'failed' => int]
 */
function sendDueReminders(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    $now = $now ?? new DateTimeImmutable('now', new DateTimeZone(REMINDER_TIMEZONE));
    $counts = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

    $claim = $pdo->prepare("INSERT IGNORE INTO push_reminder_log (user_id, reminder_key) VALUES (?, ?)");
    $release = $pdo->prepare("DELETE FROM push_reminder_log WHERE user_id = ? AND reminder_key = ?");
    $record = $pdo->prepare("INSERT INTO user_notifications (user_id, title, body, url) VALUES (?, ?, ?, ?)");

    foreach (findDueReminders($pdo, $now) as $reminder) {

        // Load each user's preferences once per run rather than per reminder.
        $prefsCache = [];
        $prefsFor = function (int $userId) use ($pdo, &$prefsCache): array {
            if (!isset($prefsCache[$userId])) {
                $prefsCache[$userId] = getNotificationPreferences($pdo, $userId);
            }
            return $prefsCache[$userId];
        };

        foreach (findDueReminders($pdo, $now) as $reminder) {
            // the user's notification preferences.
            if (!isReminderEnabled($prefsFor($reminder['user_id']), $reminder['key'])) {
                $counts['skipped']++;
                continue;
            }


            // Claiming the log row first means two overlapping runs can never
            // both send the same reminder.
            $claim->execute([$reminder['user_id'], $reminder['key']]);
            if ($claim->rowCount() === 0) {
                $counts['skipped']++;
                continue;
            }

            $delivered = sendPushToUser($pdo, $reminder['user_id'], [
                'title' => $reminder['title'],
                'body' => $reminder['body'],
                'url' => $reminder['url'],
                'tag' => $reminder['key'],
            ]);

            if ($delivered > 0) {
                // Kept for the in-app notification bell.
                $record->execute([$reminder['user_id'], $reminder['title'], $reminder['body'], $reminder['url']]);
                $counts['sent']++;
            } else {
                // Nothing got through (push service down, network blip) — let
                // the next run try again.
                $release->execute([$reminder['user_id'], $reminder['key']]);
                $counts['failed']++;
            }
        }

        return $counts;
    }
}