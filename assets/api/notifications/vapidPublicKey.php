<?php
// Gives the browser the server's VAPID public key, which it needs to
// create a push subscription tied to this server.
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not signed in']);
    exit();
}

require_once __DIR__ . '/../helpers/webPushHelper.php';

try {
    echo json_encode(['publicKey' => webPushVapidKeys()['publicKey']]);
} catch (Throwable $e) {
    error_log('VAPID key error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Push notifications are unavailable right now.']);
}
?>
