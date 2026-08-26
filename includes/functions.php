<?php
/**
 * Global Helper Functions for CardVault
 */

/**
 * Escapes strings for secure output in HTML to prevent XSS.
 */
function e($string) {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Sends a standard JSON response and terminates.
 */
function json_response($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

/**
 * Formats a database date string (YYYY-MM-DD) into user-friendly format (e.g. 19 August 2026).
 */
function format_date_user($dateStr) {
    if (empty($dateStr)) return '';
    $timestamp = strtotime($dateStr);
    return $timestamp ? date('d F Y', $timestamp) : '';
}

/**
 * Validates website URLs, making sure they begin with http:// or https://.
 */
function clean_url($url) {
    if (empty($url)) return '';
    $url = trim($url);
    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $url = "https://" . $url;
    }
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

/**
 * Validates email address format
 */
function is_valid_email($email) {
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Normalizes a phone number and returns a valid WhatsApp URL if safe, or an empty string.
 */
function get_whatsapp_url($phone) {
    if (empty($phone)) return '';
    
    // Check if the original phone number starts with a plus sign (international indicator)
    $hasPlus = (strpos(trim($phone), '+') === 0);
    
    // Strip all non-digit characters
    $digits = preg_replace('/\D/', '', $phone);
    
    // Handle Indian number formats
    if (!$hasPlus) {
        if (strlen($digits) === 10) {
            // Standard 10-digit Indian number: prepend country code 91
            $digits = '91' . $digits;
        } elseif (strlen($digits) === 11 && strpos($digits, '0') === 0) {
            // 11-digit Indian number starting with 0: remove 0 and prepend 91
            $digits = '91' . substr($digits, 1);
        }
    }
    
    // Validate length for WhatsApp (generally 10 to 15 digits including country code)
    $len = strlen($digits);
    if ($len >= 10 && $len <= 15) {
        return 'https://wa.me/' . $digits;
    }
    
    return '';
}

/**
 * Logs a contact interaction in the timeline
 */
function log_interaction($contactId, $type, $description) {
    global $pdo;
    
    // If $pdo is not globally available, build it
    if (!isset($pdo)) {
        $pdo = require_once __DIR__ . '/db.php';
    }
    
    // Check if user is logged in
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) return false;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO interactions (contact_id, user_id, type, description) 
            VALUES (:contact_id, :user_id, :type, :description)
        ");
        return $stmt->execute([
            'contact_id'  => $contactId,
            'user_id'     => $userId,
            'type'        => $type,
            'description' => $description
        ]);
    } catch (\PDOException $e) {
        error_log("Failed to log interaction: " . $e->getMessage());
        return false;
    }
}

