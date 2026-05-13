<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$name  = isset($input['name'])  ? trim($input['name'])  : '';
$email = isset($input['email']) ? trim($input['email']) : '';

if (!$name || !$email) {
    echo "error: missing fields";
    exit();
}

try {
    // Check if this demo user already exists in the DB
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // First time — insert a demo user row (no real password needed)
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password)
            VALUES (?, ?, ?)
        ");
        $dummyPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $stmt->execute([$name, $email, $dummyPassword]);

        $userId   = $pdo->lastInsertId();
        $username = $name;
    } else {
        $userId   = $user['id'];
        $username = $user['username'];
    }

    // Set the same session variables that login.php sets
    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['email']    = $email;

    echo "success";

} catch (Exception $e) {
    error_log("Firebase session error: " . $e->getMessage());
    echo "error: server error";
}
?>