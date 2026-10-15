<?php
// The signed-in user's most recent notifications, for the notification bell
// next to the hamburger menu (assets/js/notificationBell.js).
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not signed in']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

const RECENT_NOTIFICATION_LIMIT = 20;

try {
    $stmt = $pdo->prepare("
        SELECT id, title, body, url, UNIX_TIMESTAMP(created_at) AS created_ts, read_at IS NULL AS unread
        FROM user_notifications
        WHERE user_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT " . RECENT_NOTIFICATION_LIMIT
    );
    $stmt->execute([$_SESSION['user_id']]);

    $notifications = array_map(fn ($n) => [
        'id'         => (int) $n['id'],
        'title'      => $n['title'],
        'body'       => $n['body'],
        'url'        => $n['url'],
        // Seconds since the epoch, so the browser can show it in its own timezone.
        'created_ts' => (int) $n['created_ts'],
        'unread'     => (bool) $n['unread'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND read_at IS NULL");
    $countStmt->execute([$_SESSION['user_id']]);

    echo json_encode([
        'notifications' => $notifications,
        'unread'        => (int) $countStmt->fetchColumn(),
    ]);
} catch (Throwable $e) {
    error_log('Recent notifications error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load notifications right now.']);
}
