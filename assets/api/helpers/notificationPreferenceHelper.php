<?php
/*
 * Notification preferences — which kinds of reminder a user wants to receive.
 */

const NOTIFICATION_PREFERENCES_TABLE = 'notification_preferences';

const REMINDER_TYPES = [
    'flights' => [
        'label' => 'Flight reminders',
        'description' => 'Heads-up within 7 days and a final reminder within 24 hours of departure.',
        'prefixes' => ['flight']
    ],
    'checkin' => [
        'label' => 'Check-in reminders',
        'description' => 'Accommodation check-in reminders.',
        'prefixes' => ['checkin']
    ],
    'visa' => [
        'label' => 'Visa deadlines',
        'description' => 'Reminders about upcoming visa deadlines.',
        'prefixes' => ['visa']
    ],
    'custom' => [
        'label' => 'Custom reminders',
        'description' => 'Reminders you create yourself.',
        'prefixes' => ['custom']
    ],
    'trips' => [
        'label' => 'Trip start reminders',
        'description' => 'A reminder when a trip is about to begin.',
        'prefixes' => ['trip']
    ],
    'activities' => [
        'label' => 'Activity reminders',
        'description' => 'A reminder the day before a saved activity.',
        'prefixes' => ['activity']
    ],
];

/** Maps a reminder key like "flight-24h:12:..." to its preference type. */
function reminderTypeForKey(string $key): ?string
{
    $prefix = preg_split('/[:\-]/', $key, 2)[0];
    foreach (REMINDER_TYPES as $type => $def) {
        if (in_array($prefix, $def['prefixes'], true)) {
            return $type;
        }
    }
    return null;
}


function defaultNotificationPreferences(): array
{
    $prefs = ['all' => true];
    foreach (REMINDER_TYPES as $type => $_) {
        $prefs[$type] = true;
    }
    return $prefs;
}

function getNotificationPreferences(PDO $pdo, int $userId): array
{
    $prefs = defaultNotificationPreferences();

    $stmt = $pdo->prepare("SELECT reminder_type, enabled FROM " . NOTIFICATION_PREFERENCES_TABLE . " WHERE user_id = ?");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (array_key_exists($row['reminder_type'], $prefs)) {
            $prefs[$row['reminder_type']] = (bool) $row['enabled'];
        }
    }
    return $prefs;
}

/**
 * kept in sync with the individual types when saving, so re-enabling a single type after turning
 * everything off works as expected.
 */
function isReminderEnabled(array $prefs, string $reminderKey): bool
{
    $type = reminderTypeForKey($reminderKey);

    // Unknown reminder types fall back
    if ($type === null) {
        return !empty($prefs['all']);
    }

    return !empty($prefs[$type]);
}

/* Persists a set of preferences.*/
function saveNotificationPreferences(PDO $pdo, int $userId, array $input): void
{
    $current = getNotificationPreferences($pdo, $userId);
    $types = array_keys(REMINDER_TYPES);

    $allOff = array_key_exists('all', $input) && !$input['all'];
    $allOn = array_key_exists('all', $input) && (bool) $input['all'];

    $values = [];
    foreach ($types as $type) {
        if ($allOff) {
            $values[$type] = false;
        } elseif ($allOn && !array_key_exists($type, $input)) {
            $values[$type] = true;
        } elseif (array_key_exists($type, $input)) {
            $values[$type] = (bool) $input[$type];
        } else {
            $values[$type] = $current[$type];
        }
    }
    $values['all'] = in_array(true, $values, true);

    $stmt = $pdo->prepare("
        INSERT INTO " . NOTIFICATION_PREFERENCES_TABLE . " (user_id, reminder_type, enabled)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)
    ");

    $pdo->beginTransaction();
    try {
        foreach ($values as $type => $enabled) {
            $stmt->execute([$userId, $type, $enabled ? 1 : 0]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
