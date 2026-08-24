<?php
/**
 * API - User Login Endpoint
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
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validation checks
if (empty($email) || empty($password)) {
    json_response(false, 'Email and password are required.', [], 400);
}

try {
    // Retrieve user by email
    $stmt = $pdo->prepare("SELECT id, name, email, password_hash FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    
    // Verify password
    if ($user && password_verify($password, $user['password_hash'])) {
        // Log in user
        login_user($user['id'], $user['name'], $user['email']);
        
        json_response(true, 'Login successful. Welcome back!', [
            'redirect' => 'dashboard.php'
        ]);
    } else {
        // Generic error message for security
        json_response(false, 'Invalid email address or password.', [], 401);
    }
    
} catch (\PDOException $e) {
    error_log("Login DB Error: " . $e->getMessage());
    json_response(false, 'An error occurred during login. Please try again.', [], 500);
}
