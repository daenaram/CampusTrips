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

    <style>
        .profile-topbar {
            display: flex;
            align-items: center;
            gap: 16px;
            justify-content: flex-start;
        }
        .back-to-dashboard-btn {
            background: none;
            border: 1px solid #0B2545;
            color: #0B2545;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            white-space: nowrap;
        }
        .back-to-dashboard-btn:hover {
            background: #0B2545;
            color: #fff;
        }
        .profile-topbar h1 {
            margin: 0;
        }
    </style>
</head>
<body>

    <div class="profile-page">
        <!-- Header row: Back to Dashboard on the left, title next to it -->
        <div class="profile-topbar">
            <button type="button" class="back-to-dashboard-btn" onclick="location.href='Dashboard.php'">
                ← Back to Dashboard
            </button>
            <h1>Campus Trip</h1>
        </div>
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
                <button type="button" onclick="location.href='../UserAuthentication/change_password.php'">Change Password</button>
                <button type="button" onclick="location.href='deleteAccount.php'">Delete Account</button>
                <button type="button" onclick="location.href='/AUT-Web-Based-Travel-Planner/assets/api/auth/signout.php'">Sign Out</button>
            </div>
        </div>
    </div>

</body>
</html>