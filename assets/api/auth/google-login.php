<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/redirectHelper.php';

header('Content-Type: application/json');

// Get the token from the request
$input = json_decode(file_get_contents('php://input'), true);
$idToken = $input['idToken'] ?? '';

if (empty($idToken)) {
    http_response_code(400);
    echo json_encode(['error' => 'No token provided']);
    exit();
}

try {
    // Decode Firebase token (basic validation)
    // In production, you should verify the token signature with Google's public keys
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        throw new Exception('Invalid token format');
    }

    // Decode the payload (second part)
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
    
    if (!$payload || !isset($payload['email'])) {
        throw new Exception('Invalid token payload');
    }

    $email = $payload['email'];
    $name = $payload['name'] ?? '';

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Create new user if doesn't exist
        $name = $name ?: explode('@', $email)[0];
        // Generate a random password for Google users (they don't use password login)
        $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $insertStmt->execute([$name, $email, $randomPassword]);

        $userId = $pdo->lastInsertId();
        
    } else {
        $userId = $user['id'];
        $name = $user['name'];
    }

    // Create session
    $_SESSION['user_id'] = $userId;
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;

    $dashboardUrl = '/AUT-Web-Based-Travel-Planner/Pages/userDashboard/Dashboard.php';
    $requestedRedirect = is_string($input['redirect'] ?? null) ? $input['redirect'] : null;
    echo json_encode(['success' => true, 'redirect' => safePostLoginRedirect($requestedRedirect, $dashboardUrl)]);

} catch (Exception $e) {
    error_log("Google login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Authentication failed']);
}
?>
