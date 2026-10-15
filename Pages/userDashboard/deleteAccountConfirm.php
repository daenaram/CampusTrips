<?php

session_start();

require_once __DIR__ . '/../../assets/api/config/database.php';

//checking if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}

//security check - whether the delete confirmation form was submitted and clicked on
if($_SERVER['REQUEST_METHOD'] !== 'POST' ) { //|| !isset($_POST['confirm_delete'])
    header("Location: Dashboard.php");
    exit();
}

//get user's ID 
$user_id = $_SESSION['user_id'];
//$confirmInput = isset($_POST['confirmInput']) ? $_POST['confirmInput'] : '';

/**echo '<pre>';
print_r($_POST);
echo '</pre>';
exit();
**/
$confirmInput = trim($_POST['confirmInput'] ?? '');

if($confirmInput !== 'DELETE') {
    header("Location: deleteAccount.php?error=invalid_input");
    exit();
}

try {
    //delete user account from database
    $sql = "DELETE FROM users WHERE id = :id";

    $stmt = $pdo->prepare($sql);

   $stmt->execute([
        ':id' => $user_id
    ]);

    // Stop pushing reminders to this person's devices. (users is MyISAM,
    // so there's no foreign key to cascade this automatically.)
    $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare("DELETE FROM push_reminder_log WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare("DELETE FROM user_notifications WHERE user_id = ?")->execute([$user_id]);

    //clear all session variables and destroy the session
    session_unset();
    session_destroy();

    echo "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Account Deleted</title>
    </head>
    <body>
        <script>
            alert('Your account has been successfully deleted.');
            window.location.href = '/AUT-Web-Based-Travel-Planner/index.html';
        </script>
    </body>
    </html>
    ";

    exit();
} catch (PDOException $e){
    /*
        If there is a database error,
        redirect back with an error message
    */
    error_log("Account Deletion Failed for user ID {$user_id}: " . $e->getMessage());
    header("Location: deleteAccount.php?error=delete_failed");

    exit();

}

?>