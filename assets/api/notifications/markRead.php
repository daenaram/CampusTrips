<?php
// Marks all of the signed-in user's notifications as read — called when they
// open the notification bell's pop-up.
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

try {
    $stmt = $pdo->prepare("UPDATE user_notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND read_at IS NULL");
    $stmt->execute([$_SESSION['user_id']]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('Mark notifications read error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to update notifications right now.']);
}
