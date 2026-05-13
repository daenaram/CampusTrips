<?php
$host     = "localhost";
$dbname   = "aut_travel_planner";
$dbuser   = "root";        // your MySQL username
$dbpass   = "";            // your MySQL password

try {
    // Connect WITHOUT specifying a database first
    $pdo = new PDO("mysql:host=$host", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create the database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` 
                CHARACTER SET utf8mb4 
                COLLATE utf8mb4_unicode_ci");

    // Now select the database
    $pdo->exec("USE `$dbname`");

    // Create the users table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        username     VARCHAR(50),
        email        VARCHAR(100) NOT NULL UNIQUE,
        password     VARCHAR(255) NOT NULL,

        failed_attempts INT DEFAULT 0,
        locked_out_until DATETIME NULL,

        reset_token VARCHAR(255) NULL,
        reset_token_expiry DATETIME NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    //Add failed_attempts and locked_out_until columns for account lockout mechanism
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN failed_attempts INT DEFAULT 0");
    } catch (PDOException $e) {
        error_log("failed_attempts column may already exist: " . $e->getMessage());    
    } 
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN locked_out_until DATETIME NULL");
    } catch (PDOException $e) {
        error_log("locked_out_until column may already exist: " . $e->getMessage());    
    }

        // Create the trips table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS trips (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        user_id      INT NOT NULL,
        title        VARCHAR(100) NOT NULL,
        destination  VARCHAR(100) NOT NULL,
        start_date   DATE NOT NULL,
        end_date     DATE NOT NULL,
        group_size   INT DEFAULT 1,
        travel_style VARCHAR(50),
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

} catch (PDOException $e) {
    error_log("Database setup error: " . $e->getMessage());
    die(json_encode(["error" => "Database connection failed. Please try again later."]));
}
?>