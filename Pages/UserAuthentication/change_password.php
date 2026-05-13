<?php
session_start();

require_once __DIR__ . '/../../assets/api/config/database.php';

//Making sure that user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: loginForm.html");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $old_password = $_POST["old_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $message = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $message = "New password and confirmation do not match.";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $new_password)) {
        $message = "New password doesn't meet password requirements.";
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($old_password, $user["password"])) {
            $message = "Old password is incorrect.";
        } else {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$new_password_hash, $_SESSION['user_id']]);

            //Log user out after password change
            session_unset();
            session_destroy();

            header("Location: loginForm.html?message=password_changed");
            exit();
        }
    }
} 
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Change Password</title>
        <link rel="stylesheet" href="../../assets/css/loginformStyles.css">
    </head>
    <body>
        <h1>AUT Web-Based Travel Planner</h1>
        <h2>Change Password</h2>

        <?php if (!empty($message)): ?>
            <p class="note"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form method="POST">
            <label>Old Password</label><br>
            <input type="password" name="old_password" required><br><br>

            <label>New Password:</label><br>
            <input type="password" name="new_password" required><br>
            <ul class="password-requirements">
                <li id="length">At least 8 characters</li>
                <li id="uppercase">Contains an uppercase letter</li>
                <li id="number">Contains a number</li>
                <li id="symbol">Contains a symbol</li>
            </ul>

            <label>Confirm New Password:</label><br>
            <input type="password" name="confirm_password" required><br><br>

            <button type="submit">Submit New Password</button>
            <button type="backButton" onclick="location.href='../userDashboard/userProfile.php'">Back to Profile</button>
        </form>

        <script>
            const passwordInput = document.querySelector('input[name="new_password"]');

            const lengthReq = document.getElementById("length");
            const uppercaseReq = document.getElementById("uppercase");
            const numberReq = document.getElementById("number");
            const symbolReq = document.getElementById("symbol");

            passwordInput.addEventListener("input", function () {
                const value = passwordInput.value;

                if (value.length >= 8) {
                    lengthReq.classList.add("valid");
                } else {
                    lengthReq.classList.remove("valid");
                }

                if (/[A-Z]/.test(value)) {
                    uppercaseReq.classList.add("valid");
                } else {
                    uppercaseReq.classList.remove("valid");
                }

                if (/\d/.test(value)) {
                    numberReq.classList.add("valid");
                } else {
                    numberReq.classList.remove("valid");
                }

                if (/[\W_]/.test(value)) {
                    symbolReq.classList.add("valid");
                } else {
                    symbolReq.classList.remove("valid");
                }
            });
        </script>
    </body>
</html>