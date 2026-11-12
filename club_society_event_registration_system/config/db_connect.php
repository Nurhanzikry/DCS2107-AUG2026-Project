<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "club_society_event_registration_system";

try {
    $pdo = new PDO(
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Club & Society database connection failed: " . $e->getMessage());
    http_response_code(500);
    exit("Database connection failed.");
}
