<?php
/**
 * Database Configuration Example Template
 * 
 * Instructions:
 * 1. Duplicate or rename this file to db.php in the same directory (config/db.php).
 * 2. Adjust the constants below if your target machine uses a custom MySQL port, username, or password.
 * 3. Default XAMPP settings: User 'root' and an empty password ('').
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gamified_learning_platform');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
