<?php
$host   = "localhost";
$dbname = "aut_travel_planner";
$dbuser = "root";
$dbpass = "";

try {
    // Connect without specifying a database first
    $pdo = new PDO("mysql:host=$host", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`
                CHARACTER SET utf8mb4
                COLLATE utf8mb4_unicode_ci");

    // Select the database
    $pdo->exec("USE `$dbname`");

    // Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        name           VARCHAR(50)  NOT NULL,
        email              VARCHAR(100) NOT NULL UNIQUE,
        password           VARCHAR(255) NOT NULL,
        reset_token        VARCHAR(255) NULL,
        reset_token_expiry DATETIME     NULL,
        created_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    )");

    // Trips
    $pdo->exec("CREATE TABLE IF NOT EXISTS trips (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        user_id      INT          NOT NULL,
        title        VARCHAR(100) NOT NULL,
        destination  VARCHAR(100) NOT NULL,
        start_date   DATE         NOT NULL,
        end_date     DATE         NOT NULL,
        group_size   INT          DEFAULT 1,
        travel_style VARCHAR(50),
        created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Flights — temporary dummy search pool
    $pdo->exec("CREATE TABLE IF NOT EXISTS flights (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        airline            VARCHAR(100)  NOT NULL,
        flight_number      VARCHAR(20)   NOT NULL,
        departure_city     VARCHAR(100)  NOT NULL,
        arrival_city       VARCHAR(100)  NOT NULL,
        departure_airport  VARCHAR(100)  NOT NULL,
        arrival_airport    VARCHAR(100)  NOT NULL,
        departure_datetime DATETIME      NOT NULL,
        arrival_datetime   DATETIME      NOT NULL,
        duration_minutes   INT           NOT NULL,
        stops              INT           DEFAULT 0,
        cabin_class        VARCHAR(50)   DEFAULT 'Economy',
        price_nzd          DECIMAL(10,2) NOT NULL,
        created_at         TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
    )");

    // Accommodations — temporary dummy search pool
    $pdo->exec("CREATE TABLE IF NOT EXISTS accommodations (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        name                VARCHAR(150)  NOT NULL,
        type                VARCHAR(50)   NOT NULL,
        city                VARCHAR(100)  NOT NULL,
        country             VARCHAR(100)  NOT NULL,
        address             VARCHAR(255)  NOT NULL,
        check_in_time       TIME          DEFAULT '14:00:00',
        check_out_time      TIME          DEFAULT '11:00:00',
        price_per_night_nzd DECIMAL(10,2) NOT NULL,
        rating              DECIMAL(2,1)  DEFAULT 0.0,
        amenities           TEXT,
        image_url           VARCHAR(255),
        created_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
    )");

    // Saved flights — permanent user data, no link to flights table
    $pdo->exec("CREATE TABLE IF NOT EXISTS saved_flights (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        user_id            INT           NOT NULL,
        trip_id            INT           NOT NULL,
        airline            VARCHAR(100)  NOT NULL,
        flight_number      VARCHAR(20)   NOT NULL,
        departure_city     VARCHAR(100)  NOT NULL,
        arrival_city       VARCHAR(100)  NOT NULL,
        departure_airport  VARCHAR(10)   NOT NULL,
        arrival_airport    VARCHAR(10)   NOT NULL,
        departure_datetime DATETIME      NOT NULL,
        arrival_datetime   DATETIME      NOT NULL,
        duration_minutes   INT           NOT NULL,
        stops              INT           DEFAULT 0,
        cabin_class        VARCHAR(50)   DEFAULT 'Economy',
        price_nzd          DECIMAL(10,2) NOT NULL,
        notes              TEXT,
        added_at           TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
    )");

    // Saved accommodations — permanent user data, no link to accommodations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS saved_accommodations (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        user_id             INT           NOT NULL,
        trip_id             INT           NOT NULL,
        name                VARCHAR(150)  NOT NULL,
        type                VARCHAR(50)   NOT NULL,
        city                VARCHAR(100)  NOT NULL,
        country             VARCHAR(100)  NOT NULL,
        address             VARCHAR(255)  NOT NULL,
        planned_check_in    DATE,
        planned_check_out   DATE,
        check_in_time       TIME          DEFAULT '14:00:00',
        check_out_time      TIME          DEFAULT '11:00:00',
        price_per_night_nzd DECIMAL(10,2) NOT NULL,
        rating              DECIMAL(2,1),
        amenities           TEXT,
        notes               TEXT,
        added_at            TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
    )");

    // Seed dummy data quietly after table creation
    require_once __DIR__ . '/dummyData.php';

} catch (PDOException $e) {
    error_log("Database setup error: " . $e->getMessage());
    die(json_encode(["error" => "Database connection failed. Please try again later." ]));
}
?>