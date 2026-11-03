<?php
// Copy this file to db.php and fill in your local database credentials.
// db.php is ignored by Git so passwords are never committed.
date_default_timezone_set('Asia/Dhaka');

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=guest_register;charset=utf8mb4',
        'root',
        '',  // set your MySQL password here
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    exit('Database connection failed.');
}
