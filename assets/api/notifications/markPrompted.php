<?php
// Records that the signed-in user has answered the dashboard's first-login
// push notification prompt (Allow or Not now), so it isn't shown again.
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
    $stmt = $pdo->prepare("UPDATE users SET push_prompted_at = NOW() WHERE id = ? AND push_prompted_at IS NULL");
    $stmt->execute([$_SESSION['user_id']]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('Push prompt save error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to save your choice right now.']);
}
?>
