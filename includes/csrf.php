<?php
/**
 * CSRF Protection Helper
 */

// Ensure session is active
if (session_status() === PHP_SESSION_NONE) {
    // Note: includes/auth.php usually starts this first.
    // If not, we will rely on auth.php being included.
}

// Generate token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Returns the active CSRF token
 */
function get_csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Outputs a hidden input field with the CSRF token
 */
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validates the request's CSRF token.
 * Terminating execution with 403 Forbidden if invalid.
 */
function validate_csrf() {
    // Only validate mutating request methods
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = '';
        
        // 1. Check POST body
        if (isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        } 
        // 2. Check Request Headers (for AJAX/fetch)
        elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        
        // Compare with session token (using hash_equals to protect against timing attacks)
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        if (empty($sessionToken) || empty($token) || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || 
                (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                die(json_encode([
                    'success' => false,
                    'message' => 'CSRF token validation failed. Forbidden.'
                ]));
            } else {
                die('CSRF token validation failed. Forbidden.');
            }
        }
    }
}
