<?php
/**
 * API - User Registration Endpoint
 */

header('Content-Type: application/json; charset=utf-8');

// Include requirements
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = require_once dirname(__DIR__) . '/includes/db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', [], 405);
}

// Validate CSRF token
validate_csrf();

// Get POST parameters
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validation checks
if (empty($name) || empty($email) || empty($password)) {
    json_response(false, 'All fields are required.', [], 400);
}

if (strlen($name) < 2 || strlen($name) > 255) {
    json_response(false, 'Name must be between 2 and 255 characters.', [], 400);
}

if (!is_valid_email($email)) {
    json_response(false, 'Invalid email address format.', [], 400);
}

if (strlen($password) < 8) {
    json_response(false, 'Password must be at least 8 characters long.', [], 400);
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        json_response(false, 'This email address is already registered.', [], 409);
    }
    
    // Hash password and insert user
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)");
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => $passwordHash
    ]);
    
    $userId = $pdo->lastInsertId();
    
    // Log user in automatically
    login_user($userId, $name, $email);
    
    json_response(true, 'Registration successful. Welcome to CardVault!', [
        'redirect' => 'dashboard.php'
    ], 201);
    
} catch (\PDOException $e) {
    error_log("Registration DB Error: " . $e->getMessage());
    json_response(false, 'An error occurred during registration. Please try again.', [], 500);
}
