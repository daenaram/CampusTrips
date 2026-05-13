<?php
// Start session for user authentication
session_start();

// Define redirect URLs
$BASE_URL      = '/AUT-Web-Based-Travel-Planner/Pages/UserAuthentication';
$DASHBOARD_URL = '/AUT-Web-Based-Travel-Planner/Pages/userDashboard/Dashboard.php';

// Only process POST requests from the login form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$BASE_URL}/loginForm.html");
    exit();
}

// Include database connection
require_once __DIR__ . '/../../assets/api/config/database.php';

// Get and sanitize form data
$email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';
$password = isset($_POST['password']) ? $_POST['password']       : '';

echo "The password entered was: " . htmlspecialchars($password); //debugging password 

// Check if both email and password are provided
if ($email === '' || $password === '') {
    header("Location: {$BASE_URL}/loginForm.html?error=all_fields_required");
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: {$BASE_URL}/loginForm.html?error=invalid_email");
    exit();
}

try {
    // Query database for user by email
    $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user exists
    if (!$user) {
        header("Location: {$BASE_URL}/loginForm.html?error=invalid_credentials");
        exit();
    }

    //Check if account is locked out
    if ($user['locked_out_until'] && strtotime($user['locked_out_until']) > time()) {
        header("Location: {$BASE_URL}/loginForm.html?error=account_locked");
        exit();
    }

    // Verify password hash
    if (!password_verify($password, $user['password'])) {
        header("Location: {$BASE_URL}/loginForm.html?error=invalid_credentials");
        exit();
    }

    // Store user data in session
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $email;

    // Redirect to dashboard on successful login
    header("Location: {$DASHBOARD_URL}");
    exit();

} catch (Exception $e) {
    // Log error and redirect with error message
    error_log("Login error: " . $e->getMessage());
    header("Location: {$BASE_URL}/loginForm.html?error=server_error");
    exit();
}
?>
