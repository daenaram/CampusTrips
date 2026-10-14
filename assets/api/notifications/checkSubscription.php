<?php
// Tells the browser whether its push subscription is registered to the
// signed-in user — a browser can still hold a subscription that belongs to
// someone else who used this device, or one the server has since pruned.
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

try {
    $stmt = $pdo->prepare("SELECT 1 FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?");
    $stmt->execute([hash('sha256', $endpoint), $_SESSION['user_id']]);

    echo json_encode(['active' => $endpoint !== '' && (bool) $stmt->fetchColumn()]);
} catch (PDOException $e) {
    error_log('Push status check error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to check notification status right now.']);
}
?>
