<?php
// Saves (or re-assigns) a browser's push subscription for the signed-in user.
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

$input = json_decode(file_get_contents('php://input'), true);
$endpoint = trim($input['endpoint'] ?? '');
$p256dh = trim($input['keys']['p256dh'] ?? '');
$auth = trim($input['keys']['auth'] ?? '');

if ($endpoint === '' || $p256dh === '' || $auth === ''
    || strlen($endpoint) > 1000 || strlen($p256dh) > 255 || strlen($auth) > 255
    || !webPushIsAllowedEndpoint($endpoint)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid push subscription']);
    exit();
}

try {
    // The same browser endpoint may previously have belonged to another
    // account on this device — it now belongs to whoever just enabled it.
    $stmt = $pdo->prepare("
        INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh),
                                auth = VALUES(auth), user_agent = VALUES(user_agent)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $endpoint,
        hash('sha256', $endpoint),
        $p256dh,
        $auth,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('Push subscribe error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to enable push notifications right now.']);
}
?>
