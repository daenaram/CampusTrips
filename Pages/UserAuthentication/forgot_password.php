<?php
require_once __DIR__ . '/../../assets/api/config/database.php';

$message = "";
$reset_link = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if (empty($email)) {
        $message = "Please enter your registered email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user){
            $token = bin2hex(random_bytes(32));
            $expiry = date("Y-m-d H:i:s", strtotime("+30 minutes"));

            $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $update->execute([$token, $expiry, $user["id"]]);
            
            header("Location: reset_password.php?token=" . urlencode($token));
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Forgot Password</title>
        <link rel="stylesheet" href="../../assets/css/loginformStyles.css">
</head>
<body>
    <h1>AUT Web-Based Travel Planner</h1>
    <h2>Forgot Password</h2>

        <button type="backButton" onclick="location.href='../UserAuthentication/forgot_password.html'">Try Another Email</button>
        <button type="backButton" onclick="location.href='../UserAuthentication/loginForm.html'">Back to Login</button>

</body>
</html>
