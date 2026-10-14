<?php
/**
 * Sends any trip reminders that are now due as push notifications.
 *
 * This is what lets reminders arrive while the app is closed: it runs on a
 * schedule on the server, not in anyone's browser. Command line only.
 *
 * Run it every 5 minutes, e.g. with Windows Task Scheduler (WAMP):
 *   schtasks /Create /SC MINUTE /MO 5 /TN "CampusTrips Reminders" ^
 *     /TR "C:\wamp64\bin\php\php8.3.28\php.exe C:\wamp64\www\AUT-Web-Based-Travel-Planner\assets\api\notifications\sendDueReminders.php"
 * or with cron on Linux:
 *   0-59/5 * * * * php /path/to/AUT-Web-Based-Travel-Planner/assets/api/notifications/sendDueReminders.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/reminderHelper.php';

$counts = sendDueReminders($pdo);

echo date('c') . " reminders sent={$counts['sent']} already-sent={$counts['skipped']} failed={$counts['failed']}" . PHP_EOL;
