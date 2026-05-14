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
require_once __DIR__ .'/../../Pages/UserAuthentication/login_security.php';

// Get and sanitize form data
$email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';
$password = isset($_POST['password']) ? $_POST['password']       : '';

//echo "The password entered was: " . htmlspecialchars($password); //debugging password 

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
    $stmt = $pdo->prepare("SELECT id, name, password, failed_attempts, locked_out FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user exists
    if (!$user) {
        header("Location: {$BASE_URL}/loginForm.html?error=invalid_credentials");
        exit();
    }

    //check if account is locked
    if($user['locked_out']) {
        header("Location: {$BASE_URL}/loginForm.html?error=account_locked");
        exit();
    }


    // Verify password hash
    if (!password_verify($password, $user['password'])) {

        // Record failed login attempt
        if (function_exists('recordFailedLogin')) {
            recordFailedLogin($pdo, $email);
        }

        header("Location: {$BASE_URL}/loginForm.html?error=invalid_credentials");
        exit();
    }

    // Reset failed login attempts on successful login
    if (function_exists('resetFailedLogin')) {
        resetFailedLogin($pdo, $email);
    }

    // Store user data in session
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['name'] = $user['name'];
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
