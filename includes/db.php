<?php
/**
 * CardVault Database Connection Helper
 */

// Define configuration path
$configPath = dirname(__DIR__) . '/config/database.php';

if (!file_exists($configPath)) {
    // Return a function or script termination that tells the user to configure DB
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Configuration file config/database.php is missing. Please copy config/database.example.php to config/database.php and enter your database credentials.'
    ]));
}

$dbConfig = require $configPath;

try {
    $dsn = "mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['dbname'] . ";charset=" . $dbConfig['charset'];
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
} catch (\PDOException $e) {
    // Hide details from user but write to server log
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please check your credentials in config/database.php and ensure the database server is running.'
    ]));
}

return $pdo;
