<?php
// Start session for user authentication
session_start();


// Define redirect URLs
$BASE_URL      = '/AUT-Web-Based-Travel-Planner/Pages/UserAuthentication';
$DASHBOARD_URL = '/AUT-Web-Based-Travel-Planner/Pages/userDashboard/Dashboard.php';

/**
 * Only ever allows redirecting back to a shared trip link ("sign in to view
 * or edit this trip") — never an arbitrary posted URL, to rule out this
 * becoming an open redirect. Anything else falls back to the dashboard.
 */
function safePostLoginRedirect(?string $requested, string $dashboardUrl): string
{
    if ($requested === null) {
        return $dashboardUrl;
    }

    $allowedPatterns = [
        '#^/AUT-Web-Based-Travel-Planner/Pages/shared/sharedTrip\.php\?token=[a-f0-9]{40}$#',
        '#^/AUT-Web-Based-Travel-Planner/Pages/userDashboard/Dashboard\.php\?shared_token=[a-f0-9]{40}$#',
    ];

    foreach ($allowedPatterns as $pattern) {
        if (preg_match($pattern, $requested)) {
            return $requested;
        }
    }

    return $dashboardUrl;
}

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
    //check if user account is locked out before checking password
    if (isAccountLocked($pdo, $email)) {
        header("Location: {$BASE_URL}/loginForm.html?error=account_locked");
        exit();
    }

    // Query database for user by email
    $stmt = $pdo->prepare("SELECT id, name, password, failed_attempts, locked_out FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user exists
    if (!$user) {
        header("Location: {$BASE_URL}/loginForm.html?error=invalid_credentials");
        exit();
    }

   
    // Verify password
    if (!password_verify($password, $user['password'])) {

        // Record failed login attempt
        recordFailedLogin($pdo, $email);

        if (isAccountLocked($pdo, $email)) {
            header("Location: {$BASE_URL}/loginForm.html?error=account_locked");
        } else {
            header("Location: {$BASE_URL}/loginForm.html?error=invalid_credentials");
        }
        exit();
    }

    //reset failed login attempts on successful login
    resetFailedLogin($pdo, $email);
    session_regenerate_id(true); // Regenerate session ID to prevent session fixation

    // Store user data in session
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email']    = $email;

    // Redirect to the dashboard, unless the visitor arrived from a shared
    // trip link's "Sign in to edit" prompt, in which case send them back to it.
    $postLoginRedirect = safePostLoginRedirect($_POST['redirect'] ?? null, $DASHBOARD_URL);
    header("Location: {$postLoginRedirect}");
    exit();

} catch (Exception $e) {
    // Log error and redirect with error message
    error_log("Login error: " . $e->getMessage());
    header("Location: {$BASE_URL}/loginForm.html?error=server_error");
    exit();
}
?>
