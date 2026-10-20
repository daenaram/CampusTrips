# CampusTrips — AUT Web-Based Travel Planner

A PHP/MySQL web app for planning trips: create trips, save flights, accommodation and activities to them, track a budget, share trips with others, and get reminder notifications before you travel. It is also installable as a Progressive Web App (PWA).

## Getting started

Requirements: [WampServer](https://www.wampserver.com/) (Apache, MySQL, PHP 8+). The `openssl`, `curl` and `pdo_mysql` PHP extensions are needed; WAMP enables them by default.

1. Put the project in `C:\wamp64\www\AUT-Web-Based-Travel-Planner` and start WAMP.
2. Open <http://localhost/AUT-Web-Based-Travel-Planner/>.

There is no database to import. [assets/api/config/database.php](assets/api/config/database.php) creates the `aut_travel_planner` database, every table and the dummy search data on first use. It connects as `root` with no password. New columns and tables are added there too, guarded so they're only created once.

> Use `http://localhost`, not your machine's IP address. Service workers and push notifications only work on `localhost` or over HTTPS.

## Project structure

```
Pages/
  UserAuthentication/   login, sign-up, password reset
  userDashboard/        Dashboard.php (trips), budget, search, settings, profile, help desk
  shared/               read-only view of a shared trip link
assets/
  api/
    auth/               sign-in / sign-out endpoints (incl. Google via Firebase)
    config/             database.php (schema), dummy data, openssl.cnf
    dashboard/          flight / hotel / activity search
    helpers/            shared PHP (budget, currency, sharing, conflicts, reminders, web push)
    notifications/      push notification + reminder endpoints and the scheduled sender
  css/  js/  images/
sw.js                   service worker (offline caching + push notifications)
manifest.json           PWA manifest
```

---

## Notifications and reminders

CampusTrips sends reminders as **browser push notifications**, so they arrive even when the app isn't open. A copy of each one also appears in the in-app **notification bell**.

### What users see

- **First-login prompt.** On the dashboard, a banner asks the user to allow notifications. It keeps appearing until they choose *Allow notifications* or *Not now* (stored in `users.push_prompted_at`). Choosing *Allow* opens the browser's own permission popup. If they block it there, the app says so politely and carries on as normal.
- **Settings → Notifications → Trip Reminders.** A switch that turns push on or off for *this browser and device*, with a status indicator:
  - Active: shows a "Send test notification" button.
  - Off.
  - Blocked: shows step-by-step instructions for re-allowing notifications in the user's browser (Chrome, Edge, Firefox, Safari, iPhone/iPad).
  - Not supported: suggests a compatible browser.
- **Clicking a notification** opens the dashboard with that trip's details open (`Dashboard.php?open_trip=<id>`). If the user has been signed out, they sign in first and are then taken to the trip.
- **Notification bell** (next to the hamburger menu). Lists recent reminders, with an unread badge.

### How it works

```
Every 5 minutes (Windows Task Scheduler / cron)
  └─ sendDueReminders.php
       └─ reminderHelper.php: findDueReminders()   → which reminders are due now
          sendDueReminders()                       → skip ones already sent (push_reminder_log)
            └─ webPushHelper.php: sendPushToUser() → encrypt + send to each of the user's devices
                 └─ push service (Google / Mozilla / Apple / Microsoft)
                      └─ user's browser wakes sw.js → shows the notification
          + saves a copy in user_notifications      → notification bell
```

Push messages are encrypted and signed with the app's own VAPID key pair, using plain PHP with no Composer packages.

### Setup (once per machine/server)

1. **Schedule the reminder sender.** Without this, the test button works, but reminders are never sent automatically. On WAMP, in a Command Prompt (adjust the PHP version folder to match your install):

   ```
   schtasks /Create /SC MINUTE /MO 5 /TN "CampusTrips Reminders" /TR "C:\wamp64\bin\php\php8.3.28\php.exe C:\wamp64\www\AUT-Web-Based-Travel-Planner\assets\api\notifications\sendDueReminders.php"
   ```

   On Linux, use cron: `0-59/5 * * * * php /path/to/assets/api/notifications/sendDueReminders.php`

2. **VAPID keys are created automatically** the first time they're needed, in `assets/api/config/vapidKeys.php`.
   - The file is git-ignored and must stay private; never commit it.
   - Each machine gets its own keys, which is fine.
   - Deleting the file invalidates every existing subscription. Users would have to turn notifications on again.

3. **Before deploying anywhere real,** change `WEB_PUSH_SUBJECT` in [webPushHelper.php](assets/api/helpers/webPushHelper.php) to a real team email address. Apple rejects pushes without one.

### Current reminder types

All times are judged in New Zealand time (`Pacific/Auckland`), because trip dates are stored without a timezone.

| Reminder | When it's sent | Key prefix |
|---|---|---|
| Trip starting | the day before the trip (or the day itself, if missed) | `trip:` |
| Activity | the day before the activity (or the day itself) | `activity:` |
| Flight | once within 7 days of departure, and again within 24 hours | `flight-7d:` / `flight-24h:` |
| Accommodation check-in | once within 7 days of check-in, and again within 24 hours | `checkin-7d:` / `checkin-24h:` |

### Adding a new kind of reminder

Everything goes in `findDueReminders()` in [reminderHelper.php](assets/api/helpers/reminderHelper.php):

1. Query the rows that are due, including `EXISTS (... push_subscriptions ...)` like the existing queries, so users who haven't enabled push are skipped.
2. Add one entry per reminder to `$reminders`:

   ```php
   $reminders[] = [
       'user_id' => (int) $row['user_id'],
       'key'     => "myreminder:{$row['id']}:{$row['date']}", // unique; each key is only ever sent once
       'title'   => 'Short headline',
       'body'    => 'One line of detail',
       'url'     => tripDetailsUrl((int) $row['trip_id']),  // or DASHBOARD_PATH
   ];
   ```

That's all. Sending each reminder only once, removing expired devices, retrying failures and adding to the notification bell are handled for you.

Include anything that might change, such as a date or time, in the `key`, so a rescheduled item gets reminded about again.

**Still to do.** There are `TODO` comments where each one goes:

- **Custom reminders** created by the user: in `findDueReminders()`.
- **Notification preferences** (which reminder types a user wants):
  - the filtering goes in `sendDueReminders()`, based on the key prefix;
  - the options go in the Notifications section of [settings.php](Pages/userDashboard/settings.php).

### Testing

- **Quick check:** Settings → turn on Trip Reminders → *Send test notification*. It links to your next upcoming trip, so clicking it tests the "open trip details" behaviour too.
- **App closed:** turn notifications on, close every CampusTrips tab, then run the sender by hand:

  ```
  C:\wamp64\bin\php\php8.3.28\php.exe assets\api\notifications\sendDueReminders.php
  ```

  It prints a line like `reminders sent=1 already-sent=0 failed=0`. You need a trip, flight, check-in or activity that is due (see the table above).
- **First-login prompt again:** set `push_prompted_at` back to `NULL` for your user in the `users` table.
- **Browser automation (Puppeteer/Playwright):** push doesn't work in incognito-style browser contexts. Use a normal profile (`userDataDir`).

### Files

| File | Purpose |
|---|---|
| [assets/js/pushNotifications.js](assets/js/pushNotifications.js) | Browser side: support check, permission, subscribe/unsubscribe, status, blocked-browser instructions (`window.CampusPush`) |
| [assets/js/notificationBell.js](assets/js/notificationBell.js) | The notification bell and its pop-up |
| [sw.js](sw.js) | Shows incoming pushes; opens the trip when one is clicked |
| [assets/api/helpers/reminderHelper.php](assets/api/helpers/reminderHelper.php) | Which reminders are due, and sending them once each |
| [assets/api/helpers/webPushHelper.php](assets/api/helpers/webPushHelper.php) | VAPID keys, message encryption, sending to push services |
| [assets/api/notifications/sendDueReminders.php](assets/api/notifications/sendDueReminders.php) | The scheduled job (command line only) |
| `assets/api/notifications/subscribe.php`, `unsubscribe.php`, `checkSubscription.php`, `vapidPublicKey.php` | Device registration endpoints |
| `assets/api/notifications/markPrompted.php` | Records that the first-login prompt was answered |
| `assets/api/notifications/sendTest.php` | "Send test notification" |
| `assets/api/notifications/recent.php`, `markRead.php` | Notification bell data |

**Database tables:**

| Table / column | Holds |
|---|---|
| `push_subscriptions` | One row per browser/device with push enabled |
| `push_reminder_log` | Which reminders have been sent, so none is sent twice |
| `user_notifications` | The bell's notification history (`read_at` stays NULL until the user opens the bell) |
| `users.push_prompted_at` | When the user answered the first-login prompt |

These tables have no foreign keys to `users`, because `users` may be MyISAM. [deleteAccountConfirm.php](Pages/userDashboard/deleteAccountConfirm.php) removes a user's rows when their account is deleted. Keep that in mind if you add a table.

### Troubleshooting

| Problem | Likely cause |
|---|---|
| Test notification works, but real reminders never arrive | The scheduled task isn't set up or isn't running. Run `sendDueReminders.php` by hand to check. |
| Settings shows "not supported" | Opened via an IP address instead of `localhost`. Or, on iPhone/iPad, the app wasn't opened from the Home Screen (iOS 16.4+ required). |
| Settings shows "blocked" | The user denied permission. They must re-allow it in their browser's site settings (Settings shows the steps). |
| Everyone's notifications stopped | `vapidKeys.php` was deleted or replaced. Users need to turn notifications off and on again. |
| A CSS/JS change isn't showing up | The service worker serves static files cache-first. Bump `CACHE_VERSION` in [sw.js](sw.js), or add/bump a `?v=` on the `<script>`/`<link>` tag. |
| `Unable to generate EC key` / SSL errors in the PHP log | Shouldn't happen on WAMP: the code ships its own `openssl.cnf` and uses the Windows certificate store. On another server, check the `openssl` and `curl` extensions are enabled. |
