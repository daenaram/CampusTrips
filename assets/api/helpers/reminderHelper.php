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
 *
 * Still to be added — see the TODOs in findDueReminders() and
 * sendDueReminders():
 *  - flight reminders
 *  - accommodation / hotel reminders
 *  - custom reminders created by the user
 *  - notification preferences (which reminders a user wants to receive)
 */

require_once __DIR__ . '/webPushHelper.php';

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
            'key'     => "trip:{$trip['id']}:{$trip['start_date']}",
            'title'   => "Your trip starts " . reminderDayLabel($trip['start_date'], $today),
            'body'    => "{$trip['title']} — {$trip['destination']}",
            'url'     => tripDetailsUrl((int) $trip['id']),
        ];
    }

    // TODO (flight reminders): query saved_flights here — e.g. flights whose
    // departure_datetime falls within the next 24 hours — and add one entry
    // per flight to $reminders, in the same shape as the trip reminder above.
    // Give each a unique 'key' (e.g. "flight:<id>:<departure>") so it is only
    // ever sent once, and link it with tripDetailsUrl($flight['trip_id']).

    // TODO (accommodation / hotel reminders): query saved_accommodations here
    // — e.g. a planned_check_in (or planned_check_out) of today or tomorrow —
    // and add entries to $reminders the same way, with a unique 'key' such as
    // "checkin:<id>:<date>".

    // TODO (custom reminders): once users can create their own reminders,
    // select the ones that are now due from that table here and add them to
    // $reminders, with a unique 'key' (e.g. "custom:<reminder id>") and a
    // 'url' pointing at the related trip (tripDetailsUrl()) or DASHBOARD_PATH.

    $stmt = $pdo->prepare("
        SELECT s.id, s.user_id, s.trip_id, s.name, s.city, s.activity_date
        FROM saved_activities s
        WHERE s.activity_date IN (?, ?) AND " . sprintf($hasSubscription, 's')
    );
    $stmt->execute([$today, $tomorrow]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $activity) {
        $reminders[] = [
            'user_id' => (int) $activity['user_id'],
            'key'     => "activity:{$activity['id']}:{$activity['activity_date']}",
            'title'   => 'Activity ' . reminderDayLabel($activity['activity_date'], $today),
            'body'    => "{$activity['name']}, {$activity['city']}",
            'url'     => tripDetailsUrl((int) $activity['trip_id']),
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

    foreach (findDueReminders($pdo, $now) as $reminder) {
        // TODO (notification preferences): once users can choose which
        // reminders they want, skip any $reminder the user has turned off
        // here (e.g. based on the type prefix of $reminder['key'], such as
        // "trip:", "activity:", "flight:"), before anything is sent. The
        // settings UI for it belongs in the Notifications section of
        // Pages/userDashboard/settings.php.

        // Claiming the log row first means two overlapping runs can never
        // both send the same reminder.
        $claim->execute([$reminder['user_id'], $reminder['key']]);
        if ($claim->rowCount() === 0) {
            $counts['skipped']++;
            continue;
        }

        $delivered = sendPushToUser($pdo, $reminder['user_id'], [
            'title' => $reminder['title'],
            'body'  => $reminder['body'],
            'url'   => $reminder['url'],
            'tag'   => $reminder['key'],
        ]);

        if ($delivered > 0) {
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
