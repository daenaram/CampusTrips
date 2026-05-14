<?php
session_start();

$BASE_URL      = '/AUT-Web-Based-Travel-Planner/Pages/UserAuthentication';
$DASHBOARD_URL = '/AUT-Web-Based-Travel-Planner/Pages/userDashboard/Dashboard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$BASE_URL}/signup.html");
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Get and sanitize form data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';
$password = isset($_POST['password']) ? $_POST['password']       : '';
$confirmPassword = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';

// Basic validation
if ($email === '' || $password === '' || $confirmPassword === '') {
    header("Location: {$BASE_URL}/signup.html?error=all_fields_required");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: {$BASE_URL}/signup.html?error=invalid_email");
    exit();
}

if (!preg_match('/^(?=.[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}$/', $password)) {
    header("Location: {$BASE_URL}/signup.html?error=password_requirements");
    exit();
}

try {
    // Check email specifically
    $stmtEmail = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmtEmail->execute([$email]);
    $emailExists = (int) $stmtEmail->fetchColumn();

    if ($emailExists > 0) {
        header("Location: {$BASE_URL}/signup.html?error=email_taken");
        exit();
    }

    if ($password !== $confirmPassword) {
        header("Location: {$BASE_URL}/signup.html?error=password_mismatch");
        exit();
    }

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert into database
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$name, $email, $hashedPassword]);

    // Log the user in
    $userId = $pdo->lastInsertId();
    $_SESSION['user_id']  = $userId;
    $_SESSION['name'] = $name;

    // Redirect to dashboard
    header("Location: {$DASHBOARD_URL}");
    exit();

} catch (Exception $e) {
    error_log("Signup error: " . $e->getMessage());
    header("Location: {$BASE_URL}/signup.html?error=server_error");
    exit();
}
?>