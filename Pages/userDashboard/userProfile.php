<?php
// Start session to access user data
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}

require_once __DIR__ . '/../../assets/api/config/database.php';

$stmt = $pdo->prepare("SELECT name, email, created_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User profile not found.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link rel="stylesheet" href="../../assets/css/loginformStyles.css">

</head>
<body>
    <!-- Page heading for profile setup
    <h1>CampusTrips</h1>
    <h2>User Profile</h2>
    
    <div class="profile-card">
    <p><strong>name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
    <p><strong>Account Created:</strong> <?php echo htmlspecialchars($user['created_at']); ?></p>
    </div>

    <p><a href="Dashboard.php">Back to Dashboard</a></p>
    <p><a href="../UserAuthentication/change_password.php">Change Password</a></p>
    Add profile setup form and fields here
    <a href = "/AUT-Web-Based-Travel-Planner/assets/api/auth/signout.php">Sign Out</a> -->

    <div class="profile-page">
        <h1>Campus Trip</h1>
        <hr>

        <!-- Profile Picture -->
        <div class="profile-header">
            <div class="profile-avatar">
                <div class="avatar-head"></div>
                <div class="avatar-body"></div>
        </div>
        
        <button class="profile-picture-btn">Update Profile Picture</button>
    </div>

    <!-- Profile Info -->
            <div class="profile-info">
                <h2>Profile Information</h2>

                <div class="profile-grid">
                    <label>Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>

                    <label>Email</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
            </div>
           <div class="profile-buttons">
            <button onclick="location.href='../UserAuthentication/change_password.php'">Change Password</button>
            <button type="backButton" onclick="location.href='Dashboard.php'">Back to Dashboard</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>