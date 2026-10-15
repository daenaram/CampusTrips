<?php
// Sends a test push to all of the signed-in user's devices. If they have an
// upcoming trip, the notification links to it, so clicking it exercises the
// same "open the trip's details" path a real reminder uses.
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not signed in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/webPushHelper.php';
require_once __DIR__ . '/../helpers/reminderHelper.php';

try {
    $stmt = $pdo->prepare("
        SELECT id, title FROM trips
        WHERE user_id = ? AND end_date >= CURDATE()
        ORDER BY start_date ASC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    $message = [
        'title' => 'CampusTrips test notification',
        'body'  => $trip
            ? 'Push notifications are working. Click to open "' . $trip['title'] . '".'
            : 'Push notifications are working.',
        'url'   => $trip ? tripDetailsUrl((int) $trip['id']) : DASHBOARD_PATH,
        'tag'   => 'test',
    ];

    $delivered = sendPushToUser($pdo, (int) $_SESSION['user_id'], $message);

    if ($delivered === 0) {
        http_response_code(502);
        echo json_encode(['error' => 'The test notification could not be delivered. Try turning notifications off and on again.']);
        exit();
    }

    // Shows up in the notification bell too, so the bell can be tried out.
    $pdo->prepare("INSERT INTO user_notifications (user_id, title, body, url) VALUES (?, ?, ?, ?)")
        ->execute([$_SESSION['user_id'], $message['title'], $message['body'], $message['url']]);

    echo json_encode(['success' => true, 'delivered' => $delivered]);
} catch (Throwable $e) {
    error_log('Push test error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to send a test notification right now.']);
}
?>
