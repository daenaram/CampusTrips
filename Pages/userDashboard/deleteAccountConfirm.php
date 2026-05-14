<?php

session_start();

require_once __DIR__ . '/../../assets/api/config/database.php';

//checking if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html");
    exit();
}

//security check - whether the delete confirmation form was submitted and clicked on
if($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirm_delete'])) {
    header("Location: Dashboard.php");
    exit();
}

//get user's ID 
$user_id = $_SESSION['user_id'];
try {
    //delete user account from database
    $sql = "DELETE FROM users WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':id' => $user_id
    ]);

    //clear all session variables and destroy the session
    session_unset();
    session_destroy();

    //redirect to login page after account deletion
    header("Location: /AUT-Web-Based-Travel-Planner/Pages/UserAuthentication/loginForm.html?account_deleted=true");
    exit();
} catch (PDOException $e){
    /*
        If there is a database error,
        redirect back with an error message
    */
    header("Location: deleteAccount.php?error=delete_failed");

    exit();

}

?>