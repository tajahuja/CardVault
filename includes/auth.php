<?php
/**
 * CardVault Security & Authentication Handler
 */

if (session_status() === PHP_SESSION_NONE) {
    // Determine if secure cookie is appropriate (HTTPS)
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
              || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    session_start([
        'cookie_lifetime' => 0, // expire when browser is closed
        'cookie_path'     => '/',
        'cookie_secure'   => $secure,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_only_cookies' => true
    ]);
}

// Session Inactivity Timeout Check (30 minutes = 1800 seconds)
$timeout_duration = 1800;

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
        // Session expired
        logout_user();
        
        // Handle API vs Web Page redirect
        if (is_api_request()) {
            http_response_code(401);
            die(json_encode([
                'success' => false,
                'message' => 'Session expired. Please log in again.'
            ]));
        } else {
            header('Location: login.php?expired=1');
            exit;
        }
    }
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

/**
 * Check if the current request is an API request
 */
function is_api_request() {
    return strpos($_SERVER['REQUEST_URI'], '/api/') !== false || 
           (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

/**
 * Checks if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Requires login, redirects if not authenticated (or sends 401 for APIs)
 */
function require_login() {
    if (!is_logged_in()) {
        if (is_api_request()) {
            http_response_code(401);
            die(json_encode([
                'success' => false,
                'message' => 'Authentication required.'
            ]));
        } else {
            header('Location: login.php');
            exit;
        }
    }

    // Verify session user actually exists in the database to prevent foreign key errors (e.g. database reset)
    global $pdo;
    if (!isset($pdo)) {
        $pdo = require dirname(__DIR__) . '/includes/db.php';
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            // Log out user as their session is stale/orphaned
            logout_user();
            if (is_api_request()) {
                http_response_code(401);
                die(json_encode([
                    'success' => false,
                    'message' => 'Session invalid. Please log in again.'
                ]));
            } else {
                header('Location: login.php?invalid_session=1');
                exit;
            }
        }
    } catch (\Exception $e) {
        // Fallback for database check errors
        error_log("Session validation DB check failed: " . $e->getMessage());
    }
}

/**
 * Logs in a user securely and regenerates session ID to prevent fixation
 */
function login_user($userId, $userName, $userEmail) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $userName;
    $_SESSION['user_email'] = $userEmail;
    $_SESSION['last_activity'] = time();
    
    // Generate CSRF token if not present
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Logs out a user and destroys session
 */
function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}
