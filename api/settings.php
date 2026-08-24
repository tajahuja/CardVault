<?php
/**
 * API - User Settings & Account Privacy Management
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$pdo = require_once dirname(__DIR__) . '/includes/db.php';

// Assert user is logged in
require_login();

$userId = $_SESSION['user_id'];

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', [], 405);
}

// Validate CSRF token
validate_csrf();

$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($action === 'change_password') {
    $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        json_response(false, 'All password fields are required.', [], 400);
    }
    
    if ($newPassword !== $confirmPassword) {
        json_response(false, 'New passwords do not match.', [], 400);
    }
    
    if (strlen($newPassword) < 8) {
        json_response(false, 'New password must be at least 8 characters long.', [], 400);
    }
    
    try {
        // Fetch current password hash
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $hash = $stmt->fetchColumn();
        
        if (!$hash || !password_verify($currentPassword, $hash)) {
            json_response(false, 'Incorrect current password.', [], 401);
        }
        
        // Hash new password and update
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
        $updateStmt->execute([
            'hash' => $newHash,
            'id' => $userId
        ]);
        
        json_response(true, 'Password updated successfully.');
        
    } catch (\PDOException $e) {
        error_log("Change password DB error: " . $e->getMessage());
        json_response(false, 'Unable to change password. Please try again.', [], 500);
    }
} 
elseif ($action === 'delete_account') {
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    if (empty($confirmPassword)) {
        json_response(false, 'Please enter your password to confirm account deletion.', [], 400);
    }
    
    try {
        // Fetch password hash to confirm
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $hash = $stmt->fetchColumn();
        
        if (!$hash || !password_verify($confirmPassword, $hash)) {
            json_response(false, 'Incorrect password. Account deletion aborted.', [], 401);
        }
        
        // Fetch all card images to delete from disk (garbage collection)
        $imgStmt = $pdo->prepare("SELECT original_card_image FROM contacts WHERE user_id = :user_id AND original_card_image IS NOT NULL");
        $imgStmt->execute(['user_id' => $userId]);
        $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $uploadDir = dirname(__DIR__) . '/uploads/business_cards/';
        foreach ($images as $img) {
            $path = $uploadDir . basename($img);
            if (file_exists($path)) {
                @unlink($path); // Delete card image file from server
            }
        }
        
        // Delete user (foreign key cascades will delete contacts, notes, contact_tags, and tags)
        $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $deleteStmt->execute(['id' => $userId]);
        
        // Clear session and cookies
        logout_user();
        
        json_response(true, 'Account deleted successfully.', [
            'redirect' => 'login.php?logged_out=1'
        ]);
        
    } catch (\PDOException $e) {
        error_log("Delete account DB error: " . $e->getMessage());
        json_response(false, 'Unable to delete account. Please try again.', [], 500);
    }
} else {
    json_response(false, 'Invalid settings action.', [], 400);
}
