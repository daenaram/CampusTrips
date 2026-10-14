<?php
// Removes a browser's push subscription for the signed-in user.
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

$input = json_decode(file_get_contents('php://input'), true);
$endpoint = trim($input['endpoint'] ?? '');

if ($endpoint === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing endpoint']);
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?");
    $stmt->execute([hash('sha256', $endpoint), $_SESSION['user_id']]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('Push unsubscribe error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to turn off push notifications right now.']);
}
?>
