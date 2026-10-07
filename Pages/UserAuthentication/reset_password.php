<?php
require_once __DIR__ . '/../../assets/api/config/database.php';

$message = "";
$token = $_GET["token"] ?? ($_POST["token"] ?? "");

if (empty($token)) {
    die("Invalid password reset link.");
}

$stmt = $pdo->prepare("SELECT id, reset_token_expiry FROM users WHERE reset_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Invalid password reset link.");
}

if (strtotime($user["reset_token_expiry"]) < time()) {
    die("This password reset link has expired. Please request a new one.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (empty($new_password) || empty($confirm_password)) {
        $message = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $message = "New password and confirmation do not match.";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $new_password)) {
        $message = "Password must be at least 8 characters and include an uppercase letter, a number, and a symbol.";
    } else {
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

        $update = $pdo->prepare("
            UPDATE users
            SET password = ?, reset_token = NULL, reset_token_expiry = NULL
            WHERE id = ?
        ");
        $update->execute([$new_password_hash, $user["id"]]);

        header("Location: loginForm.html?message=password_reset_success");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <script src="/AUT-Web-Based-Travel-Planner/assets/js/pwa.js"></script>
        <meta charset="UTF-8">
        <title>Reset Password</title>
        <link rel="stylesheet" href="../../assets/css/loginformStyles.css">
</head>
<body>
    <h1>AUT Web-Based Travel Planner</h1>
    <h2>Reset Password</h2>

    <?php if (!empty($message)): ?>
        <p class="note"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <label>New Password</label>
            <input type="password" name="new_password" required>
            <ul class="password-requirements">
                <li id="length">At least 8 characters</li>
                <li id="uppercase">Contains an uppercase letter</li>
                <li id="number">Contains a number</li>
                <li id="symbol">Contains a symbol</li>
            </ul>
            
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required>

            <button type="submit">Reset Password</button>
            <button type="backButton" onclick="location.href='../UserAuthentication/loginForm.html'">Back to Login</button>
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