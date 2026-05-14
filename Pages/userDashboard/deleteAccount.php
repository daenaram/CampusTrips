<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}   
?>

<!DOCTYPE html>

<html>
    <head>
        <meta charset="UTF-8">
        <title>Delete Account</title>
    </head>

    <body>
        <h1>Delete Account</h1>
        <!-- Display confirmation message and options -->
        <p>Are you sure you want to delete your account? This action cannot be undone.</p>

        <!-- Form to confirm account deletion -->
        <form method="POST" action="deleteAccountConfirm.php">
            <button type="submit" name="confirm_delete">Delete My Account</button>
            <a href="Dashboard.php"><button type="button">Cancel</button></a>
        </form>
    </body>         



</html>